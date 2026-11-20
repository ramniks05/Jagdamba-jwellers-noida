<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Enums\CustomerType;
use App\Enums\KycStatus;
use App\Enums\PartyType;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\CustomerRequest;
use App\Http\Requests\Commerce\ReceiptRequest;
use App\Models\Customer;
use App\Models\Society;
use App\Models\LedgerEntry;
use App\Services\Commerce\CustomerService;
use App\Services\Commerce\LedgerService;
use App\Services\Commerce\PaymentService;
use App\Services\Foundation\NumberFormatService;
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);
        $search = trim((string) $request->query('search', ''));
        $societyUuid = trim((string) $request->query('society', ''));
        $premium = (string) $request->query('premium', '');

        return view('commerce.customers.index', [
            'search' => $search,
            'societyUuid' => $societyUuid,
            'premium' => $premium,
            'societies' => Society::query()->orderBy('sort_order')->orderBy('name')->get(['uuid', 'name']),
            'customers' => Customer::query()
                ->with('society')
                ->when($search !== '', function ($query) use ($search) {
                    $like = '%'.addcslashes($search, '%_\\').'%';
                    $query->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('mobile', 'like', $like));
                })
                ->when($societyUuid !== '', fn ($query) => $query->whereHas('society', fn ($query) => $query->where('uuid', $societyUuid)))
                ->when($premium === 'yes', fn ($query) => $query->where('is_premium', true))
                ->when($premium === 'no', fn ($query) => $query->where('is_premium', false))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Society>
     */
    private function societyChoices(): \Illuminate\Database\Eloquent\Collection
    {
        return Society::query()->active()->orderBy('sort_order')->orderBy('name')->get(['uuid', 'name']);
    }

    public function create(): View
    {
        $this->authorize('create', Customer::class);

        return view('commerce.customers.form', [
            'customer' => new Customer(['is_active' => true, 'is_premium' => false, 'kyc_status' => KycStatus::Pending, 'customer_type' => CustomerType::Retail, 'country' => 'India']),
            'kyc' => KycStatus::cases(),
            'types' => CustomerType::cases(),
            'societies' => $this->societyChoices(),
        ]);
    }

    public function store(CustomerRequest $request, CustomerService $customers, CompanyContext $context): RedirectResponse|JsonResponse
    {
        $customer = $customers->create($context->company(), $request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'uuid' => $customer->uuid,
                'name' => $customer->name,
                'code' => $customer->code,
                'mobile' => $customer->mobile,
                'walkin' => (bool) $customer->is_system,
            ]);
        }

        return redirect()->route('customers.show', $customer)->with('status', 'Customer saved.');
    }

    public function show(Request $request, Customer $customer, LedgerService $ledger, NumberFormatService $format, CompanyContext $context): View|JsonResponse
    {
        $this->authorize('view', $customer);

        if ($request->expectsJson()) {
            $company = $context->company();
            $balance = $ledger->balance(PartyType::Customer, (int) $customer->id);
            $totals = $customer->sales()->pluck('total');
            $billed = $totals->reduce(fn (BigDecimal $sum, $total): BigDecimal => $sum->plus((string) $total), BigDecimal::zero());

            return response()->json([
                'name' => $customer->name,
                'code' => $customer->code,
                'mobile' => $customer->mobile,
                'email' => $customer->email,
                'type' => $customer->customer_type?->label(),
                'kyc' => $customer->kyc_status?->label(),
                'pan' => $customer->pan,
                'gstin' => $customer->gstin,
                'dob' => $customer->dob?->format('d M Y'),
                'anniversary' => $customer->anniversary?->format('d M Y'),
                'address' => collect([
                    $customer->address_line1,
                    $customer->address_line2,
                    collect([$customer->city, $customer->state, $customer->postal_code])->filter()->join(', '),
                ])->filter()->values(),
                'notes' => $customer->notes,
                'walkin' => (bool) $customer->is_system,
                'society' => $customer->society?->name,
                'is_premium' => (bool) $customer->is_premium,
                'balance' => $format->money((string) BigDecimal::of($balance)->abs(), $company),
                'balance_sign' => BigDecimal::of($balance)->getSign(),
                'bills_count' => $totals->count(),
                'bills_total' => $format->money((string) $billed->toScale(2), $company),
                'recent' => $customer->sales()->orderByDesc('sold_at')->limit(5)->get()->map(fn ($sale): array => [
                    'number' => $sale->number,
                    'when' => $sale->sold_at->timezone(config('app.timezone'))->format('d M Y'),
                    'total' => $format->money((string) $sale->total, $company),
                    'url' => route('sales.show', $sale),
                ]),
                'url' => route('customers.show', $customer),
                'edit_url' => $request->user()?->can('update', $customer) ? route('customers.edit', $customer) : null,
            ]);
        }

        $customer->loadMissing('society');

        return view('commerce.customers.show', [
            'customer' => $customer,
            'balance' => $ledger->balance(PartyType::Customer, (int) $customer->id),
            'entries' => LedgerEntry::query()->where('party_type', PartyType::Customer)->where('party_id', $customer->id)->orderByDesc('occurred_at')->orderByDesc('id')->limit(50)->get(),
            'sales' => $customer->sales()->orderByDesc('sold_at')->limit(20)->get(),
            'methods' => PaymentMethod::cases(),
        ]);
    }

    public function edit(Customer $customer): View
    {
        $this->authorize('update', $customer);

        $customer->loadMissing('society');

        return view('commerce.customers.form', [
            'customer' => $customer,
            'kyc' => KycStatus::cases(),
            'types' => CustomerType::cases(),
            'societies' => $this->societyChoices(),
        ]);
    }

    public function update(CustomerRequest $request, Customer $customer, CustomerService $customers): RedirectResponse
    {
        $customers->update($customer, $request->validated());

        return redirect()->route('customers.show', $customer)->with('status', 'Customer saved.');
    }

    public function destroy(Customer $customer, CustomerService $customers): RedirectResponse
    {
        $this->authorize('delete', $customer);
        $customers->delete($customer);

        return redirect()->route('customers.index')->with('status', 'Customer removed.');
    }

    public function payment(ReceiptRequest $request, Customer $customer, PaymentService $payments): RedirectResponse
    {
        $this->authorize('view', $customer);
        $payments->receive($customer, $request->validated(), $request->user()?->id);

        return redirect()->route('customers.show', $customer)->with('status', 'Receipt saved.');
    }
}
