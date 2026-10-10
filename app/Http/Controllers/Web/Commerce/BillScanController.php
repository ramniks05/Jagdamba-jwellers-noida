<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Http\Controllers\Controller;
use App\Models\AdvanceOrder;
use App\Models\Sale;
use App\Services\Commerce\BillScanService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillScanController extends Controller
{
    public function __invoke(Request $request, BillScanService $scanner, CompanyContext $context): JsonResponse
    {
        $this->authorize('create', Sale::class);
        $request->validate([
            'code' => ['required', 'string', 'max:200'],
            'order' => ['nullable', 'uuid'],
        ]);

        $order = $request->filled('order')
            ? AdvanceOrder::query()->where('uuid', (string) $request->query('order'))->whereIn('status', AdvanceOrder::OPEN)->first()
            : null;
        $result = $scanner->scan((string) $request->query('code'), $context->company(), $order);

        return response()
            ->json(array_filter(['message' => $result['message'], 'piece' => $result['piece'] ?? null], fn ($value) => $value !== null), $result['status'])
            ->header('Cache-Control', 'no-store');
    }
}
