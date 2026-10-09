<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Enums\KycStatus;
use App\Enums\LedgerDirection;
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
use App\Services\Foundation\NumberFormatService;
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request, NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('viewAny', Supplier::class);
        $search = trim((string) $request->query('search', ''));
        $show = in_array($request->query('show'), ['owed', 'hidden'], true) ? $request->query('show') : 'all';
        $owedSql = '(select coalesce(sum(case when direction = ? then amount else -amount end), 0) from ledger_entries where party_type = ? and party_id = suppliers.id) > 0.004';
        $owedBindings = [LedgerDirection::Credit->value, PartyType::Supplier->value];
        $base = Supplier::query()->when($search !== '', function ($query) use ($search) {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn ($query) => $query->where('name', 'like', $like)
                ->orWhere('code', 'like', $like)
                ->orWhere('mobile', 'like', $like)
                ->orWhere('contact_name', 'like', $like)
                ->orWhere('gstin', 'like', $like));
        });

        $suppliers = (clone $base)
            ->withCount('purchases')
            ->when($show === 'owed', fn ($query) => $query->whereRaw($owedSql, $owedBindings))
            ->when($show === 'hidden', fn ($query) => $query->where('is_active', false))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $payable = LedgerEntry::query()
            ->where('party_type', PartyType::Supplier)
            ->whereIn('party_id', $suppliers->getCollection()->pluck('id'))
            ->selectRaw('party_id, sum(case when direction = ? then amount else -amount end) as payable', [LedgerDirection::Credit->value])
            ->groupBy('party_id')
            ->pluck('payable', 'party_id');
        $owedTotal = LedgerEntry::query()
            ->where('party_type', PartyType::Supplier)
            ->selectRaw('sum(case when direction = ? then amount else -amount end) as payable', [LedgerDirection::Credit->value])
            ->value('payable');

        return view('commerce.suppliers.index', [
            'search' => $search,
            'show' => $show,
            'counts' => [
                'all' => (clone $base)->count(),
                'owed' => (clone $base)->whereRaw($owedSql, $owedBindings)->count(),
                'hidden' => (clone $base)->where('is_active', false)->count(),
            ],
            'suppliers' => $suppliers,
            'payable' => $payable->map(fn ($amount) => (string) BigDecimal::of((string) round((float) $amount, 2))->toScale(2, RoundingMode::HalfUp)),
            'owedTotal' => (string) BigDecimal::of((string) round((float) $owedTotal, 2))->toScale(2, RoundingMode::HalfUp),
            'money' => fn (string $amount) => $format->money($amount, $context->company()),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Supplier::class);

        return view('commerce.suppliers.form', [
            'supplier' => new Supplier([
                'code' => $this->nextCode(),
                'is_active' => true,
                'kyc_status' => KycStatus::Pending,
                'country' => 'India',
            ]),
            'kyc' => KycStatus::cases(),
            'forPurchase' => $request->query('for') === 'purchase',
        ]);
    }

    public function store(SupplierRequest $request, SupplierService $suppliers, CompanyContext $context): RedirectResponse
    {
        $supplier = $suppliers->create($context->company(), $request->validated());

        if ($request->input('for') === 'purchase' && $supplier->is_active) {
            return redirect()->route('purchases.create', ['supplier' => $supplier->uuid])->with('status', 'Supplier '.$supplier->name.' saved. Now type their bill.');
        }

        return redirect()->route('suppliers.show', $supplier)->with('status', 'Supplier saved.');
    }

    public function show(Supplier $supplier, LedgerService $ledger, NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('view', $supplier);

        $payable = BigDecimal::of($ledger->balance(PartyType::Supplier, (int) $supplier->id))->negated()->toScale(2, RoundingMode::HalfUp);
        $entries = LedgerEntry::query()
            ->where('party_type', PartyType::Supplier)
            ->where('party_id', $supplier->id)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $running = $payable;
        $balances = [];

        foreach ($entries as $entry) {
            $balances[$entry->id] = (string) $running;
            $amount = BigDecimal::of((string) $entry->amount);
            $running = $entry->direction === LedgerDirection::Credit ? $running->minus($amount) : $running->plus($amount);
        }

        $purchases = $supplier->purchases()
            ->withCount('lines')
            ->withSum('returns', 'amount')
            ->orderByDesc('purchased_at')
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        return view('commerce.suppliers.show', [
            'supplier' => $supplier,
            'payable' => (string) $payable,
            'methods' => PaymentMethod::cases(),
            'entries' => $entries,
            'balances' => $balances,
            'purchases' => $purchases,
            'purchaseCount' => $supplier->purchases()->count(),
            'boughtTotal' => (string) BigDecimal::of((string) ($supplier->purchases()->sum('total') ?: '0'))->toScale(2, RoundingMode::HalfUp),
            'money' => fn (string $amount) => $format->money($amount, $context->company()),
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

    private function nextCode(): string
    {
        $number = Supplier::withTrashed()->count() + 1;

        do {
            $code = 'SUP'.str_pad((string) $number++, 3, '0', STR_PAD_LEFT);
        } while (Supplier::withTrashed()->where('code', $code)->exists());

        return $code;
    }
}
