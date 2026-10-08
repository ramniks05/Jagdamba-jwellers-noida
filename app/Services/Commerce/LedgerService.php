<?php

namespace App\Services\Commerce;

use App\Enums\LedgerDirection;
use App\Enums\PartyType;
use App\Models\AdvanceOrder;
use App\Models\LedgerEntry;
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;

class LedgerService
{
    public function __construct(private readonly CompanyContext $context) {}

    public function post(
        int $companyId,
        PartyType $partyType,
        int $partyId,
        LedgerDirection $direction,
        string $amount,
        string $narration,
        ?Model $reference = null,
        ?int $userId = null,
    ): LedgerEntry {
        $this->context->ensureId($companyId);

        return LedgerEntry::query()->create([
            'company_id' => $companyId,
            'party_type' => $partyType,
            'party_id' => $partyId,
            'direction' => $direction,
            'amount' => $amount,
            'narration' => $narration,
            'reference_type' => $reference ? $reference->getMorphClass() : null,
            'reference_id' => $reference?->getKey(),
            'occurred_at' => now(),
            'user_id' => $userId,
        ]);
    }

    public function balance(PartyType $partyType, int $partyId): string
    {
        $rows = LedgerEntry::query()
            ->where('party_type', $partyType)
            ->where('party_id', $partyId)
            ->get(['direction', 'amount']);

        $balance = BigDecimal::zero();

        foreach ($rows as $row) {
            $amount = BigDecimal::of((string) $row->amount);
            $balance = $row->direction === LedgerDirection::Debit
                ? $balance->plus($amount)
                : $balance->minus($amount);
        }

        return (string) $balance->toScale(2, RoundingMode::HalfUp);
    }

    /**
     * Credit a customer can spend on a bill. Advances held for open orders stay with those orders.
     *
     * @param  array<int, int>  $customerIds
     * @return array<int, string>
     */
    public function spendableCredits(array $customerIds): array
    {
        $credits = [];

        if ($customerIds === []) {
            return $credits;
        }

        $balances = [];

        foreach (LedgerEntry::query()->where('party_type', PartyType::Customer)->whereIn('party_id', $customerIds)->get(['party_id', 'direction', 'amount']) as $row) {
            $amount = BigDecimal::of((string) $row->amount);
            $balances[$row->party_id] = ($balances[$row->party_id] ?? BigDecimal::zero())
                ->plus($row->direction === LedgerDirection::Credit ? $amount : $amount->negated());
        }

        foreach (AdvanceOrder::query()->whereIn('customer_id', array_keys($balances))->whereIn('status', AdvanceOrder::OPEN)->get(['customer_id', 'advance_paid', 'advance_refunded']) as $order) {
            $balances[$order->customer_id] = $balances[$order->customer_id]->minus($order->advanceHeld());
        }

        foreach ($balances as $customerId => $balance) {
            if ($balance->isPositive()) {
                $credits[$customerId] = (string) $balance->toScale(2, RoundingMode::HalfUp);
            }
        }

        return $credits;
    }

    public function spendableCredit(int $customerId): string
    {
        return $this->spendableCredits([$customerId])[$customerId] ?? '0.00';
    }
}
