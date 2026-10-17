<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\MetalStockRequest;
use App\Services\Commerce\MetalStockService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;

class MetalStockController extends Controller
{
    public function store(MetalStockRequest $request, MetalStockService $stock, CompanyContext $context): RedirectResponse
    {
        $stock->add($context->company(), $request->validated(), $request->user()?->id);
        $opening = $request->validated('kind') === 'opening';

        return redirect()
            ->route('reports.stock')
            ->with('status', $opening ? 'Opening stock saved.' : 'Purchase added to stock.');
    }
}
