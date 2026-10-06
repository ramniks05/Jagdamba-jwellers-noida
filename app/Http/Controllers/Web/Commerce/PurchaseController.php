<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Enums\ChargeAppliesTo;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\PurchaseRequest;
use App\Models\ChargeMethod;
use App\Models\MetalType;
use App\Models\Purchase;
use App\Models\StockLocation;
use App\Models\Supplier;
use App\Services\Commerce\PurchaseService;
use App\Services\Foundation\NumberFormatService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Purchase::class);

        return view('commerce.purchases.index', [
            'purchases' => Purchase::query()->with('supplier')->orderByDesc('purchased_at')->orderByDesc('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Purchase::class);

        return view('commerce.purchases.create', [
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'metals' => MetalType::query()->with('purities')->where('is_active', true)->orderBy('name')->get(),
            'locations' => StockLocation::query()->where('is_active', true)->orderBy('name')->get(),
            'making' => ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Making)->where('is_active', true)->orderBy('name')->get(),
            'wastage' => ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Wastage)->where('is_active', true)->orderBy('name')->get(),
            'methods' => PaymentMethod::cases(),
        ]);
    }

    public function store(PurchaseRequest $request, PurchaseService $purchases, CompanyContext $context): RedirectResponse
    {
        $purchase = $purchases->receive($context->company(), $request->validated(), $request->user()?->id);

        return redirect()->route('purchases.show', $purchase)->with('status', 'Purchase '.$purchase->number.' saved.');
    }

    public function show(Purchase $purchase, NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('view', $purchase);
        $purchase->load(['lines.item', 'supplier']);

        return view('commerce.purchases.show', [
            'purchase' => $purchase,
            'money' => fn (string $amount) => $format->money($amount, $context->company()),
            'weight' => fn (string $amount) => $format->weight($amount, $context->company()),
        ]);
    }

    public function returnToSupplier(Purchase $purchase, PurchaseService $purchases): RedirectResponse
    {
        $this->authorize('create', Purchase::class);
        $document = $purchases->sendBack($purchase, request()->user()?->id);

        return redirect()->route('purchases.show', $purchase)->with('status', 'Return '.$document->number.' saved.');
    }
}
