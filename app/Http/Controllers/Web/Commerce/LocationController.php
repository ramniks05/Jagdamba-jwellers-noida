<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Enums\LocationKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\LocationRequest;
use App\Models\Branch;
use App\Models\StockLocation;
use App\Services\Commerce\LocationService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', StockLocation::class);

        return view('commerce.locations.index', [
            'locations' => StockLocation::query()->with(['branch', 'parent'])->withCount('items')->orderBy('name')->paginate(30),
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
