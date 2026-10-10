<?php

namespace App\Services\Commerce;

use App\Enums\ItemStatus;
use App\Models\AdvanceOrder;
use App\Models\Company;
use App\Models\Item;
use App\Services\Foundation\NumberFormatService;
use Illuminate\Database\Eloquent\Builder;

class BillScanService
{
    public const CODE_PATTERN = '/^[\x21-\x7E]{1,64}$/';

    public function __construct(
        private readonly SaleService $sales,
        private readonly NumberFormatService $format,
    ) {}

    public static function clean(string $code): string
    {
        return trim((string) preg_replace('/[\x00-\x1F\x7F]+/', '', $code));
    }

    /**
     * Finds the shop's piece for a scanned tag. Reading never changes the piece or its stock.
     */
    public function find(string $code): ?Item
    {
        $exact = $this->pieces()->where(fn (Builder $query) => $query->where('barcode', $code)->orWhere('item_code', $code))->get();
        $match = $exact->firstWhere('barcode', $code) ?? $exact->first();

        if ($match) {
            return $match;
        }

        $lower = mb_strtolower($code);

        return $this->pieces()
            ->where(fn (Builder $query) => $query->whereRaw('lower(barcode) = ?', [$lower])->orWhereRaw('lower(item_code) = ?', [$lower]))
            ->orderBy('id')
            ->first();
    }

    /**
     * @return array{ok: bool, status: int, message: string, piece?: array<string, mixed>}
     */
    public function scan(string $code, Company $company, ?AdvanceOrder $order = null): array
    {
        $code = self::clean($code);

        if (preg_match(self::CODE_PATTERN, $code) !== 1) {
            return ['ok' => false, 'status' => 422, 'message' => 'That is not a tag code. Scan again or type the piece code.'];
        }

        $item = $this->find($code);

        if (! $item) {
            return ['ok' => false, 'status' => 404, 'message' => 'No piece in this shop has the code "'.$code.'".'];
        }

        if ($item->status !== ItemStatus::Available) {
            return ['ok' => false, 'status' => 409, 'message' => $item->item_code.' is '.mb_strtolower($item->status->label()).', so it cannot be billed.'];
        }

        $quote = $this->sales->quote($item, $order);

        if (! $quote['ready']) {
            return ['ok' => false, 'status' => 422, 'message' => $item->item_code.': '.$quote['message']];
        }

        return ['ok' => true, 'status' => 200, 'message' => 'Added '.$item->item_code.'.', 'piece' => $this->present($item, $quote, $company)];
    }

    /**
     * The shape the bill page uses for a tagged piece.
     *
     * @param  array<string, mixed>  $quote
     * @return array<string, mixed>
     */
    public function present(Item $item, array $quote, Company $company): array
    {
        $ready = (bool) $quote['ready'];

        return [
            'uuid' => $item->uuid,
            'code' => $item->item_code,
            'name' => $item->name,
            'barcode' => $item->barcode,
            'huid' => $item->huid,
            'metal' => trim(($item->metalType?->name ?? '').' '.($item->purity?->name ?? '')),
            'purity' => $item->purity?->name,
            'gross' => $this->format->weight((string) $item->gross_weight, $company),
            'net' => $this->format->weight((string) $item->net_weight, $company),
            'netWeight' => (float) $item->net_weight,
            'ready' => $ready,
            'line' => $ready ? (float) $quote['line'] : 0,
            'rate' => $ready ? (float) $quote['rate'] : 0,
            'metalAmount' => $ready ? (float) $quote['metal'] : 0,
            'wastageAmount' => $ready ? (float) $quote['wastage'] : 0,
            'makingAmount' => $ready ? (float) $quote['making'] : 0,
            'stoneAmount' => $ready ? (float) $quote['stone'] : 0,
            'stones' => $item->stones->map(fn ($stone) => [
                'name' => $stone->name,
                'weight' => (string) $stone->weight,
                'value' => (float) $stone->value,
                'rate' => $stone->rate !== null ? (string) $stone->rate : null,
                'rate_unit' => $stone->rate_unit,
            ])->values()->all(),
            'label' => $ready ? $this->format->money((string) $quote['line'], $company) : (string) $quote['message'],
        ];
    }

    /**
     * @return Builder<Item>
     */
    private function pieces(): Builder
    {
        return Item::query()->with(['metalType', 'purity', 'makingMethod', 'wastageMethod', 'stones']);
    }
}
