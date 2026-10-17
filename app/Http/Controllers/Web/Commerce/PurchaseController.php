<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Enums\PartyType;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\PurchaseRequest;
use App\Http\Requests\Commerce\ReceiptRequest;
use App\Models\Item;
use App\Models\Purchase;
use App\Services\Commerce\LedgerService;
use App\Services\Commerce\PurchaseService;
use App\Services\Foundation\NumberFormatService;
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    public function index(Request $request, NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('viewAny', Purchase::class);
        $search = trim((string) $request->query('search', ''));
        $like = '%'.addcslashes($search, '%_\\').'%';
        $company = $context->company();

        return view('commerce.purchases.index', [
            'search' => $search,
            'items' => Item::query()
                ->with(['metalType', 'purity', 'category'])
                ->where('own_gold', true)
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->where('item_code', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('huid', 'like', $like)))
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString(),
            'weight' => fn (string $amount) => $format->weight($amount, $company),
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        $this->authorize('create', Purchase::class);

        return redirect()->route('items.create', array_filter([
            'own' => 1,
            'like' => $request->query('like'),
        ]));
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
