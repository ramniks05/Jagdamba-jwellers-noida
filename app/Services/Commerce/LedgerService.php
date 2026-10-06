<?php

namespace App\Services\Commerce;

use App\Enums\LedgerDirection;
use App\Enums\PartyType;
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
}
