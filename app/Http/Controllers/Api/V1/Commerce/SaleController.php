<?php

namespace App\Http\Controllers\Api\V1\Commerce;

use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\SaleRequest;
use App\Models\Sale;
use App\Services\Commerce\SaleService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;

class SaleController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Sale::class);
        $sales = Sale::query()->with('customer')->orderByDesc('sold_at')->paginate(20);

        return response()->json([
            'data' => $sales->map(fn (Sale $sale) => [
                'uuid' => $sale->uuid,
                'number' => $sale->number,
                'total' => $sale->total,
                'paid_amount' => $sale->paid_amount,
                'customer_uuid' => $sale->customer?->uuid,
            ])->values(),
        ]);
    }

    public function store(SaleRequest $request, SaleService $sales, CompanyContext $context): JsonResponse
    {
        $sale = $sales->post($context->company(), $request->validated(), $request->user()?->id);

        return response()->json(['data' => [
            'uuid' => $sale->uuid,
            'number' => $sale->number,
            'total' => $sale->total,
            'paid_amount' => $sale->paid_amount,
            'balance_due' => $sale->balanceDue(),
        ]], 201);
    }

    public function show(Sale $sale): JsonResponse
    {
        $this->authorize('view', $sale);
        $sale->load(['lines', 'payments']);

        return response()->json(['data' => [
            'uuid' => $sale->uuid,
            'number' => $sale->number,
            'total' => $sale->total,
            'paid_amount' => $sale->paid_amount,
            'balance_due' => $sale->balanceDue(),
            'lines' => $sale->lines->map(fn ($line) => [
                'item_code' => $line->item_code,
                'rate_per_gram' => $line->rate_per_gram,
                'line_amount' => $line->line_amount,
            ])->values(),
        ]]);
    }
}
