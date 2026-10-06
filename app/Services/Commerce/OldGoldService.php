<?php

namespace App\Services\Commerce;

use App\Enums\DocumentType;
use App\Enums\LedgerDirection;
use App\Enums\PartyType;
use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\MetalType;
use App\Models\OldGoldExchange;
use App\Models\Payment;
use App\Models\Purity;
use App\Services\Foundation\DocumentNumberService;
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OldGoldService
{
    public function __construct(
        private readonly CompanyContext $context,
        private readonly OldGoldPricer $pricer,
        private readonly LedgerService $ledger,
        private readonly DocumentNumberService $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function exchange(Company $company, array $attributes, ?int $userId = null): OldGoldExchange
    {
        return DB::transaction(function () use ($company, $attributes, $userId) {
            $this->context->ensureId((int) $company->id);
            $customer = Customer::query()->where('uuid', $attributes['customer_uuid'])->where('is_active', true)->first();

            if (! $customer) {
                throw ValidationException::withMessages([
                    'customer_uuid' => 'Choose a customer.',
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
            $valued = $this->pricer->value(
                (string) $attributes['gross_weight'],
                (string) ($attributes['stone_weight'] ?? '0'),
                (string) ($attributes['melting_loss_percent'] ?? '0'),
                (string) $attributes['rate_per_gram'],
                (string) ($attributes['deduction_amount'] ?? '0'),
            );
            $number = $this->numbers->issue($this->numbers->for(DocumentType::OldGold, $branch));
            $exchange = OldGoldExchange::query()->create([
                'company_id' => $company->id,
                'branch_id' => $branch?->id,
                'customer_id' => $customer->id,
                'metal_type_id' => $metal->id,
                'purity_id' => $purity->id,
                'number' => $number->number,
                'exchanged_at' => now(),
                'gross_weight' => $attributes['gross_weight'],
                'stone_weight' => $attributes['stone_weight'] ?? '0',
                'net_weight' => $valued['net_weight'],
                'melted_weight' => $valued['melted_weight'],
                'melting_loss_percent' => $attributes['melting_loss_percent'] ?? '0',
                'rate_per_gram' => $attributes['rate_per_gram'],
                'deduction_amount' => $attributes['deduction_amount'] ?? '0',
                'exchange_value' => $valued['exchange_value'],
                'testing_result' => trim((string) ($attributes['testing_result'] ?? '')) ?: null,
                'notes' => trim((string) ($attributes['notes'] ?? '')) ?: null,
                'user_id' => $userId,
            ]);
            $this->ledger->post(
                (int) $company->id,
                PartyType::Customer,
                (int) $customer->id,
                LedgerDirection::Credit,
                $valued['exchange_value'],
                'Old gold '.$exchange->number,
                $exchange,
                $userId,
            );

            $refund = BigDecimal::of(trim((string) ($attributes['refund'] ?? '')) === '' ? '0' : (string) $attributes['refund'])->toScale(2, RoundingMode::HalfUp);
            $balance = BigDecimal::of($this->ledger->balance(PartyType::Customer, (int) $customer->id));
            $refundable = $balance->isNegative() ? $balance->negated()->toScale(2, RoundingMode::HalfUp) : BigDecimal::zero()->toScale(2);

            if ($customer->is_system && ! $refund->isEqualTo($refundable)) {
                throw ValidationException::withMessages([
                    'refund' => 'A walk-in exchange must be refunded in full, '.$refundable.'.',
                ]);
            }

            if ($refund->isGreaterThan($refundable)) {
                throw ValidationException::withMessages([
                    'refund' => 'The refund cannot be more than the credit on this account, '.$refundable.'.',
                ]);
            }

            if ($refund->isPositive()) {
                $voucher = $this->numbers->issue($this->numbers->for(DocumentType::Payment, $branch));
                $payment = Payment::query()->create([
                    'company_id' => $company->id,
                    'branch_id' => $branch?->id,
                    'old_gold_exchange_id' => $exchange->id,
                    'customer_id' => $customer->id,
                    'number' => $voucher->number,
                    'direction' => 'out',
                    'method' => PaymentMethod::Cash,
                    'amount' => (string) $refund,
                    'narration' => 'Old gold refund '.$exchange->number,
                    'received_at' => now(),
                    'user_id' => $userId,
                ]);
                $this->ledger->post(
                    (int) $company->id,
                    PartyType::Customer,
                    (int) $customer->id,
                    LedgerDirection::Debit,
                    (string) $refund,
                    'Refund '.$payment->number,
                    $payment,
                    $userId,
                );
            }

            return $exchange;
        });
    }
}
