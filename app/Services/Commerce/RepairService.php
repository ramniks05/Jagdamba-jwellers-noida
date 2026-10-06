<?php

namespace App\Services\Commerce;

use App\Enums\DocumentType;
use App\Enums\InventoryMovement;
use App\Enums\LedgerDirection;
use App\Enums\PartyType;
use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Payment;
use App\Models\RepairOrder;
use App\Services\Foundation\DocumentNumberService;
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RepairService
{
    /** @var array<string, array<int, string>> */
    private array $next = [
        'received' => ['inspection', 'cancelled'],
        'inspection' => ['repairing', 'cancelled'],
        'repairing' => ['ready', 'cancelled'],
        'ready' => ['delivered'],
    ];

    public function __construct(
        private readonly CompanyContext $context,
        private readonly InventoryService $inventory,
        private readonly LedgerService $ledger,
        private readonly DocumentNumberService $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function receive(Company $company, array $attributes, ?int $userId = null): RepairOrder
    {
        return DB::transaction(function () use ($company, $attributes, $userId) {
            $this->context->ensureId((int) $company->id);
            $customer = Customer::query()->where('uuid', $attributes['customer_uuid'])->where('is_active', true)->first();

            if (! $customer) {
                throw ValidationException::withMessages([
                    'customer_uuid' => 'Choose a customer.',
                ]);
            }

            $item = null;

            if (trim((string) ($attributes['item_uuid'] ?? '')) !== '') {
                $item = Item::query()->where('uuid', $attributes['item_uuid'])->first();

                if (! $item) {
                    throw ValidationException::withMessages([
                        'item_uuid' => 'That piece was not found.',
                    ]);
                }
            }

            $branch = Branch::query()->where('is_head_office', true)->first()
                ?? Branch::query()->orderBy('id')->first();
            $number = $this->numbers->issue($this->numbers->for(DocumentType::Repair, $branch));
            $repair = RepairOrder::query()->create([
                'company_id' => $company->id,
                'branch_id' => $branch?->id,
                'customer_id' => $customer->id,
                'item_id' => $item?->id,
                'number' => $number->number,
                'description' => $attributes['description'],
                'problem' => $attributes['problem'],
                'gross_weight' => $attributes['gross_weight'],
                'technician' => trim((string) ($attributes['technician'] ?? '')) ?: null,
                'estimated_cost' => $attributes['estimated_cost'] ?? '0',
                'expected_on' => $attributes['expected_on'] ?? null,
                'status' => 'received',
                'received_at' => now(),
                'notes' => trim((string) ($attributes['notes'] ?? '')) ?: null,
                'user_id' => $userId,
            ]);

            if ($item) {
                $this->inventory->apply($item, InventoryMovement::RepairOut, $repair, null, null, 'Sent for repair', $userId);
            }

            return $repair;
        });
    }

    public function advance(RepairOrder $repair, string $status): RepairOrder
    {
        return DB::transaction(function () use ($repair, $status) {
            $this->context->ensureId((int) $repair->company_id);
            $allowed = $this->next[$repair->status] ?? [];

            if (! in_array($status, $allowed, true) || $status === 'delivered') {
                throw ValidationException::withMessages([
                    'status' => 'This repair cannot move to that step.',
                ]);
            }

            if ($status === 'cancelled' && $repair->item) {
                $this->inventory->apply($repair->item, InventoryMovement::RepairIn, $repair, null, null, 'Repair cancelled', null);
            }

            $repair->status = $status;
            $repair->save();

            return $repair;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function deliver(RepairOrder $repair, array $attributes, ?int $userId = null): RepairOrder
    {
        return DB::transaction(function () use ($repair, $attributes, $userId) {
            $this->context->ensureId((int) $repair->company_id);
            $repair->load('customer');

            if ($repair->status !== 'ready') {
                throw ValidationException::withMessages([
                    'status' => 'Mark the repair ready before delivery.',
                ]);
            }

            $charge = BigDecimal::of((string) ($attributes['final_charge'] ?? '0'))->toScale(2, RoundingMode::HalfUp);
            $paid = BigDecimal::of(trim((string) ($attributes['payment'] ?? '')) === '' ? '0' : (string) $attributes['payment'])->toScale(2, RoundingMode::HalfUp);

            if ($paid->isGreaterThan($charge)) {
                throw ValidationException::withMessages([
                    'payment' => 'The payment cannot be more than the repair charge.',
                ]);
            }

            if ($repair->customer->is_system && ! $paid->isEqualTo($charge)) {
                throw ValidationException::withMessages([
                    'payment' => 'A walk-in repair must be paid in full.',
                ]);
            }

            $repair->final_charge = (string) $charge;
            $repair->status = 'delivered';
            $repair->delivered_at = now();
            $repair->save();

            if ($charge->isPositive()) {
                $this->ledger->post(
                    (int) $repair->company_id,
                    PartyType::Customer,
                    (int) $repair->customer_id,
                    LedgerDirection::Debit,
                    (string) $charge,
                    'Repair '.$repair->number,
                    $repair,
                    $userId,
                );
            }

            if ($paid->isPositive()) {
                $branch = $repair->branch;
                $voucher = $this->numbers->issue($this->numbers->for(DocumentType::Receipt, $branch));
                $payment = Payment::query()->create([
                    'company_id' => $repair->company_id,
                    'branch_id' => $repair->branch_id,
                    'repair_order_id' => $repair->id,
                    'customer_id' => $repair->customer_id,
                    'number' => $voucher->number,
                    'direction' => 'in',
                    'method' => PaymentMethod::from($attributes['method'] ?? PaymentMethod::Cash->value),
                    'amount' => (string) $paid,
                    'reference' => trim((string) ($attributes['reference'] ?? '')) ?: null,
                    'narration' => 'Repair '.$repair->number,
                    'received_at' => now(),
                    'user_id' => $userId,
                ]);
                $this->ledger->post(
                    (int) $repair->company_id,
                    PartyType::Customer,
                    (int) $repair->customer_id,
                    LedgerDirection::Credit,
                    (string) $paid,
                    'Receipt '.$payment->number,
                    $payment,
                    $userId,
                );
            }

            if ($repair->item) {
                $this->inventory->apply($repair->item, InventoryMovement::RepairIn, $repair, null, null, 'Repair delivered', $userId);
            }

            return $repair;
        });
    }
}
