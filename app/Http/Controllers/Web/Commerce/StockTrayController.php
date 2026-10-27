<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\StockTrayRequest;
use App\Models\StockTray;
use App\Services\Commerce\TrayService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockTrayController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', StockTray::class);
        $search = trim((string) $request->query('search', ''));
        $show = in_array($request->query('show'), ['active', 'hidden'], true) ? $request->query('show') : 'all';
        $like = '%'.addcslashes($search, '%_\\').'%';

        return view('commerce.trays.index', [
            'search' => $search,
            'show' => $show,
            'trays' => StockTray::query()
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('code', 'like', $like)))
                ->when($show !== 'all', fn ($query) => $query->where('is_active', $show === 'active'))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(30)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', StockTray::class);

        return view('commerce.trays.form', [
            'tray' => new StockTray(['is_active' => true, 'sort_order' => 0]),
        ]);
    }

    public function store(StockTrayRequest $request, TrayService $trays, CompanyContext $context): RedirectResponse
    {
        $trays->create($context->company(), $request->validated(), $request->user()?->id);

        return redirect()->route('trays.index')->with('status', 'Tray saved.');
    }

    public function edit(StockTray $tray): View
    {
        $this->authorize('update', $tray);

        return view('commerce.trays.form', [
            'tray' => $tray,
        ]);
    }

    public function update(StockTrayRequest $request, StockTray $tray, TrayService $trays): RedirectResponse
    {
        $trays->update($tray, $request->validated(), $request->user()?->id);

        return redirect()->route('trays.index')->with('status', 'Tray saved.');
    }

    public function destroy(StockTray $tray, TrayService $trays): RedirectResponse
    {
        $this->authorize('delete', $tray);
        $trays->delete($tray);

        return redirect()->route('trays.index')->with('status', 'Tray removed.');
    }
}
