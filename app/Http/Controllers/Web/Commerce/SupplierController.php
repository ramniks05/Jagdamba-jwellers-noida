<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Enums\KycStatus;
use App\Enums\PartyType;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\ReceiptRequest;
use App\Http\Requests\Commerce\SupplierRequest;
use App\Models\LedgerEntry;
use App\Models\Supplier;
use App\Services\Commerce\LedgerService;
use App\Services\Commerce\PaymentService;
use App\Services\Commerce\SupplierService;
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Supplier::class);
        $search = trim((string) $request->query('search', ''));

        return view('commerce.suppliers.index', [
            'search' => $search,
            'suppliers' => Supplier::query()
                ->when($search !== '', function ($query) use ($search) {
                    $like = '%'.addcslashes($search, '%_\\').'%';
                    $query->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('mobile', 'like', $like));
                })
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Supplier::class);

        return view('commerce.suppliers.form', [
            'supplier' => new Supplier(['is_active' => true, 'kyc_status' => KycStatus::Pending, 'country' => 'India']),
            'kyc' => KycStatus::cases(),
        ]);
    }

    public function store(SupplierRequest $request, SupplierService $suppliers, CompanyContext $context): RedirectResponse
    {
        $supplier = $suppliers->create($context->company(), $request->validated());

        return redirect()->route('suppliers.show', $supplier)->with('status', 'Supplier saved.');
    }

    public function show(Supplier $supplier, LedgerService $ledger): View
    {
        $this->authorize('view', $supplier);

        $balance = BigDecimal::of($ledger->balance(PartyType::Supplier, (int) $supplier->id));

        return view('commerce.suppliers.show', [
            'supplier' => $supplier,
            'payable' => (string) $balance->negated()->toScale(2, RoundingMode::HalfUp),
            'methods' => PaymentMethod::cases(),
            'entries' => LedgerEntry::query()->where('party_type', PartyType::Supplier)->where('party_id', $supplier->id)->orderByDesc('occurred_at')->limit(50)->get(),
        ]);
    }

    public function edit(Supplier $supplier): View
    {
        $this->authorize('update', $supplier);

        return view('commerce.suppliers.form', [
            'supplier' => $supplier,
            'kyc' => KycStatus::cases(),
        ]);
    }

    public function update(SupplierRequest $request, Supplier $supplier, SupplierService $suppliers): RedirectResponse
    {
        $suppliers->update($supplier, $request->validated());

        return redirect()->route('suppliers.show', $supplier)->with('status', 'Supplier saved.');
    }

    public function payment(ReceiptRequest $request, Supplier $supplier, PaymentService $payments): RedirectResponse
    {
        $payments->paySupplier($supplier, $request->validated(), $request->user()?->id);

        return redirect()->route('suppliers.show', $supplier)->with('status', 'Payment saved.');
    }

    public function destroy(Supplier $supplier, SupplierService $suppliers): RedirectResponse
    {
        $this->authorize('delete', $supplier);
        $suppliers->delete($supplier);

        return redirect()->route('suppliers.index')->with('status', 'Supplier removed.');
    }
}
