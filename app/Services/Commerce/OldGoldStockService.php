<?php

namespace App\Services\Commerce;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Item;
use App\Models\MetalType;
use App\Models\OldGoldExchange;
use App\Models\OldGoldMovement;
use App\Models\Purity;
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OldGoldStockService
{
    public function __construct(private readonly CompanyContext $context) {}

    public function receive(OldGoldExchange $exchange, ?int $userId = null): OldGoldMovement
    {
        return OldGoldMovement::query()->create([
            'company_id' => $exchange->company_id,
            'branch_id' => $exchange->branch_id,
            'metal_type_id' => $exchange->metal_type_id,
            'purity_id' => $exchange->purity_id,
            'direction' => 'in',
            'kind' => 'exchange',
            'gross_weight' => $exchange->gross_weight,
            'fine_weight' => $exchange->melted_weight,
            'value' => $exchange->exchange_value,
            'old_gold_exchange_id' => $exchange->id,
            'moved_at' => $exchange->exchanged_at ?? now(),
            'user_id' => $userId,
        ]);
    }

    /**
     * @return Collection<int, array{metal: MetalType, purity: Purity, gross: string, fine: string, value: string}>
     */
    public function balances(): Collection
    {
        $rows = OldGoldMovement::query()
            ->select(['metal_type_id', 'purity_id', 'direction'])
            ->selectRaw('sum(gross_weight) as gross, sum(fine_weight) as fine, sum(value) as value')
            ->groupBy(['metal_type_id', 'purity_id', 'direction'])
            ->get();
        $metals = MetalType::query()->whereIn('id', $rows->pluck('metal_type_id'))->get()->keyBy('id');
        $purities = Purity::query()->whereIn('id', $rows->pluck('purity_id'))->get()->keyBy('id');

        return $rows->groupBy(fn ($row) => $row->metal_type_id.'-'.$row->purity_id)
            ->map(function (Collection $group) use ($metals, $purities) {
                $sum = fn (string $field) => $group->reduce(
                    fn (BigDecimal $total, $row) => $row->direction === 'in'
                        ? $total->plus((string) $row->{$field})
                        : $total->minus((string) $row->{$field}),
                    BigDecimal::zero(),
                );
                $first = $group->first();

                return [
                    'metal' => $metals[$first->metal_type_id],
                    'purity' => $purities[$first->purity_id],
                    'gross' => (string) $sum('gross')->toScale(3, RoundingMode::HalfUp),
                    'fine' => (string) $sum('fine')->toScale(3, RoundingMode::HalfUp),
                    'value' => (string) $sum('value')->toScale(2, RoundingMode::HalfUp),
                ];
            })
            ->filter(fn (array $row) => (float) $row['gross'] > 0 || (float) $row['fine'] > 0)
            ->sortBy([
                fn (array $a, array $b) => strcmp($a['metal']->name, $b['metal']->name),
                fn (array $a, array $b) => (float) $b['purity']->fineness <=> (float) $a['purity']->fineness,
            ])
            ->values();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function send(Company $company, array $attributes, ?int $userId = null): OldGoldMovement
    {
        return DB::transaction(function () use ($company, $attributes, $userId) {
            $this->context->ensureId((int) $company->id);
            $purity = Purity::query()->where('uuid', $attributes['purity_uuid'])->first();
            $metal = $purity?->metalType;

            if (! $purity || ! $metal) {
                throw ValidationException::withMessages(['purity_uuid' => 'Choose which old gold to send.']);
            }

            $gross = BigDecimal::of((string) $attributes['gross_weight'])->toScale(3, RoundingMode::HalfUp);
            $fine = BigDecimal::of((string) $attributes['fine_weight'])->toScale(3, RoundingMode::HalfUp);
            $out = $this->takeOut((int) $metal->id, (int) $purity->id, $gross, $fine);
            $received = trim((string) ($attributes['amount_received'] ?? ''));
            $notes = trim((string) ($attributes['notes'] ?? ''));

            if (! $out['fine']->isEqualTo($fine)) {
                $notes = trim('Tested fine '.$fine.' g. '.$notes);
            }

            return OldGoldMovement::query()->create([
                'company_id' => $company->id,
                'branch_id' => Branch::query()->where('is_head_office', true)->value('id'),
                'metal_type_id' => $metal->id,
                'purity_id' => $purity->id,
                'direction' => 'out',
                'kind' => $attributes['kind'],
                'gross_weight' => (string) $gross,
                'fine_weight' => (string) $out['fine'],
                'value' => $out['value'],
                'amount_received' => $received === '' ? null : (string) BigDecimal::of($received)->toScale(2, RoundingMode::HalfUp),
                'party' => trim((string) ($attributes['party'] ?? '')) ?: null,
                'notes' => $notes ?: null,
                'moved_at' => now(),
                'user_id' => $userId,
            ]);
        });
    }

    public function makePiece(OldGoldExchange $exchange, Item $item, ?int $userId = null): OldGoldMovement
    {
        $this->context->ensureId((int) $exchange->company_id);

        if ((int) $item->metal_type_id !== (int) $exchange->metal_type_id || (int) $item->purity_id !== (int) $exchange->purity_id) {
            throw ValidationException::withMessages(['purity_uuid' => 'The piece must be the same metal and purity as the old gold.']);
        }

        $gross = BigDecimal::of((string) $item->gross_weight)->toScale(3, RoundingMode::HalfUp);
        $used = BigDecimal::of((string) $exchange->pieceMovements()->sum('gross_weight'));
        $left = BigDecimal::of((string) $exchange->gross_weight)->minus($used);

        if ($gross->isGreaterThan($left)) {
            throw ValidationException::withMessages(['gross_weight' => 'Only '.$left->toScale(3).' g of this old gold is left to make into a piece.']);
        }

        $share = $gross->dividedBy((string) $exchange->gross_weight, 10, RoundingMode::HalfUp);
        $fine = $share->multipliedBy((string) $exchange->melted_weight)->toScale(3, RoundingMode::HalfUp);
        $out = $this->takeOut((int) $exchange->metal_type_id, (int) $exchange->purity_id, $gross, $fine);

        return OldGoldMovement::query()->create([
            'company_id' => $exchange->company_id,
            'branch_id' => $exchange->branch_id,
            'metal_type_id' => $exchange->metal_type_id,
            'purity_id' => $exchange->purity_id,
            'direction' => 'out',
            'kind' => 'piece',
            'gross_weight' => (string) $gross,
            'fine_weight' => (string) $out['fine'],
            'value' => $out['value'],
            'party' => $item->item_code,
            'old_gold_exchange_id' => $exchange->id,
            'item_id' => $item->id,
            'moved_at' => now(),
            'user_id' => $userId,
        ]);
    }

    /**
     * Checks the stock in hand and returns the fine weight and cost that leave, at the average cost per fine gram.
     * Sending out all the gross clears the line, so test differences do not linger as stray fine grams.
     *
     * @return array{fine: BigDecimal, value: string}
     */
    private function takeOut(int $metalId, int $purityId, BigDecimal $gross, BigDecimal $fine): array
    {
        if ($gross->isNegativeOrZero() || $fine->isNegative() || $fine->isGreaterThan($gross)) {
            throw ValidationException::withMessages(['gross_weight' => 'Enter the gross weight, and a fine weight no more than the gross.']);
        }

        $rows = OldGoldMovement::query()
            ->where('metal_type_id', $metalId)
            ->where('purity_id', $purityId)
            ->lockForUpdate()
            ->get(['direction', 'gross_weight', 'fine_weight', 'value']);
        $sum = fn (string $field) => $rows->reduce(
            fn (BigDecimal $total, OldGoldMovement $row) => $row->direction === 'in' ? $total->plus((string) $row->{$field}) : $total->minus((string) $row->{$field}),
            BigDecimal::zero(),
        );
        $haveGross = $sum('gross_weight');
        $haveFine = $sum('fine_weight');
        $haveValue = $sum('value');

        if ($gross->isGreaterThan($haveGross)) {
            throw ValidationException::withMessages([
                'gross_weight' => 'Only '.$haveGross->toScale(3).' g gross ('.$haveFine->toScale(3).' g fine) of this old gold is in stock.',
            ]);
        }

        if ($gross->isEqualTo($haveGross)) {
            return ['fine' => $haveFine->toScale(3), 'value' => (string) $haveValue->toScale(2, RoundingMode::HalfUp)];
        }

        if ($fine->isGreaterThan($haveFine)) {
            throw ValidationException::withMessages([
                'fine_weight' => 'Only '.$haveFine->toScale(3).' g fine of this old gold is in stock.',
            ]);
        }

        return [
            'fine' => $fine,
            'value' => $haveFine->isZero() ? '0.00' : (string) $haveValue->multipliedBy($fine)->dividedBy($haveFine, 2, RoundingMode::HalfUp),
        ];
    }
}
