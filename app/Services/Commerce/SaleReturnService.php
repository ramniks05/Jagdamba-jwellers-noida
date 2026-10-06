<?php

namespace App\Services\Commerce;

use App\Enums\DocumentType;
use App\Enums\InventoryMovement;
use App\Enums\LedgerDirection;
use App\Enums\PartyType;
use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SaleReturn;
use App\Models\SaleReturnLine;
use App\Services\Foundation\DocumentNumberService;
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleReturnService
{
    public function __construct(
        private readonly CompanyContext $context,
        private readonly InventoryService $inventory,
        private readonly LedgerService $ledger,
        private readonly DocumentNumberService $numbers,
    ) {}

    /**
     * @param  array<int, string>  $lineUuids
     */
    public function post(Sale $sale, array $lineUuids, string $refund, ?int $userId = null): SaleReturn
    {
        return DB::transaction(function () use ($sale, $lineUuids, $refund, $userId) {
            $this->context->ensureId((int) $sale->company_id);
            $sale->load(['lines.item', 'customer', 'branch']);
            $returned = SaleReturnLine::query()->whereIn('sale_line_id', $sale->lines->modelKeys())->pluck('sale_line_id');
            $lines = $sale->lines->filter(fn (SaleLine $line) => in_array($line->uuid, $lineUuids, true));

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => 'Choose at least one piece to return.',
                ]);
            }

            if ($lines->contains(fn (SaleLine $line) => $returned->contains($line->id))) {
                throw ValidationException::withMessages([
                    'lines' => 'One of these pieces has already been returned.',
                ]);
            }

            $amounts = $this->amounts($sale, $lines->values()->all());
            $total = array_reduce($amounts, fn (BigDecimal $sum, string $amount) => $sum->plus($amount), BigDecimal::zero());
            $number = $this->numbers->issue($this->numbers->for(DocumentType::CreditNote, $sale->branch));
            $document = SaleReturn::query()->create([
                'company_id' => $sale->company_id,
                'branch_id' => $sale->branch_id,
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'number' => $number->number,
                'amount' => (string) $total->toScale(2, RoundingMode::HalfUp),
                'refund_amount' => '0.00',
                'returned_at' => now(),
                'user_id' => $userId,
            ]);

            foreach ($lines as $line) {
                $this->inventory->apply($line->item, InventoryMovement::SaleReturn, $document, null, $amounts[$line->id], 'Returned from sale', $userId);
                SaleReturnLine::query()->create([
                    'company_id' => $sale->company_id,
                    'sale_return_id' => $document->id,
                    'sale_line_id' => $line->id,
                    'item_id' => $line->item_id,
                    'amount' => $amounts[$line->id],
                ]);
            }

            $this->ledger->post(
                (int) $sale->company_id,
                PartyType::Customer,
                (int) $sale->customer_id,
                LedgerDirection::Credit,
                (string) $total->toScale(2, RoundingMode::HalfUp),
                'Credit note '.$document->number,
                $document,
                $userId,
            );

            $refundable = $this->refundable((int) $sale->customer_id);
            $cash = BigDecimal::of(trim($refund) === '' ? '0' : $refund)->toScale(2, RoundingMode::HalfUp);

            if ($sale->customer->is_system && ! $cash->isEqualTo($refundable)) {
                throw ValidationException::withMessages([
                    'refund' => 'A walk-in return must be refunded in full, '.$refundable.'.',
                ]);
            }

            if ($cash->isGreaterThan($refundable)) {
                throw ValidationException::withMessages([
                    'refund' => 'The refund cannot be more than the cash the customer has paid, '.$refundable.'.',
                ]);
            }

            if ($cash->isPositive()) {
                $voucher = $this->numbers->issue($this->numbers->for(DocumentType::Payment, $sale->branch));
                $payment = Payment::query()->create([
                    'company_id' => $sale->company_id,
                    'branch_id' => $sale->branch_id,
                    'sale_return_id' => $document->id,
                    'customer_id' => $sale->customer_id,
                    'number' => $voucher->number,
                    'direction' => 'out',
                    'method' => PaymentMethod::Cash,
                    'amount' => (string) $cash,
                    'narration' => 'Refund '.$document->number,
                    'received_at' => now(),
                    'user_id' => $userId,
                ]);
                $this->ledger->post(
                    (int) $sale->company_id,
                    PartyType::Customer,
                    (int) $sale->customer_id,
                    LedgerDirection::Debit,
                    (string) $cash,
                    'Refund '.$payment->number,
                    $payment,
                    $userId,
                );
                $document->refund_amount = (string) $cash;
                $document->save();
            }

            return $document;
        });
    }

    /**
     * @param  array<int, SaleLine>  $lines
     * @return array<int, string>
     */
    private function amounts(Sale $sale, array $lines): array
    {
        $base = BigDecimal::of((string) $sale->lines_amount);
        $remaining = BigDecimal::of((string) $sale->total);
        $last = array_key_last($lines);
        $amounts = [];

        foreach ($lines as $index => $line) {
            if ($index === $last || $base->isZero()) {
                $share = $remaining;
            } else {
                $share = BigDecimal::of((string) $sale->total)
                    ->multipliedBy((string) $line->line_amount)
                    ->dividedBy($base, 2, RoundingMode::HalfUp);
                $remaining = $remaining->minus($share);
            }

            $amounts[$line->id] = (string) $share->toScale(2, RoundingMode::HalfUp);
        }

        return $amounts;
    }

    private function refundable(int $customerId): string
    {
        $balance = BigDecimal::of($this->ledger->balance(PartyType::Customer, $customerId));

        if ($balance->isNegative()) {
            return (string) $balance->negated()->toScale(2, RoundingMode::HalfUp);
        }

        return '0.00';
    }
}
