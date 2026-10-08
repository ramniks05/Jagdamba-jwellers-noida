<?php

namespace App\Services\Commerce;

use App\Enums\DocumentType;
use App\Enums\LedgerDirection;
use App\Enums\PartyType;
use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
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
    public function receiveForSale(Sale $sale, array $attributes, ?int $userId = null): Payment
    {
        return DB::transaction(function () use ($sale, $attributes, $userId) {
            $this->context->ensureId((int) $sale->company_id);
            $locked = Sale::query()->whereKey($sale->getKey())->lockForUpdate()->firstOrFail();
            $amount = BigDecimal::of((string) $attributes['amount'])->toScale(2, RoundingMode::HalfUp);
            $due = BigDecimal::of($locked->balanceDue());

            if ($amount->isNegativeOrZero()) {
                throw ValidationException::withMessages([
                    'amount' => 'Enter an amount more than zero.',
                ]);
            }

            if ($due->isNegativeOrZero()) {
                throw ValidationException::withMessages([
                    'amount' => 'This invoice is already fully paid.',
                ]);
            }

            if ($amount->isGreaterThan($due)) {
                throw ValidationException::withMessages([
                    'amount' => 'The amount is more than the balance due of '.$due.'.',
                ]);
            }

            $receipt = $this->numbers->issue($this->numbers->for(DocumentType::Receipt, $locked->branch));
            $payment = Payment::query()->create([
                'company_id' => $locked->company_id,
                'branch_id' => $locked->branch_id,
                'sale_id' => $locked->id,
                'customer_id' => $locked->customer_id,
                'number' => $receipt->number,
                'direction' => 'in',
                'method' => PaymentMethod::from($attributes['method']),
                'amount' => (string) $amount,
                'reference' => trim((string) ($attributes['reference'] ?? '')) ?: null,
                'narration' => 'Received against '.$locked->number,
                'received_at' => now(),
                'user_id' => $userId,
            ]);

            $this->ledger->post(
                (int) $locked->company_id,
                PartyType::Customer,
                (int) $locked->customer_id,
                LedgerDirection::Credit,
                (string) $amount,
                'Receipt '.$payment->number,
                $payment,
                $userId,
            );

            $locked->paid_amount = (string) BigDecimal::of((string) $locked->paid_amount)->plus($amount)->toScale(2, RoundingMode::HalfUp);
            $locked->save();

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
            $this->settlePurchases($supplier, $amount);

            return $payment;
        });
    }

    /**
     * A payment made from the supplier page clears that supplier's oldest purchases first.
     */
    private function settlePurchases(Supplier $supplier, BigDecimal $amount): void
    {
        $left = $amount;
        $purchases = Purchase::query()
            ->where('supplier_id', $supplier->id)
            ->withSum('returns', 'amount')
            ->orderBy('purchased_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($purchases as $purchase) {
            if (! $left->isPositive()) {
                break;
            }

            $due = BigDecimal::of($purchase->dueAmount());

            if (! $due->isPositive()) {
                continue;
            }

            $part = $due->isLessThan($left) ? $due : $left;
            Purchase::query()->whereKey($purchase->id)->update([
                'paid_amount' => (string) BigDecimal::of((string) $purchase->paid_amount)->plus($part)->toScale(2, RoundingMode::HalfUp),
            ]);
            $left = $left->minus($part);
        }
    }
}
