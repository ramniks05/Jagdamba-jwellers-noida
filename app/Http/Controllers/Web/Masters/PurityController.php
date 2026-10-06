<?php

namespace App\Http\Controllers\Web\Masters;

use App\Http\Controllers\Controller;
use App\Http\Requests\Masters\PurityRequest;
use App\Models\MetalType;
use App\Models\Purity;
use App\Services\Masters\Fineness;
use App\Services\Masters\PurityService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurityController extends Controller
{
    public function index(Request $request, MetalType $metal): View
    {
        $this->authorize('viewAny', Purity::class);
        $search = trim((string) $request->query('search', ''));

        return view('masters.purities.index', [
            'metal' => $metal,
            'search' => $search,
            'purities' => $metal->purities()->matching($search)->orderBy('sort_order')->orderBy('name')->paginate(20)->withQueryString(),
        ]);
    }

    public function create(MetalType $metal): View
    {
        $this->authorize('create', Purity::class);

        return view('masters.purities.form', [
            'metal' => $metal,
            'purity' => new Purity(['is_active' => true, 'sort_order' => 0]),
            'finenessPercent' => '',
        ]);
    }

    public function store(PurityRequest $request, MetalType $metal, PurityService $purities, CompanyContext $context): RedirectResponse
    {
        $purities->create($context->company(), $metal, $request->validated());

        return redirect()->route('metals.purities.index', $metal)->with('status', 'Purity saved.');
    }

    public function edit(MetalType $metal, Purity $purity): View
    {
        $this->ensureMetal($metal, $purity);
        $this->authorize('update', $purity);

        return view('masters.purities.form', [
            'metal' => $metal,
            'purity' => $purity,
            'finenessPercent' => Fineness::percentFromRatio((string) $purity->fineness),
        ]);
    }

    public function update(PurityRequest $request, MetalType $metal, Purity $purity, PurityService $purities): RedirectResponse
    {
        $this->ensureMetal($metal, $purity);
        $purities->update($purity, $request->validated());

        return redirect()->route('metals.purities.index', $metal)->with('status', 'Purity saved.');
    }

    public function destroy(MetalType $metal, Purity $purity, PurityService $purities): RedirectResponse
    {
        $this->ensureMetal($metal, $purity);
        $this->authorize('delete', $purity);
        $purities->delete($purity);

        return redirect()->route('metals.purities.index', $metal)->with('status', 'Purity removed.');
    }

    private function ensureMetal(MetalType $metal, Purity $purity): void
    {
        abort_unless((int) $purity->metal_type_id === (int) $metal->id, 404);
    }
}
