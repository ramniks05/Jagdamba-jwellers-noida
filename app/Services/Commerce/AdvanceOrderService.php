<?php

namespace App\Services\Commerce;

use App\Enums\DocumentType;
use App\Enums\LedgerDirection;
use App\Enums\PartyType;
use App\Enums\PaymentMethod;
use App\Models\AdvanceOrder;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\MetalType;
use App\Models\Payment;
use App\Models\Purity;
use App\Services\Foundation\DocumentNumberService;
use App\Services\Foundation\SettingService;
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdvanceOrderService
{
    public function __construct(
        private readonly CompanyContext $context,
        private readonly RateBook $rates,
        private readonly JewelleryPricer $pricer,
        private readonly LedgerService $ledger,
        private readonly DocumentNumberService $numbers,
        private readonly SettingService $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function book(Company $company, array $attributes, ?int $userId = null): AdvanceOrder
    {
        return DB::transaction(function () use ($company, $attributes, $userId) {
            $this->context->ensureId((int) $company->id);
            $customer = Customer::query()->where('uuid', $attributes['customer_uuid'])->where('is_active', true)->first();

            if (! $customer || $customer->is_system) {
                throw ValidationException::withMessages([
                    'customer_uuid' => 'Choose the customer. An order cannot be in the walk-in name.',
                ]);
            }

            $metal = MetalType::query()->where('uuid', $attributes['metal_uuid'])->first();
            $purity = Purity::query()->where('uuid', $attributes['purity_uuid'])->first();

            if (! $metal || ! $purity || (int) $purity->metal_type_id !== (int) $metal->id) {
                throw ValidationException::withMessages([
                    'purity_uuid' => 'Choose a purity that belongs to this metal.',
                ]);
            }

            $branch = Branch::query()->where('is_head_office', true)->first()
                ?? Branch::query()->orderBy('id')->first();
            $rate = $this->rates->current((int) $metal->id, (int) $purity->id, $branch?->id);

            if (! $rate) {
                throw ValidationException::withMessages([
                    'purity_uuid' => 'Enter today’s '.$metal->name.' '.$purity->name.' rate before booking the order.',
                ]);
            }

            $advance = BigDecimal::of((string) $attributes['advance'])->toScale(2, RoundingMode::HalfUp);

            if ($advance->isNegativeOrZero()) {
                throw ValidationException::withMessages([
                    'advance' => 'Take an advance to book the order.',
                ]);
            }

            $number = $this->numbers->issue($this->numbers->for(DocumentType::AdvanceOrder, $branch));
            $order = AdvanceOrder::query()->create([
                'company_id' => $company->id,
                'branch_id' => $branch?->id,
                'customer_id' => $customer->id,
                'metal_type_id' => $metal->id,
                'purity_id' => $purity->id,
                'metal_rate_id' => $rate->id,
                'number' => $number->number,
                'description' => trim((string) $attributes['description']),
                'design_notes' => trim((string) ($attributes['design_notes'] ?? '')) ?: null,
                'expected_weight' => $attributes['expected_weight'],
                'rate_per_gram' => $rate->rate_per_gram,
                'estimated_making' => $attributes['estimated_making'] ?? '0',
                'advance_paid' => '0',
                'advance_refunded' => '0',
                'status' => 'booked',
                'booked_at' => now(),
                'due_on' => $attributes['due_on'] ?? null,
                'notes' => trim((string) ($attributes['notes'] ?? '')) ?: null,
                'user_id' => $userId,
            ]);

            $this->receive($order, (string) $advance, (string) $attributes['method'], $attributes['reference'] ?? null, $userId);

            return $order->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function addAdvance(AdvanceOrder $order, array $attributes, ?int $userId = null): AdvanceOrder
    {
        return DB::transaction(function () use ($order, $attributes, $userId) {
            $this->context->ensureId((int) $order->company_id);
            $order = AdvanceOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->mustBeOpen($order, 'amount');
            $amount = BigDecimal::of((string) $attributes['amount'])->toScale(2, RoundingMode::HalfUp);

            if ($amount->isNegativeOrZero()) {
                throw ValidationException::withMessages([
                    'amount' => 'Enter the amount received.',
                ]);
            }

            $this->receive($order, (string) $amount, (string) $attributes['method'], $attributes['reference'] ?? null, $userId);

            return $order->refresh();
        });
    }

    public function markReady(AdvanceOrder $order): AdvanceOrder
    {
        $this->context->ensureId((int) $order->company_id);

        if ($order->status !== 'booked') {
            throw ValidationException::withMessages([
                'status' => 'Only a booked order can be marked ready.',
            ]);
        }

        $order->status = 'ready';
        $order->ready_at = now();
        $order->save();

        return $order;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function cancel(AdvanceOrder $order, array $attributes, ?int $userId = null): AdvanceOrder
    {
        return DB::transaction(function () use ($order, $attributes, $userId) {
            $this->context->ensureId((int) $order->company_id);
            $order = AdvanceOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->mustBeOpen($order, 'refund');
            $refund = BigDecimal::of((string) ($attributes['refund'] ?? '0'))->toScale(2, RoundingMode::HalfUp);

            if ($refund->isNegative()) {
                throw ValidationException::withMessages([
                    'refund' => 'The refund cannot be less than zero.',
                ]);
            }

            if ($refund->isGreaterThan($order->advanceHeld())) {
                throw ValidationException::withMessages([
                    'refund' => 'The refund cannot be more than the advance, '.$order->advanceHeld().'.',
                ]);
            }

            $credit = BigDecimal::of($this->ledger->balance(PartyType::Customer, (int) $order->customer_id))->negated();
            $credit = $credit->isNegative() ? BigDecimal::zero() : $credit;

            if ($refund->isGreaterThan($credit)) {
                throw ValidationException::withMessages([
                    'refund' => 'The customer owes money on other bills. Refund at most '.$credit->toScale(2).'.',
                ]);
            }

            if ($refund->isPositive()) {
                $voucher = $this->numbers->issue($this->numbers->for(DocumentType::Payment, $order->branch));
                $payment = Payment::query()->create([
                    'company_id' => $order->company_id,
                    'branch_id' => $order->branch_id,
                    'advance_order_id' => $order->id,
                    'customer_id' => $order->customer_id,
                    'number' => $voucher->number,
                    'direction' => 'out',
                    'method' => PaymentMethod::from((string) ($attributes['method'] ?? 'cash')),
                    'amount' => (string) $refund,
                    'reference' => trim((string) ($attributes['reference'] ?? '')) ?: null,
                    'narration' => 'Advance refund '.$order->number,
                    'received_at' => now(),
                    'user_id' => $userId,
                ]);
                $this->ledger->post(
                    (int) $order->company_id,
                    PartyType::Customer,
                    (int) $order->customer_id,
                    LedgerDirection::Debit,
                    (string) $refund,
                    'Advance refund '.$payment->number,
                    $payment,
                    $userId,
                );
            }

            $order->advance_refunded = (string) BigDecimal::of((string) $order->advance_refunded)->plus($refund)->toScale(2, RoundingMode::HalfUp);
            $order->status = 'cancelled';
            $order->cancelled_at = now();
            $order->save();

            return $order;
        });
    }

    /**
     * @return array{gold: string, making: string, mode: string, tax: string, making_tax: string, round_off: string, total: string, balance: string}
     */
    public function estimate(AdvanceOrder $order, Company $company): array
    {
        $gold = $order->goldEstimate();
        $making = (string) BigDecimal::of((string) $order->estimated_making)->toScale(2, RoundingMode::HalfUp);
        $mode = (string) ($this->settings->get('pricing.making_mode', $company) ?? 'inside');
        $bill = $this->pricer->billWithMaking(
            (string) BigDecimal::of($gold)->plus($making)->toScale(2, RoundingMode::HalfUp),
            $making,
            '0',
            (string) ($this->settings->get('pricing.gst_percent', $company) ?? '0'),
            $mode,
            (string) ($this->settings->get('pricing.making_gst_percent', $company) ?? '0'),
            $this->settings->get('invoice.tax_display', $company) !== 'inclusive',
            (bool) $this->settings->get('pricing.round_rupee', $company),
        );
        $balance = BigDecimal::of($bill->total)->minus($order->advanceHeld());

        return [
            'gold' => $gold,
            'making' => $making,
            'mode' => $mode,
            'tax' => (string) BigDecimal::of($bill->taxAmount)->minus($bill->makingTaxAmount),
            'making_tax' => $bill->makingTaxAmount,
            'round_off' => $bill->roundOff,
            'total' => $bill->total,
            'balance' => (string) $balance->toScale(2, RoundingMode::HalfUp),
        ];
    }

    private function receive(AdvanceOrder $order, string $amount, string $method, ?string $reference, ?int $userId): void
    {
        $receipt = $this->numbers->issue($this->numbers->for(DocumentType::Receipt, $order->branch));
        $payment = Payment::query()->create([
            'company_id' => $order->company_id,
            'branch_id' => $order->branch_id,
            'advance_order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'number' => $receipt->number,
            'direction' => 'in',
            'method' => PaymentMethod::from($method),
            'amount' => $amount,
            'reference' => trim((string) $reference) ?: null,
            'narration' => 'Advance for order '.$order->number,
            'received_at' => now(),
            'user_id' => $userId,
        ]);
        $this->ledger->post(
            (int) $order->company_id,
            PartyType::Customer,
            (int) $order->customer_id,
            LedgerDirection::Credit,
            $amount,
            'Receipt '.$payment->number,
            $payment,
            $userId,
        );
        $order->advance_paid = (string) BigDecimal::of((string) $order->advance_paid)->plus($amount)->toScale(2, RoundingMode::HalfUp);
        $order->save();
    }

    private function mustBeOpen(AdvanceOrder $order, string $field): void
    {
        if (! $order->isOpen()) {
            throw ValidationException::withMessages([
                $field => 'This order is already '.strtolower($order->statusLabel()).'.',
            ]);
        }
    }
}
