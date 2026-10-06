<?php

namespace App\Services\Commerce;

use App\Enums\DocumentType;
use App\Enums\LedgerDirection;
use App\Enums\PartyType;
use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Supplier;
use App\Services\Foundation\DocumentNumberService;
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private readonly CompanyContext $context,
        private readonly LedgerService $ledger,
        private readonly DocumentNumberService $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function receive(Customer $customer, array $attributes, ?int $userId = null): Payment
    {
        return DB::transaction(function () use ($customer, $attributes, $userId) {
            $this->context->ensureId((int) $customer->company_id);

            if ($customer->is_system) {
                throw ValidationException::withMessages([
                    'amount' => 'Walk-in bills are settled on the invoice.',
                ]);
            }

            $amount = BigDecimal::of((string) $attributes['amount'])->toScale(2, RoundingMode::HalfUp);
            $balance = BigDecimal::of($this->ledger->balance(PartyType::Customer, (int) $customer->id));

            if ($amount->isNegativeOrZero()) {
                throw ValidationException::withMessages([
                    'amount' => 'Enter an amount more than zero.',
                ]);
            }

            if ($amount->isGreaterThan($balance)) {
                throw ValidationException::withMessages([
                    'amount' => 'The amount is more than the outstanding balance.',
                ]);
            }

            $branch = Branch::query()->where('is_head_office', true)->first()
                ?? Branch::query()->orderBy('id')->first();
            $receipt = $this->numbers->issue($this->numbers->for(DocumentType::Receipt, $branch));
            $payment = Payment::query()->create([
                'company_id' => $customer->company_id,
                'branch_id' => $branch?->id,
                'customer_id' => $customer->id,
                'number' => $receipt->number,
                'direction' => 'in',
                'method' => PaymentMethod::from($attributes['method']),
                'amount' => (string) $amount,
                'reference' => trim((string) ($attributes['reference'] ?? '')) ?: null,
                'narration' => 'Received on account',
                'received_at' => now(),
                'user_id' => $userId,
            ]);

            $this->ledger->post(
                (int) $customer->company_id,
                PartyType::Customer,
                (int) $customer->id,
                LedgerDirection::Credit,
                (string) $amount,
                'Receipt '.$payment->number,
                $payment,
                $userId,
            );

            return $payment;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function paySupplier(Supplier $supplier, array $attributes, ?int $userId = null): Payment
    {
        return DB::transaction(function () use ($supplier, $attributes, $userId) {
            $this->context->ensureId((int) $supplier->company_id);
            $amount = BigDecimal::of((string) $attributes['amount'])->toScale(2, RoundingMode::HalfUp);
            $payable = BigDecimal::of($this->ledger->balance(PartyType::Supplier, (int) $supplier->id))->negated();

            if ($amount->isNegativeOrZero()) {
                throw ValidationException::withMessages([
                    'amount' => 'Enter an amount more than zero.',
                ]);
            }

            if ($amount->isGreaterThan($payable)) {
                throw ValidationException::withMessages([
                    'amount' => 'The amount is more than the amount payable.',
                ]);
            }

            $branch = Branch::query()->where('is_head_office', true)->first()
                ?? Branch::query()->orderBy('id')->first();
            $voucher = $this->numbers->issue($this->numbers->for(DocumentType::Payment, $branch));
            $payment = Payment::query()->create([
                'company_id' => $supplier->company_id,
                'branch_id' => $branch?->id,
                'supplier_id' => $supplier->id,
                'number' => $voucher->number,
                'direction' => 'out',
                'method' => PaymentMethod::from($attributes['method']),
                'amount' => (string) $amount,
                'reference' => trim((string) ($attributes['reference'] ?? '')) ?: null,
                'narration' => 'Paid to supplier',
                'received_at' => now(),
                'user_id' => $userId,
            ]);
            $this->ledger->post(
                (int) $supplier->company_id,
                PartyType::Supplier,
                (int) $supplier->id,
                LedgerDirection::Debit,
                (string) $amount,
                'Payment '.$payment->number,
                $payment,
                $userId,
            );

            return $payment;
        });
    }
}
