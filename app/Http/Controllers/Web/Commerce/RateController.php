<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\MarketRateRequest;
use App\Http\Requests\Commerce\RateRequest;
use App\Models\Branch;
use App\Models\MetalRate;
use App\Models\MetalType;
use App\Models\Purity;
use App\Services\Commerce\MarketPriceUnavailable;
use App\Services\Commerce\MarketRateBoard;
use App\Services\Commerce\MetalPriceFeed;
use App\Services\Commerce\RateBook;
use App\Services\Foundation\NumberFormatService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RateController extends Controller
{
    public function index(MetalPriceFeed $feed, MarketRateBoard $board, NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('viewAny', MetalRate::class);
        $quote = null;
        $quoteError = null;

        try {
            $quote = $feed->current();
        } catch (MarketPriceUnavailable) {
            $quoteError = 'The market price could not be fetched. Enter the rate yourself.';
        }

        return view('commerce.rates.index', [
            'money' => fn (string $amount) => $format->money($amount, $context->company()),
            'rates' => MetalRate::query()->with(['metalType', 'purity', 'branch'])->orderByDesc('effective_at')->orderByDesc('id')->paginate(30),
            'metals' => MetalType::query()->active()->orderBy('name')->get(),
            'purities' => Purity::query()->with('metalType')->active()->whereHas('metalType', fn ($metals) => $metals->active())->orderBy('name')->get(),
            'branches' => Branch::query()->visibleTo(auth()->user())->orderBy('name')->get(),
            'canEnter' => auth()->user()->can('create', MetalRate::class),
            'quote' => $quote,
            'quoteError' => $quoteError,
            'suggestionGroups' => $quote
                ? collect($board->suggestions($quote))
                    ->groupBy(fn (array $row) => $row['purity']->metalType?->name ?? 'Metal')
                    ->map(fn ($rows) => $rows->sortByDesc(fn (array $row) => (float) $row['purity']->fineness)->values())
                : collect(),
        ]);
    }

    public function refresh(MetalPriceFeed $feed): RedirectResponse
    {
        $this->authorize('create', MetalRate::class);

        try {
            $feed->refresh();
        } catch (MarketPriceUnavailable) {
            return redirect()->route('rates.index')->withErrors([
                'market' => 'The market price could not be fetched. Enter the rate yourself.',
            ]);
        }

        return redirect()->route('rates.index')->with('status', 'Market price updated.');
    }

    public function storeMarket(MarketRateRequest $request, MarketRateBoard $board, CompanyContext $context): RedirectResponse
    {
        $saved = $board->save($context->company(), $request->validated('lines'), $request->user()?->id);

        return redirect()->route('rates.index')->with('status', $saved.' market rate'.($saved === 1 ? '' : 's').' saved. Older rates stay as they were.');
    }

    public function store(RateRequest $request, RateBook $rates, CompanyContext $context): RedirectResponse
    {
        $rates->record($context->company(), $request->validated(), $request->user()?->id);

        return redirect()->route('rates.index')->with('status', 'Rate saved. Older rates stay as they were.');
    }
}
