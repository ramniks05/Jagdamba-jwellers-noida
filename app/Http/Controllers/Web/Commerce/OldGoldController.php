<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\OldGoldRequest;
use App\Models\Customer;
use App\Models\MetalType;
use App\Models\OldGoldExchange;
use App\Services\Commerce\OldGoldService;
use App\Services\Foundation\NumberFormatService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OldGoldController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', OldGoldExchange::class);

        return view('commerce.old-gold.index', [
            'exchanges' => OldGoldExchange::query()->with(['customer', 'metalType', 'purity'])->orderByDesc('exchanged_at')->paginate(20),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', OldGoldExchange::class);

        return view('commerce.old-gold.create', [
            'customers' => Customer::query()->where('is_active', true)->orderBy('name')->get(),
            'metals' => MetalType::query()->with('purities')->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(OldGoldRequest $request, OldGoldService $exchanges, CompanyContext $context): RedirectResponse
    {
        $exchange = $exchanges->exchange($context->company(), $request->validated(), $request->user()?->id);

        return redirect()->route('old-gold.show', $exchange)->with('status', 'Old gold '.$exchange->number.' saved.');
    }

    public function show(OldGoldExchange $exchange, NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('view', $exchange);
        $exchange->load(['customer', 'metalType', 'purity']);

        return view('commerce.old-gold.show', [
            'exchange' => $exchange,
            'money' => fn (string $amount) => $format->money($amount, $context->company()),
            'weight' => fn (string $amount) => $format->weight($amount, $context->company()),
        ]);
    }
}
