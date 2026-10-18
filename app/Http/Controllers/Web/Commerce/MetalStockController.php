<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Enums\MetalStockKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\MetalStockRequest;
use App\Models\MetalStockMovement;
use App\Services\Commerce\MetalStockService;
use App\Services\Foundation\NumberFormatService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;

class MetalStockController extends Controller
{
    public function store(MetalStockRequest $request, MetalStockService $stock, NumberFormatService $format, CompanyContext $context): RedirectResponse
    {
        $movement = $stock->add($context->company(), $request->validated(), $request->user()?->id);
        $opening = $request->validated('kind') === 'opening';
        $status = $opening
            ? 'Opening stock saved.'
            : 'Purchase added to stock. Total '.$format->money((string) $movement->total_amount).'.';

        return redirect()
            ->route('reports.stock')
            ->with('status', $status);
    }

    public function update(MetalStockRequest $request, MetalStockMovement $movement, MetalStockService $stock, NumberFormatService $format, CompanyContext $context): RedirectResponse
    {
        abort_unless($movement->kind === MetalStockKind::Purchase, 404);
        $movement = $stock->updatePurchase($context->company(), $movement, $request->validated(), $request->user()?->id);

        return redirect()
            ->route('reports.stock')
            ->with('status', 'Purchase corrected. Total '.$format->money((string) $movement->total_amount).'.');
    }
}
