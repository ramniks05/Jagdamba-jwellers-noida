<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Enums\ItemStatus;
use App\Enums\LocationKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\LocationRequest;
use App\Models\Branch;
use App\Models\StockLocation;
use App\Services\Commerce\LocationService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', StockLocation::class);
        $search = trim((string) $request->query('search', ''));
        $show = in_array($request->query('show'), ['active', 'hidden'], true) ? $request->query('show') : 'all';
        $like = '%'.addcslashes($search, '%_\\').'%';

        return view('commerce.locations.index', [
            'search' => $search,
            'show' => $show,
            'locations' => StockLocation::query()
                ->with(['branch', 'parent'])
                ->withCount(['items as stock_count' => fn ($items) => $items->whereIn('status', [ItemStatus::Available, ItemStatus::Reserved])])
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('code', 'like', $like)))
                ->when($show !== 'all', fn ($query) => $query->where('is_active', $show === 'active'))
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->paginate(30)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', StockLocation::class);

        return view('commerce.locations.form', [
            'location' => new StockLocation(['is_active' => true, 'kind' => LocationKind::Rack]),
            'branches' => Branch::query()->visibleTo(auth()->user())->orderBy('name')->get(),
            'parents' => StockLocation::query()->orderBy('name')->get(),
            'kinds' => LocationKind::cases(),
        ]);
    }

    public function store(LocationRequest $request, LocationService $locations, CompanyContext $context): RedirectResponse
    {
        $locations->create($context->company(), $request->validated());

        return redirect()->route('locations.index')->with('status', 'Location saved.');
    }

    public function edit(StockLocation $location): View
    {
        $this->authorize('update', $location);

        return view('commerce.locations.form', [
            'location' => $location->load(['branch', 'parent']),
            'branches' => Branch::query()->visibleTo(auth()->user())->orderBy('name')->get(),
            'parents' => StockLocation::query()->whereKeyNot($location->id)->orderBy('name')->get(),
            'kinds' => LocationKind::cases(),
        ]);
    }

    public function update(LocationRequest $request, StockLocation $location, LocationService $locations): RedirectResponse
    {
        $locations->update($location, $request->validated());

        return redirect()->route('locations.index')->with('status', 'Location saved.');
    }
}
