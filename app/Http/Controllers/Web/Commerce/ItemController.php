<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Enums\InventoryMovement;
use App\Enums\ItemStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\ItemRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ChargeMethod;
use App\Models\Collection;
use App\Models\Design;
use App\Models\Item;
use App\Models\MetalRate;
use App\Models\MetalType;
use App\Models\OldGoldExchange;
use App\Models\Purity;
use App\Models\StockLocation;
use App\Models\StoneType;
use App\Services\Commerce\InventoryService;
use App\Services\Commerce\ItemService;
use App\Services\Commerce\JewelleryPricer;
use App\Services\Commerce\OldGoldStockService;
use App\Services\Commerce\RateBook;
use App\Services\Foundation\NumberFormatService;
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ItemController extends Controller
{
    public function index(Request $request, NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('viewAny', Item::class);
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');

        $status = $request->has('status') ? $status : ItemStatus::Available->value;

        $items = Item::query()
            ->with(['metalType', 'purity', 'location.branch', 'category'])
            ->matching($search)
            ->when(ItemStatus::tryFrom($status), fn ($query, $parsed) => $query->where('status', $parsed))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('commerce.items.index', [
            'items' => $items,
            'search' => $search,
            'status' => $status,
            'statuses' => ItemStatus::cases(),
            'counts' => Item::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'weight' => fn (string $amount) => $format->weight($amount, $context->company()),
        ]);
    }

    public function create(Request $request, ItemService $items): View
    {
        $this->authorize('create', Item::class);
        $item = new Item(['item_code' => $items->nextCode()]);
        $like = $request->filled('like')
            ? Item::query()->with(['metalType', 'purity', 'category', 'location', 'makingMethod', 'wastageMethod'])->where('uuid', $request->query('like'))->first()
            : null;

        if ($like) {
            $item->fill(['making_value' => $like->making_value, 'wastage_value' => $like->wastage_value]);

            foreach (['metalType', 'purity', 'category', 'location', 'makingMethod', 'wastageMethod'] as $relation) {
                $item->setRelation($relation, $like->{$relation});
            }
        } else {
            $gold = MetalType::query()->where('code', 'GOLD')->first();
            $item->setRelation('metalType', $gold);
            $item->setRelation('purity', $gold ? Purity::query()->where('metal_type_id', $gold->id)->where('code', '22K')->first() : null);
            $item->setRelation('makingMethod', ChargeMethod::query()->where('applies_to', 'making')->where('code', 'per_gram')->where('is_active', true)->first());
        }

        $fromOldGold = $request->filled('from_old_gold')
            ? OldGoldExchange::query()->with(['metalType', 'purity'])->withSum('pieceMovements', 'gross_weight')->where('uuid', $request->query('from_old_gold'))->first()
            : null;

        if ($fromOldGold) {
            $left = BigDecimal::of((string) $fromOldGold->gross_weight)->minus((string) ($fromOldGold->piece_movements_sum_gross_weight ?? '0'))->toScale(3, RoundingMode::HalfUp);
            $share = $fromOldGold->gross_weight > 0 ? $left->dividedBy((string) $fromOldGold->gross_weight, 10, RoundingMode::HalfUp) : BigDecimal::zero();
            $item->fill([
                'name' => 'Old '.$fromOldGold->purity?->name.' '.$fromOldGold->metalType?->name,
                'gross_weight' => (string) $left,
                'cost_price' => (string) $share->multipliedBy((string) $fromOldGold->exchange_value)->toScale(2, RoundingMode::HalfUp),
                'notes' => 'From old gold '.$fromOldGold->number,
            ]);
            $item->setRelation('metalType', $fromOldGold->metalType);
            $item->setRelation('purity', $fromOldGold->purity);
        }

        return view('commerce.items.form', $this->formData($item) + ['fromOldGold' => $fromOldGold, 'like' => $like]);
    }

    public function store(ItemRequest $request, ItemService $items, OldGoldStockService $oldGold, CompanyContext $context): RedirectResponse
    {
        $attributes = $request->validated();
        $exchange = filled($attributes['old_gold_uuid'] ?? null)
            ? OldGoldExchange::query()->where('uuid', $attributes['old_gold_uuid'])->firstOrFail()
            : null;

        if ($exchange) {
            $this->authorize('create', OldGoldExchange::class);
        }

        $attributes['image_path'] = $request->hasFile('image')
            ? $request->file('image')->store('items/'.$context->company()->uuid, 'public')
            : null;

        $item = DB::transaction(function () use ($items, $oldGold, $context, $attributes, $exchange, $request) {
            $item = $items->create($context->company(), $attributes, $request->user()?->id, InventoryMovement::Opening, $exchange);

            if ($exchange) {
                $oldGold->makePiece($exchange, $item, $request->user()?->id);
            }

            return $item;
        });

        $status = $exchange
            ? 'Piece '.$item->item_code.' saved and added to stock from old gold '.$exchange->number.'.'
            : 'Piece '.$item->item_code.' saved and added to stock.';

        if ($request->input('next') === 'another' && ! $exchange) {
            return redirect()->route('items.create', ['like' => $item->uuid])->with('status', $status.' Add the next one.');
        }

        return redirect()->route('items.show', $item)->with('status', $status);
    }

    public function show(Item $item, RateBook $rates, JewelleryPricer $pricer, NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('view', $item);
        $item->load(['metalType', 'purity', 'category.parent', 'brand', 'collection', 'design', 'location.branch', 'makingMethod', 'wastageMethod', 'stones']);
        $rate = $rates->current((int) $item->metal_type_id, (int) $item->purity_id, $item->branch_id ? (int) $item->branch_id : null);

        return view('commerce.items.show', [
            'item' => $item,
            'movements' => $item->movements()->orderByDesc('occurred_at')->orderByDesc('id')->get(),
            'rate' => $rate,
            'price' => $rate ? $pricer->line(
                (string) $item->net_weight,
                (string) $rate->rate_per_gram,
                $item->wastageMethod?->code ?? 'fixed',
                (string) ($item->wastage_value ?? '0'),
                $item->makingMethod?->code ?? 'fixed',
                (string) ($item->making_value ?? '0'),
                (string) ($item->stone_value ?? '0'),
            ) : null,
            'money' => fn (string $amount) => $format->money($amount, $context->company()),
            'weight' => fn (string $amount) => $format->weight($amount, $context->company()),
        ]);
    }

    public function photo(Item $item): StreamedResponse
    {
        $this->authorize('view', $item);
        abort_unless($item->image_path && Storage::disk('public')->exists($item->image_path), 404);

        return Storage::disk('public')->response($item->image_path);
    }

    public function edit(Item $item): View
    {
        $this->authorize('update', $item);

        return view('commerce.items.form', $this->formData($item->load(['category', 'brand', 'collection', 'design', 'metalType', 'purity', 'location', 'makingMethod', 'wastageMethod', 'stones'])));
    }

    public function update(ItemRequest $request, Item $item, ItemService $items, CompanyContext $context): RedirectResponse
    {
        $attributes = $request->validated();

        if ($request->hasFile('image')) {
            $attributes['image_path'] = $request->file('image')->store('items/'.$context->company()->uuid, 'public');
        }

        $items->update($item, $attributes);

        return redirect()->route('items.show', $item)->with('status', 'Piece saved.');
    }

    public function damage(Item $item, InventoryService $inventory): RedirectResponse
    {
        $this->authorize('inventory.adjust');
        $inventory->apply($item, InventoryMovement::Damage, null, null, null, 'Marked damaged', auth()->id());

        return back()->with('status', 'Piece marked damaged.');
    }

    public function lost(Item $item, InventoryService $inventory): RedirectResponse
    {
        $this->authorize('inventory.adjust');
        $inventory->apply($item, InventoryMovement::Lost, null, null, null, 'Marked lost', auth()->id());

        return back()->with('status', 'Piece marked lost.');
    }

    public function reserve(Item $item, InventoryService $inventory): RedirectResponse
    {
        $this->authorize('inventory.adjust');
        $inventory->apply($item, InventoryMovement::Reserve, null, null, null, 'Reserved', auth()->id());

        return back()->with('status', 'Piece reserved.');
    }

    public function release(Item $item, InventoryService $inventory): RedirectResponse
    {
        $this->authorize('inventory.adjust');
        $inventory->apply($item, InventoryMovement::Release, null, null, null, 'Reservation released', auth()->id());

        return back()->with('status', 'Piece is available again.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Item $item): array
    {
        return [
            'item' => $item,
            'categories' => Category::query()->with('parent')->where(fn ($rows) => $rows->active()->orWhere('id', $item->category_id))->orderBy('sort_order')->orderBy('name')->get(),
            'brands' => Brand::query()->where(fn ($rows) => $rows->active()->orWhere('id', $item->brand_id))->orderBy('name')->get(),
            'collections' => Collection::query()->where(fn ($rows) => $rows->active()->orWhere('id', $item->collection_id))->orderBy('name')->get(),
            'designs' => Design::query()->where(fn ($rows) => $rows->active()->orWhere('id', $item->design_id))->orderBy('name')->get(),
            'metals' => MetalType::query()
                ->with(['purities' => fn ($purities) => $purities->where(fn ($rows) => $rows->active()->orWhere('id', $item->purity_id))])
                ->where(fn ($rows) => $rows->active()->orWhere('id', $item->metal_type_id))
                ->orderBy('name')
                ->get(),
            'locations' => StockLocation::query()->with('branch')->where('is_active', true)->orderBy('name')->get(),
            'stoneTypes' => StoneType::query()->active()->orderBy('sort_order')->orderBy('name')->pluck('name'),
            'makingMethods' => ChargeMethod::query()->where('applies_to', 'making')->where('is_active', true)->orderBy('sort_order')->get(),
            'wastageMethods' => ChargeMethod::query()->where('applies_to', 'wastage')->where('is_active', true)->orderBy('sort_order')->get(),
            'rates' => MetalRate::query()->orderByDesc('effective_at')->orderByDesc('id')->get(['metal_type_id', 'purity_id', 'branch_id', 'rate_per_gram']),
        ];
    }
}
