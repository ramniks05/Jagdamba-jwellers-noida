<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\OldGoldSendRequest;
use App\Models\OldGoldExchange;
use App\Models\OldGoldMovement;
use App\Services\Commerce\OldGoldStockService;
use App\Services\Foundation\NumberFormatService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OldGoldStockController extends Controller
{
    public function index(OldGoldStockService $stock, NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('viewAny', OldGoldExchange::class);

        return view('commerce.old-gold.stock', [
            'balances' => $stock->balances(),
            'movements' => OldGoldMovement::query()
                ->with(['metalType', 'purity', 'exchange.customer', 'item'])
                ->orderByDesc('moved_at')
                ->orderByDesc('id')
                ->paginate(25),
            'kinds' => OldGoldMovement::SEND_KINDS,
            'money' => fn (string $amount) => $format->money($amount, $context->company()),
            'weight' => fn (string $amount) => $format->weight($amount, $context->company()),
        ]);
    }

    public function store(OldGoldSendRequest $request, OldGoldStockService $stock, CompanyContext $context): RedirectResponse
    {
        $movement = $stock->send($context->company(), $request->validated(), $request->user()?->id);

        return redirect()->route('old-gold.stock')->with('status', 'Sent out '.$movement->gross_weight.' g of '.$movement->metalType?->name.' '.$movement->purity?->name.'.');
    }
}
