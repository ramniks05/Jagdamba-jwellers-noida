<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\RateRequest;
use App\Models\Branch;
use App\Models\MetalRate;
use App\Models\MetalType;
use App\Models\Purity;
use App\Services\Commerce\RateBook;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RateController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', MetalRate::class);

        return view('commerce.rates.index', [
            'rates' => MetalRate::query()->with(['metalType', 'purity', 'branch'])->orderByDesc('effective_at')->orderByDesc('id')->paginate(30),
            'metals' => MetalType::query()->orderBy('name')->get(),
            'purities' => Purity::query()->with('metalType')->orderBy('name')->get(),
            'branches' => Branch::query()->visibleTo(auth()->user())->orderBy('name')->get(),
            'canEnter' => auth()->user()->can('create', MetalRate::class),
        ]);
    }

    public function store(RateRequest $request, RateBook $rates, CompanyContext $context): RedirectResponse
    {
        $rates->record($context->company(), $request->validated(), $request->user()?->id);

        return redirect()->route('rates.index')->with('status', 'Rate saved. Older rates stay as they were.');
    }
}
