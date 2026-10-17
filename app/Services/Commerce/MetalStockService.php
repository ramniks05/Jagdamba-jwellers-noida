<?php

namespace App\Services\Commerce;

use App\Enums\MetalStockKind;
use App\Models\Branch;
use App\Models\Company;
use App\Models\MetalStockMovement;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\Supplier;
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MetalStockService
{
    public function __construct(private readonly CompanyContext $context) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function add(Company $company, array $attributes, ?int $userId = null): MetalStockMovement
    {
        $this->context->ensureId((int) $company->id);
        $branch = Branch::query()->where('uuid', $attributes['branch_uuid'])->first();
        $metal = MetalType::query()->where('uuid', $attributes['metal_uuid'])->where('is_active', true)->first();
        $purity = Purity::query()->where('uuid', $attributes['purity_uuid'])->where('is_active', true)->first();

        if (! $branch || ! $metal || ! $purity || (int) $purity->metal_type_id !== (int) $metal->id) {
            throw ValidationException::withMessages([
                'purity_uuid' => 'Choose a purity of the selected metal.',
            ]);
        }

        $grams = BigDecimal::of((string) $attributes['grams'])->toScale(3, RoundingMode::HalfUp);

        if ($grams->isNegativeOrZero()) {
            throw ValidationException::withMessages([
                'grams' => 'Enter the grams.',
            ]);
        }

        $kind = MetalStockKind::from((string) $attributes['kind']);

        if ($kind === MetalStockKind::Opening) {
            return $this->setOpening($branch, $metal, $purity, $grams, $userId);
        }

        $supplier = Supplier::query()->where('uuid', $attributes['supplier_uuid'] ?? '')->where('is_active', true)->first();

        if (! $supplier) {
            throw ValidationException::withMessages([
                'supplier_uuid' => 'Choose the supplier for this purchase.',
            ]);
        }

        return $this->record((int) $branch->id, (int) $metal->id, (int) $purity->id, $grams, MetalStockKind::Purchase, $supplier, $userId);
    }

    public function sell(int $branchId, int $metalId, int $purityId, string $grams, Model $reference, ?int $userId = null): void
    {
        $weight = BigDecimal::of($grams)->toScale(3, RoundingMode::HalfUp);

        if ($weight->isNegativeOrZero()) {
            return;
        }

        $this->record($branchId, $metalId, $purityId, $weight, MetalStockKind::Sale, $reference, $userId);
    }

    public function restore(int $branchId, int $metalId, int $purityId, string $grams, Model $reference, ?int $userId = null): void
    {
        $weight = BigDecimal::of($grams)->toScale(3, RoundingMode::HalfUp);

        if ($weight->isNegativeOrZero()) {
            return;
        }

        $this->record($branchId, $metalId, $purityId, $weight, MetalStockKind::SaleReturn, $reference, $userId);
    }

    /**
     * @return Collection<int, array{metal: string, metal_uuid: string, purity: string, purity_uuid: string, opening: string, purchased: string, sold: string, balance: string, short: bool}>
     */
    public function balances(?Supplier $supplier = null): Collection
    {
        $purchase = MetalStockKind::Purchase->value;
        $rows = MetalStockMovement::query()
            ->select('metal_type_id', 'purity_id')
            ->selectRaw("coalesce(sum(case when kind = ? then grams else 0 end), 0) as opening_grams", [MetalStockKind::Opening->value])
            ->selectRaw('coalesce(sum(case when kind = ? then grams else 0 end), 0) as purchased_grams', [$purchase])
            ->when($supplier, fn ($query) => $query->selectRaw(
                'coalesce(sum(case when kind = ? and reference_type = ? and reference_id = ? then grams else 0 end), 0) as supplier_purchased_grams',
                [$purchase, $supplier->getMorphClass(), $supplier->id],
            ))
            ->selectRaw("coalesce(sum(case when kind = ? then grams else 0 end), 0) as sold_grams", [MetalStockKind::Sale->value])
            ->selectRaw("coalesce(sum(case when kind = ? then grams else 0 end), 0) as returned_grams", [MetalStockKind::SaleReturn->value])
            ->when($supplier, function ($query) use ($supplier, $purchase): void {
                $query->whereExists(function ($bought) use ($supplier, $purchase): void {
                    $bought->selectRaw('1')
                        ->from('metal_stock_movements as supplier_purchases')
                        ->whereColumn('supplier_purchases.company_id', 'metal_stock_movements.company_id')
                        ->whereColumn('supplier_purchases.metal_type_id', 'metal_stock_movements.metal_type_id')
                        ->whereColumn('supplier_purchases.purity_id', 'metal_stock_movements.purity_id')
                        ->where('supplier_purchases.kind', $purchase)
                        ->where('supplier_purchases.reference_type', $supplier->getMorphClass())
                        ->where('supplier_purchases.reference_id', $supplier->id);
                });
            })
            ->groupBy('metal_type_id', 'purity_id')
            ->get();

        $metals = MetalType::query()->whereIn('id', $rows->pluck('metal_type_id'))->get()->keyBy('id');
        $purities = Purity::query()->whereIn('id', $rows->pluck('purity_id'))->get()->keyBy('id');

        return $rows->map(function ($row) use ($metals, $purities, $supplier) {
            $opening = BigDecimal::of((string) $row->opening_grams);
            $purchased = BigDecimal::of((string) $row->purchased_grams);
            $shown = $supplier ? BigDecimal::of((string) $row->supplier_purchased_grams) : $purchased;
            $sold = BigDecimal::of((string) $row->sold_grams)->minus((string) $row->returned_grams);
            $balance = $opening->plus($purchased)->minus($sold);

            return [
                'metal' => (string) ($metals[$row->metal_type_id]->name ?? ''),
                'metal_uuid' => (string) ($metals[$row->metal_type_id]->uuid ?? ''),
                'purity' => (string) ($purities[$row->purity_id]->name ?? ''),
                'purity_uuid' => (string) ($purities[$row->purity_id]->uuid ?? ''),
                'opening' => (string) $opening->toScale(3, RoundingMode::HalfUp),
                'purchased' => (string) $shown->toScale(3, RoundingMode::HalfUp),
                'sold' => (string) $sold->toScale(3, RoundingMode::HalfUp),
                'balance' => (string) $balance->toScale(3, RoundingMode::HalfUp),
                'short' => $balance->isNegative(),
            ];
        })->sortBy([
            ['metal', 'asc'],
            ['purity', 'asc'],
        ])->values();
    }

    private function setOpening(Branch $branch, MetalType $metal, Purity $purity, BigDecimal $grams, ?int $userId): MetalStockMovement
    {
        return DB::transaction(function () use ($branch, $metal, $purity, $grams, $userId) {
            $existing = MetalStockMovement::query()
                ->where('branch_id', $branch->id)
                ->where('metal_type_id', $metal->id)
                ->where('purity_id', $purity->id)
                ->where('kind', MetalStockKind::Opening)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->grams = (string) $grams;
                $existing->user_id = $userId;
                $existing->occurred_at = now();
                $existing->save();

                return $existing;
            }

            return $this->record((int) $branch->id, (int) $metal->id, (int) $purity->id, $grams, MetalStockKind::Opening, null, $userId);
        });
    }

    private function record(int $branchId, int $metalId, int $purityId, BigDecimal $grams, MetalStockKind $kind, ?Model $reference, ?int $userId): MetalStockMovement
    {
        return MetalStockMovement::query()->create([
            'branch_id' => $branchId,
            'metal_type_id' => $metalId,
            'purity_id' => $purityId,
            'kind' => $kind,
            'grams' => (string) $grams->toScale(3, RoundingMode::HalfUp),
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'user_id' => $userId,
            'occurred_at' => now(),
        ]);
    }
}
