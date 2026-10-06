<?php

namespace App\Http\Controllers\Api\V1\Commerce;

use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\ItemRequest;
use App\Models\Item;
use App\Services\Commerce\ItemService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Item::class);
        $items = Item::query()
            ->with(['metalType', 'purity'])
            ->matching(trim((string) $request->query('search', '')))
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'data' => $items->map(fn (Item $item) => $this->payload($item))->values(),
        ]);
    }

    public function store(ItemRequest $request, ItemService $items, CompanyContext $context): JsonResponse
    {
        $item = $items->create($context->company(), $request->validated(), $request->user()?->id);

        return response()->json(['data' => $this->payload($item->load(['metalType', 'purity']))], 201);
    }

    public function show(Item $item): JsonResponse
    {
        $this->authorize('view', $item);

        return response()->json(['data' => $this->payload($item->load(['metalType', 'purity']))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Item $item): array
    {
        return [
            'uuid' => $item->uuid,
            'item_code' => $item->item_code,
            'sku' => $item->sku,
            'name' => $item->name,
            'status' => $item->status->value,
            'net_weight' => $item->net_weight,
            'metal' => $item->metalType?->name,
            'purity' => $item->purity?->name,
        ];
    }
}
