<?php

namespace App\Services\Commerce;

use App\Enums\ItemStatus;
use App\Models\Category;
use App\Models\Item;
use Illuminate\Support\Collection;

class PieceCategorySummary
{
    /**
     * Pieces by category and status. Own-gold pieces are left out, as on the Pieces page.
     *
     * @return Collection<int, array{uuid: ?string, id: ?int, name: string, in_stock: int, gross: string, net: string, reserved: int, repair: int, sold: int}>
     */
    public function rows(?string $metalUuid = null, ?string $locationUuid = null): Collection
    {
        $available = ItemStatus::Available->value;
        $grouped = Item::query()
            ->where('own_gold', false)
            ->when($metalUuid, fn ($rows) => $rows->whereHas('metalType', fn ($metals) => $metals->where('uuid', $metalUuid)))
            ->when($locationUuid, fn ($rows) => $rows->whereHas('location', fn ($places) => $places->where('uuid', $locationUuid)))
            ->toBase()
            ->selectRaw(
                'category_id,
                sum(case when status = ? then 1 else 0 end) as in_stock,
                coalesce(sum(case when status = ? then gross_weight else 0 end), 0) as gross,
                coalesce(sum(case when status = ? then net_weight else 0 end), 0) as net,
                sum(case when status = ? then 1 else 0 end) as reserved,
                sum(case when status = ? then 1 else 0 end) as repair,
                sum(case when status = ? then 1 else 0 end) as sold',
                [$available, $available, $available, ItemStatus::Reserved->value, ItemStatus::Repair->value, ItemStatus::Sold->value],
            )
            ->groupBy('category_id')
            ->get();

        $categories = Category::withTrashed()
            ->whereIn('id', $grouped->pluck('category_id')->filter())
            ->get(['id', 'uuid', 'name', 'sort_order'])
            ->keyBy('id');

        return $grouped
            ->map(function ($row) use ($categories): array {
                $category = $row->category_id ? $categories->get($row->category_id) : null;

                return [
                    'id' => $category?->id,
                    'uuid' => $category?->uuid,
                    'name' => $category?->name ?? 'No category',
                    'sort' => $category ? sprintf('0-%05d-%s', $category->sort_order, mb_strtolower($category->name)) : '1',
                    'in_stock' => (int) $row->in_stock,
                    'gross' => number_format((float) $row->gross, 3, '.', ''),
                    'net' => number_format((float) $row->net, 3, '.', ''),
                    'reserved' => (int) $row->reserved,
                    'repair' => (int) $row->repair,
                    'sold' => (int) $row->sold,
                ];
            })
            ->sortBy('sort')
            ->map(fn (array $row): array => collect($row)->except('sort')->all())
            ->values();
    }
}
