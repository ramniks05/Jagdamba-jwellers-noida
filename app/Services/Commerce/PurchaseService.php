<?php

namespace App\Services\Commerce;

use App\Enums\DocumentType;
use App\Enums\InventoryMovement;
use App\Enums\ItemStatus;
use App\Enums\LedgerDirection;
use App\Enums\PartyType;
use App\Enums\PaymentMethod;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnLine;
use App\Models\StockLocation;
use App\Models\Supplier;
use App\Services\Foundation\DocumentNumberService;
use App\Services\Foundation\SettingService;
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(
        private readonly CompanyContext $context,
        private readonly ItemService $items,
        private readonly JewelleryPricer $pricer,
        private readonly InventoryService $inventory,
        private readonly LedgerService $ledger,
        private readonly DocumentNumberService $numbers,
        private readonly SettingService $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function receive(Company $company, array $attributes, ?int $userId = null): Purchase
    {
        return DB::transaction(function () use ($company, $attributes, $userId) {
            $this->context->ensureId((int) $company->id);
            $supplier = Supplier::query()->where('uuid', $attributes['supplier_uuid'])->where('is_active', true)->first();

            if (! $supplier) {
                throw ValidationException::withMessages([
                    'supplier_uuid' => 'Choose a supplier.',
                ]);
            }

            $location = StockLocation::query()->where('uuid', $attributes['location_uuid'] ?? '')->where('is_active', true)->first();

            if (! $location) {
                throw ValidationException::withMessages([
                    'location_uuid' => 'Choose where the pieces are kept.',
                ]);
            }

            return $this->post($company, $supplier, $location, $attributes, $userId);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function post(Company $company, Supplier $supplier, StockLocation $location, array $attributes, ?int $userId): Purchase
    {
        $byAmount = ($attributes['pricing'] ?? 'rate') === 'amount';
        $rows = [];
        $sum = BigDecimal::zero();

        foreach (array_values($attributes['lines'] ?? []) as $index => $row) {
            $net = BigDecimal::of((string) $row['gross_weight'])
                ->minus((string) ($row['stone_weight'] ?? '0'))
                ->minus((string) ($row['other_weight'] ?? '0'));

            if ($net->isLessThanOrEqualTo(0)) {
                throw ValidationException::withMessages([
                    'lines.'.$index.'.gross_weight' => 'Piece '.($index + 1).': the gross weight must be more than the stone and other weight.',
                ]);
            }

            $netWeight = (string) $net->toScale(3, RoundingMode::HalfUp);

            if ($byAmount) {
                $amount = BigDecimal::of((string) $row['amount'])->toScale(2, RoundingMode::HalfUp);
                $rate = (string) $amount->dividedBy($netWeight, 2, RoundingMode::HalfUp);
                $wastage = '0';
                $labour = '0';
                $stone = '0.00';
            } else {
                $rate = (string) BigDecimal::of((string) $row['rate_per_gram'])->toScale(2, RoundingMode::HalfUp);
                $wastage = (string) ($row['wastage_percent'] ?? '0');
                $labour = (string) ($row['labour_per_gram'] ?? '0');
                $priced = $this->pricer->line($netWeight, $rate, 'percentage', $wastage, 'per_gram', $labour, (string) ($row['stone_value'] ?? '0'));
                $amount = BigDecimal::of($priced['line_amount']);
                $stone = $priced['stone_amount'];
            }

            $rows[] = [
                'input' => $row,
                'net' => $netWeight,
                'rate' => $rate,
                'wastage' => $wastage,
                'labour' => $labour,
                'stone' => $stone,
                'amount' => (string) $amount->toScale(2, RoundingMode::HalfUp),
            ];
            $sum = $sum->plus($amount);
        }

        if ($rows === []) {
            throw ValidationException::withMessages([
                'lines' => 'Add at least one piece.',
            ]);
        }

        $taxPercent = (string) ($attributes['gst_percent'] ?? $this->settings->get('pricing.gst_percent', $company) ?? '0');
        $bill = $this->pricer->bill(
            (string) $sum->toScale(2, RoundingMode::HalfUp),
            (string) ($attributes['discount'] ?? '0'),
            $taxPercent,
            true,
            (bool) $this->settings->get('pricing.round_rupee', $company),
        );
        $paid = $this->paid($attributes['payments'] ?? []);

        if ($paid->isGreaterThan($bill->total)) {
            throw ValidationException::withMessages([
                'payments' => 'Payments are more than the purchase total.',
            ]);
        }

        $number = $this->numbers->issue($this->numbers->for(DocumentType::Purchase, $location->branch));
        $purchase = Purchase::query()->create([
            'company_id' => $company->id,
            'branch_id' => $location->branch_id,
            'supplier_id' => $supplier->id,
            'number' => $number->number,
            'supplier_bill_number' => trim((string) ($attributes['supplier_bill_number'] ?? '')) ?: null,
            'purchased_at' => $this->purchasedAt($attributes['purchased_on'] ?? null),
            'lines_amount' => $bill->linesAmount,
            'discount_amount' => $bill->discountAmount,
            'taxable_amount' => $bill->taxableAmount,
            'tax_percent' => $taxPercent,
            'tax_amount' => $bill->taxAmount,
            'round_off' => $bill->roundOff,
            'total' => $bill->total,
            'paid_amount' => (string) $paid->toScale(2, RoundingMode::HalfUp),
            'notes' => trim((string) ($attributes['notes'] ?? '')) ?: null,
            'user_id' => $userId,
        ]);

        $left = BigDecimal::of($bill->total);
        $lastIndex = count($rows) - 1;

        foreach ($rows as $index => $row) {
            $cost = $index === $lastIndex
                ? $left
                : BigDecimal::of($bill->total)->multipliedBy($row['amount'])->dividedBy($sum, 2, RoundingMode::HalfUp);
            $left = $left->minus($cost);
            $input = $row['input'];
            $item = $this->items->create($company, [
                'name' => $input['name'],
                'item_code' => $input['item_code'],
                'sku' => $input['item_code'],
                'category_uuid' => $input['category_uuid'] ?? null,
                'metal_uuid' => $input['metal_uuid'],
                'purity_uuid' => $input['purity_uuid'],
                'location_uuid' => $location->uuid,
                'gross_weight' => $input['gross_weight'],
                'stone_weight' => $input['stone_weight'] ?? '0',
                'other_weight' => $input['other_weight'] ?? '0',
                'stone_value' => $input['stone_value'] ?? '0',
                'making_method_uuid' => $input['making_method_uuid'] ?? null,
                'making_value' => $input['making_value'] ?? '0',
                'wastage_method_uuid' => $input['wastage_method_uuid'] ?? null,
                'wastage_value' => $input['wastage_value'] ?? '0',
                'huid' => $input['huid'] ?? null,
                'cost_price' => (string) $cost->toScale(2, RoundingMode::HalfUp),
            ], $userId, InventoryMovement::Purchase, $purchase);

            PurchaseLine::query()->create([
                'company_id' => $company->id,
                'purchase_id' => $purchase->id,
                'item_id' => $item->id,
                'name' => $item->name,
                'item_code' => $item->item_code,
                'gross_weight' => $item->gross_weight,
                'net_weight' => $item->net_weight,
                'rate_per_gram' => $row['rate'],
                'wastage_percent' => $row['wastage'],
                'labour_per_gram' => $row['labour'],
                'stone_amount' => $row['stone'],
                'line_amount' => $row['amount'],
                'cost_amount' => (string) $cost->toScale(2, RoundingMode::HalfUp),
            ]);
        }

        $this->ledger->post((int) $company->id, PartyType::Supplier, (int) $supplier->id, LedgerDirection::Credit, $bill->total, 'Purchase '.$purchase->number, $purchase, $userId);

        foreach ($attributes['payments'] ?? [] as $row) {
            if (trim((string) ($row['amount'] ?? '')) === '') {
                continue;
            }

            $this->recordPayment($purchase, $supplier, $row, $userId);
        }

        return $purchase->load(['lines.item', 'supplier']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function pay(Purchase $purchase, array $attributes, ?int $userId = null): Payment
    {
        return DB::transaction(function () use ($purchase, $attributes, $userId) {
            $this->context->ensureId((int) $purchase->company_id);
            $locked = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            $amount = BigDecimal::of((string) $attributes['amount'])->toScale(2, RoundingMode::HalfUp);

            if ($amount->isGreaterThan($locked->dueAmount())) {
                throw ValidationException::withMessages([
                    'amount' => 'The amount is more than the '.$locked->dueAmount().' still due on this purchase.',
                ]);
            }

            $payment = $this->recordPayment($locked, $locked->supplier()->firstOrFail(), $attributes, $userId);
            $locked->paid_amount = (string) BigDecimal::of((string) $locked->paid_amount)->plus($amount)->toScale(2, RoundingMode::HalfUp);
            $locked->save();

            return $payment;
        });
    }

    /**
     * @param  list<string>  $lineUuids
     */
    public function sendBack(Purchase $purchase, array $lineUuids = [], ?int $userId = null): PurchaseReturn
    {
        return DB::transaction(function () use ($purchase, $lineUuids, $userId) {
            $this->context->ensureId((int) $purchase->company_id);
            $purchase->load('lines.item');
            $returnedIds = PurchaseReturnLine::query()->whereIn('purchase_line_id', $purchase->lines->modelKeys())->pluck('purchase_line_id');
            $lines = $purchase->lines
                ->reject(fn (PurchaseLine $line) => $returnedIds->contains($line->id))
                ->when($lineUuids !== [], fn ($lines) => $lines->filter(fn (PurchaseLine $line) => in_array($line->uuid, $lineUuids, true)));

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => $lineUuids === [] ? 'Every piece on this purchase has already gone back.' : 'Tick the pieces that go back to the supplier.',
                ]);
            }

            foreach ($lines as $line) {
                if ($line->item?->status !== ItemStatus::Available) {
                    throw ValidationException::withMessages([
                        'lines' => $line->item_code.' is '.strtolower((string) $line->item?->status?->label()).', so it cannot go back to the supplier.',
                    ]);
                }
            }

            $amounts = $lines->mapWithKeys(fn (PurchaseLine $line) => [
                $line->id => (string) BigDecimal::of((string) ((float) $line->cost_amount > 0 ? $line->cost_amount : $line->line_amount))->toScale(2, RoundingMode::HalfUp),
            ]);
            $amount = (string) $amounts->reduce(fn (BigDecimal $sum, string $value) => $sum->plus($value), BigDecimal::zero())->toScale(2, RoundingMode::HalfUp);
            $number = $this->numbers->issue($this->numbers->for(DocumentType::PurchaseReturn, $purchase->branch));
            $document = PurchaseReturn::query()->create([
                'company_id' => $purchase->company_id,
                'purchase_id' => $purchase->id,
                'supplier_id' => $purchase->supplier_id,
                'number' => $number->number,
                'amount' => $amount,
                'returned_at' => now(),
                'user_id' => $userId,
            ]);

            foreach ($lines as $line) {
                $this->inventory->apply($line->item, InventoryMovement::PurchaseReturn, $document, null, $amounts[$line->id], 'Returned to supplier', $userId);
                PurchaseReturnLine::query()->create([
                    'company_id' => $purchase->company_id,
                    'purchase_return_id' => $document->id,
                    'purchase_line_id' => $line->id,
                    'item_id' => $line->item_id,
                    'amount' => $amounts[$line->id],
                ]);
            }

            $this->ledger->post(
                (int) $purchase->company_id,
                PartyType::Supplier,
                (int) $purchase->supplier_id,
                LedgerDirection::Debit,
                $amount,
                'Purchase return '.$document->number,
                $document,
                $userId,
            );

            return $document;
        });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function recordPayment(Purchase $purchase, Supplier $supplier, array $row, ?int $userId): Payment
    {
        $amount = (string) BigDecimal::of((string) $row['amount'])->toScale(2, RoundingMode::HalfUp);
        $voucher = $this->numbers->issue($this->numbers->for(DocumentType::Payment, $purchase->branch));
        $payment = Payment::query()->create([
            'company_id' => $purchase->company_id,
            'branch_id' => $purchase->branch_id,
            'purchase_id' => $purchase->id,
            'supplier_id' => $supplier->id,
            'number' => $voucher->number,
            'direction' => 'out',
            'method' => PaymentMethod::from($row['method']),
            'amount' => $amount,
            'reference' => trim((string) ($row['reference'] ?? '')) ?: null,
            'narration' => 'Paid against '.$purchase->number,
            'received_at' => now(),
            'user_id' => $userId,
        ]);
        $this->ledger->post((int) $purchase->company_id, PartyType::Supplier, (int) $supplier->id, LedgerDirection::Debit, $amount, 'Payment '.$payment->number, $payment, $userId);

        return $payment;
    }

    private function purchasedAt(mixed $date): Carbon
    {
        if (! is_string($date) || trim($date) === '') {
            return now();
        }

        $day = Carbon::parse($date, config('app.timezone'));

        return $day->isToday() ? now() : $day->setTime(12, 0);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function paid(array $rows): BigDecimal
    {
        $paid = BigDecimal::zero();

        foreach ($rows as $row) {
            if (trim((string) ($row['amount'] ?? '')) === '') {
                continue;
            }

            $paid = $paid->plus((string) $row['amount']);
        }

        return $paid->toScale(2, RoundingMode::HalfUp);
    }
}
