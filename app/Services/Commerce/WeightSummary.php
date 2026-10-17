<?php

namespace App\Services\Commerce;

use App\Models\PurchaseLine;
use App\Models\SaleLine;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;

class WeightSummary
{
    /**
     * Gross and net weight of the bills in this list, split by metal and purity.
     *
     * @param  Builder<\App\Models\Sale>  $sales
     * @return array{gross: string, net: string, rows: list<array{label: string, gross: string, net: string}>}
     */
    public function sales(Builder $sales): array
    {
        $rows = SaleLine::query()
            ->whereIn('sale_id', (clone $sales)->select('sales.id'))
            ->select('metal_name', 'purity_name')
            ->selectRaw('coalesce(sum(gross_weight), 0) as gross')
            ->selectRaw('coalesce(sum(net_weight), 0) as net')
            ->groupBy('metal_name', 'purity_name')
            ->orderBy('metal_name')
            ->orderBy('purity_name')
            ->get();

        return $this->pack($rows->map(fn ($row): array => [
            'label' => trim($row->metal_name.' '.$row->purity_name),
            'gross' => (string) $row->gross,
            'net' => (string) $row->net,
        ])->all());
    }

    /**
     * @param  Builder<\App\Models\Purchase>  $purchases
     * @return array{gross: string, net: string, rows: list<array{label: string, gross: string, net: string}>}
     */
    public function purchases(Builder $purchases): array
    {
        $rows = PurchaseLine::query()
            ->join('items', 'items.id', '=', 'purchase_lines.item_id')
            ->join('metal_types', 'metal_types.id', '=', 'items.metal_type_id')
            ->join('purities', 'purities.id', '=', 'items.purity_id')
            ->whereIn('purchase_lines.purchase_id', (clone $purchases)->select('purchases.id'))
            ->select('metal_types.name as metal_name', 'purities.name as purity_name')
            ->selectRaw('coalesce(sum(purchase_lines.gross_weight), 0) as gross')
            ->selectRaw('coalesce(sum(purchase_lines.net_weight), 0) as net')
            ->groupBy('metal_types.name', 'purities.name')
            ->orderBy('metal_types.name')
            ->orderBy('purities.name')
            ->get();

        return $this->pack($rows->map(fn ($row): array => [
            'label' => trim($row->metal_name.' '.$row->purity_name),
            'gross' => (string) $row->gross,
            'net' => (string) $row->net,
        ])->all());
    }

    /**
     * @param  list<array{label: string, gross: string, net: string}>  $rows
     * @return array{gross: string, net: string, rows: list<array{label: string, gross: string, net: string}>}
     */
    private function pack(array $rows): array
    {
        $gross = BigDecimal::zero();
        $net = BigDecimal::zero();
        $packed = [];

        foreach ($rows as $row) {
            $rowGross = BigDecimal::of($row['gross'])->toScale(3, RoundingMode::HalfUp);
            $rowNet = BigDecimal::of($row['net'])->toScale(3, RoundingMode::HalfUp);
            $gross = $gross->plus($rowGross);
            $net = $net->plus($rowNet);
            $packed[] = [
                'label' => $row['label'] !== '' ? $row['label'] : 'Weight',
                'gross' => (string) $rowGross,
                'net' => (string) $rowNet,
            ];
        }

        return [
            'gross' => (string) $gross->toScale(3, RoundingMode::HalfUp),
            'net' => (string) $net->toScale(3, RoundingMode::HalfUp),
            'rows' => $packed,
        ];
    }
}
