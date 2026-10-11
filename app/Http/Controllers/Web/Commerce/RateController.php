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
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class RateController extends Controller
{
    public function index(Request $request, MetalPriceFeed $feed, MarketRateBoard $board, NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('viewAny', MetalRate::class);
        $view = $request->query('view') === 'history' ? 'history' : 'current';
        $purities = Purity::query()->with('metalType')->active()->whereHas('metalType', fn ($metals) => $metals->active())->orderBy('name')->get();
        $filter = [
            'purity' => (string) $request->query('purity', ''),
            'from' => $this->dateQuery($request->query('from')),
            'to' => $this->dateQuery($request->query('to')),
        ];
        $quote = null;
        $quoteError = null;

        try {
            $quote = $feed->current();
        } catch (MarketPriceUnavailable) {
            $quoteError = 'The market price could not be fetched. Enter the rate yourself.';
        }

        return view('commerce.rates.index', [
            'money' => fn (string $amount) => $format->money($amount, $context->company()),
            'view' => $view,
            'filter' => $filter,
            'current' => $view === 'current' ? $this->currentRates() : collect(),
            'rates' => $view === 'history' ? $this->history($filter) : null,
            'metals' => MetalType::query()->active()->orderBy('name')->get(),
            'purities' => $purities,
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

    /**
     * The rate in force now for each metal, purity and branch, which is what bills use.
     *
     * @return Collection<int, MetalRate>
     */
    private function currentRates(): Collection
    {
        return MetalRate::query()
            ->with(['metalType', 'purity', 'branch'])
            ->inForce()
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->get()
            ->unique(fn (MetalRate $rate) => $rate->metal_type_id.'|'.$rate->purity_id.'|'.($rate->branch_id ?? 'shop'))
            ->sortBy([
                fn (MetalRate $a, MetalRate $b) => strcmp((string) $a->metalType?->name, (string) $b->metalType?->name),
                fn (MetalRate $a, MetalRate $b) => (float) $b->purity?->fineness <=> (float) $a->purity?->fineness,
                fn (MetalRate $a, MetalRate $b) => strcmp((string) $a->branch?->name, (string) $b->branch?->name),
            ])
            ->values();
    }

    /**
     * @param  array{purity: string, from: ?string, to: ?string}  $filter
     */
    private function history(array $filter): LengthAwarePaginator
    {
        return MetalRate::query()
            ->with(['metalType', 'purity', 'branch'])
            ->when($filter['purity'] !== '', fn ($query) => $query->whereHas('purity', fn ($purities) => $purities->where('uuid', $filter['purity'])))
            ->when($filter['from'], fn ($query, $from) => $query->whereDate('effective_at', '>=', $from))
            ->when($filter['to'], fn ($query, $to) => $query->whereDate('effective_at', '<=', $to))
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();
    }

    private function dateQuery(mixed $value): ?string
    {
        $date = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : null;
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
