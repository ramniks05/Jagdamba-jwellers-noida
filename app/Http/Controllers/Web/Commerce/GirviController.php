<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\GirviRequest;
use App\Http\Requests\Commerce\GirviSettleRequest;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\GirviPledge;
use App\Models\MetalRate;
use App\Models\MetalType;
use App\Services\Commerce\GirviPricer;
use App\Services\Commerce\GirviService;
use App\Services\Foundation\NumberFormatService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GirviController extends Controller
{
    public function index(NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('viewAny', GirviPledge::class);

        return view('commerce.girvi.index', [
            'pledges' => GirviPledge::query()->with('customer')->orderByDesc('pledged_at')->paginate(20),
            'money' => fn (string $amount) => $format->money($amount, $context->company()),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', GirviPledge::class);

        return view('commerce.girvi.create', [
            'customers' => Customer::query()->where('is_active', true)->where('is_system', false)->orderBy('name')->get(),
            'metals' => MetalType::query()->with('purities')->where('is_active', true)->orderBy('name')->get(),
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(),
            'rates' => MetalRate::query()->orderByDesc('effective_at')->orderByDesc('id')->get(['metal_type_id', 'purity_id', 'branch_id', 'rate_per_gram']),
            'branchId' => Branch::query()->where('is_head_office', true)->value('id'),
        ]);
    }

    public function store(GirviRequest $request, GirviService $girvi, CompanyContext $context): RedirectResponse
    {
        $pledge = $girvi->pledge($context->company(), $request->validated(), $request->user()?->id);

        return redirect()->route('girvi.show', $pledge)->with('status', 'Girvi '.$pledge->number.' saved.');
    }

    public function show(GirviPledge $pledge, NumberFormatService $format, CompanyContext $context, GirviPricer $pricer): View
    {
        $this->authorize('view', $pledge);
        $pledge->load(['customer', 'metalType', 'purity', 'items.metalType', 'items.purity']);
        $months = $pledge->status === 'open'
            ? $pricer->monthsBetween($pledge->interest_from, now())
            : 0;

        return view('commerce.girvi.show', [
            'pledge' => $pledge,
            'company' => $context->company(),
            'months' => $months,
            'interest' => $pledge->status === 'open'
                ? $pricer->interest((string) $pledge->principal, (string) $pledge->interest_percent, $months)
                : '0.00',
            'methods' => PaymentMethod::cases(),
            'money' => fn (string $amount) => $format->money($amount, $context->company()),
            'weight' => fn (string $amount) => $format->weight($amount, $context->company()),
        ]);
    }

    public function settle(GirviSettleRequest $request, GirviPledge $pledge, GirviService $girvi): RedirectResponse
    {
        $girvi->settle($pledge, $request->validated(), $request->user()?->id);
        $message = $request->validated('action') === 'release'
            ? 'Gold released.'
            : 'Interest saved. The gold stays in girvi.';

        return redirect()->route('girvi.show', $pledge)->with('status', $message);
    }
}
