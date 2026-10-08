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
use App\Models\MetalType;
use App\Models\OldGoldExchange;
use App\Models\Purity;
use App\Models\StockLocation;
use App\Services\Commerce\InventoryService;
use App\Services\Commerce\ItemService;
use App\Services\Commerce\OldGoldStockService;
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ItemController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Item::class);
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');

        $items = Item::query()
            ->with(['metalType', 'purity', 'location', 'category'])
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
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Item::class);
        $item = new Item([
            'gross_weight' => '0.000',
            'stone_weight' => '0.000',
            'other_weight' => '0.000',
            'making_value' => '0',
            'wastage_value' => '0',
            'stone_value' => '0',
            'cost_price' => '0',
            'selling_price' => '0',
            'mrp' => '0',
        ]);
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

        return view('commerce.items.form', $this->formData($item) + ['fromOldGold' => $fromOldGold]);
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

        return redirect()->route('items.show', $item)->with('status', $exchange
            ? 'Piece saved and added to stock from old gold '.$exchange->number.'.'
            : 'Piece saved and added to stock.');
    }

    public function show(Item $item): View
    {
        $this->authorize('view', $item);

        return view('commerce.items.show', [
            'item' => $item->load(['metalType', 'purity', 'category.parent', 'brand', 'collection', 'design', 'location.branch', 'makingMethod', 'wastageMethod', 'stones']),
            'movements' => $item->movements()->orderByDesc('occurred_at')->orderByDesc('id')->get(),
        ]);
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
            'categories' => Category::query()->with('parent')->orderBy('name')->get(),
            'brands' => Brand::query()->orderBy('name')->get(),
            'collections' => Collection::query()->orderBy('name')->get(),
            'designs' => Design::query()->orderBy('name')->get(),
            'metals' => MetalType::query()->orderBy('name')->get(),
            'purities' => Purity::query()->with('metalType')->orderBy('name')->get(),
            'locations' => StockLocation::query()->with('branch')->where('is_active', true)->orderBy('name')->get(),
            'makingMethods' => ChargeMethod::query()->where('applies_to', 'making')->where('is_active', true)->orderBy('sort_order')->get(),
            'wastageMethods' => ChargeMethod::query()->where('applies_to', 'wastage')->where('is_active', true)->orderBy('sort_order')->get(),
        ];
    }
}
