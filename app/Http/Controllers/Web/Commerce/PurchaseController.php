<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Enums\ChargeAppliesTo;
use App\Enums\PartyType;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\PurchaseRequest;
use App\Http\Requests\Commerce\ReceiptRequest;
use App\Models\Category;
use App\Models\ChargeMethod;
use App\Models\MetalRate;
use App\Models\MetalType;
use App\Models\Purchase;
use App\Models\StockLocation;
use App\Models\Supplier;
use App\Services\Commerce\ItemService;
use App\Services\Commerce\LedgerService;
use App\Services\Commerce\PurchaseService;
use App\Services\Foundation\NumberFormatService;
use App\Services\Foundation\SettingService;
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    private const DUE_SQL = 'purchases.total - purchases.paid_amount - (select coalesce(sum(purchase_returns.amount), 0) from purchase_returns where purchase_returns.purchase_id = purchases.id)';

    public function index(Request $request, NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('viewAny', Purchase::class);
        $search = trim((string) $request->query('search', ''));
        $show = in_array($request->query('show'), ['due', 'paid'], true) ? $request->query('show') : 'all';
        $like = '%'.addcslashes($search, '%_\\').'%';
        $base = Purchase::query()->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
            ->where('number', 'like', $like)
            ->orWhere('supplier_bill_number', 'like', $like)
            ->orWhereHas('supplier', fn ($query) => $query->where('name', 'like', $like)->orWhere('mobile', 'like', $like))));

        return view('commerce.purchases.index', [
            'search' => $search,
            'show' => $show,
            'counts' => [
                'all' => (clone $base)->count(),
                'due' => (clone $base)->whereRaw(self::DUE_SQL.' > 0.004')->count(),
                'paid' => (clone $base)->whereRaw(self::DUE_SQL.' <= 0.004')->count(),
            ],
            'dueTotal' => (string) BigDecimal::of((string) ((clone $base)->whereRaw(self::DUE_SQL.' > 0.004')->selectRaw('sum('.self::DUE_SQL.') as due')->value('due') ?? '0'))->toScale(2, RoundingMode::HalfUp),
            'purchases' => (clone $base)
                ->with('supplier')
                ->withCount('lines')
                ->withSum('lines', 'gross_weight')
                ->withSum('returns', 'amount')
                ->when($show === 'due', fn ($query) => $query->whereRaw(self::DUE_SQL.' > 0.004'))
                ->when($show === 'paid', fn ($query) => $query->whereRaw(self::DUE_SQL.' <= 0.004'))
                ->orderByDesc('purchased_at')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString(),
            'money' => fn (string $amount) => $format->money($amount, $context->company()),
            'weight' => fn (string $amount) => $format->weight($amount, $context->company()),
        ]);
    }

    public function create(Request $request, SettingService $settings, ItemService $items, LedgerService $ledger, CompanyContext $context): View
    {
        $this->authorize('create', Purchase::class);
        $company = $context->company();
        $suppliers = Supplier::query()->where('is_active', true)->orderBy('name')->get();

        return view('commerce.purchases.create', [
            'suppliers' => $suppliers,
            'payable' => $suppliers->mapWithKeys(fn (Supplier $supplier) => [
                $supplier->uuid => (string) BigDecimal::of($ledger->balance(PartyType::Supplier, (int) $supplier->id))->negated()->toScale(2, RoundingMode::HalfUp),
            ]),
            'chosenSupplier' => (string) $request->query('supplier', ''),
            'nextCode' => $items->nextCode(),
            'metals' => MetalType::query()->with(['purities' => fn ($purities) => $purities->active()])->active()->orderBy('name')->get(),
            'categories' => Category::query()->with('parent')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'locations' => StockLocation::query()->with('branch')->where('is_active', true)->orderBy('name')->get(),
            'making' => ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Making)->where('is_active', true)->orderBy('name')->get(),
            'wastage' => ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Wastage)->where('is_active', true)->orderBy('name')->get(),
            'methods' => PaymentMethod::cases(),
            'rates' => MetalRate::query()->orderByDesc('effective_at')->orderByDesc('id')->get(['metal_type_id', 'purity_id', 'branch_id', 'rate_per_gram']),
            'gstPercent' => (string) ($settings->get('pricing.gst_percent', $company) ?? '0'),
            'roundRupee' => (bool) $settings->get('pricing.round_rupee', $company),
        ]);
    }

    public function store(PurchaseRequest $request, PurchaseService $purchases, CompanyContext $context): RedirectResponse
    {
        $purchase = $purchases->receive($context->company(), $request->validated(), $request->user()?->id);
        $count = $purchase->lines->count();

        return redirect()->route('purchases.show', $purchase)->with('status', 'Purchase '.$purchase->number.' saved. '.$count.' '.($count === 1 ? 'piece' : 'pieces').' added to stock.');
    }

    public function show(Purchase $purchase, NumberFormatService $format, LedgerService $ledger, CompanyContext $context): View
    {
        $this->authorize('view', $purchase);
        $purchase->load(['lines.item.metalType', 'lines.item.purity', 'lines.item.category', 'lines.returnLine.purchaseReturn', 'supplier', 'payments', 'returns']);

        return view('commerce.purchases.show', [
            'purchase' => $purchase,
            'payable' => (string) BigDecimal::of($ledger->balance(PartyType::Supplier, (int) $purchase->supplier_id))->negated()->toScale(2, RoundingMode::HalfUp),
            'methods' => PaymentMethod::cases(),
            'money' => fn (string $amount) => $format->money($amount, $context->company()),
            'weight' => fn (string $amount) => $format->weight($amount, $context->company()),
        ]);
    }

    public function pay(ReceiptRequest $request, Purchase $purchase, PurchaseService $purchases): RedirectResponse
    {
        $this->authorize('create', Purchase::class);
        $payment = $purchases->pay($purchase, $request->validated(), $request->user()?->id);

        return redirect()->route('purchases.show', $purchase)->with('status', 'Payment '.$payment->number.' saved.');
    }

    public function returnToSupplier(Request $request, Purchase $purchase, PurchaseService $purchases): RedirectResponse
    {
        $this->authorize('create', Purchase::class);
        $lines = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*' => ['uuid'],
        ], [
            'lines.required' => 'Tick the pieces that go back to the supplier.',
        ])['lines'];
        $document = $purchases->sendBack($purchase, array_values($lines), $request->user()?->id);

        return redirect()->route('purchases.show', $purchase)->with('status', 'Return '.$document->number.' saved. The pieces are out of stock and the supplier balance is reduced.');
    }
}
