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
use App\Models\LedgerEntry;
use App\Services\Commerce\CustomerService;
use App\Services\Commerce\LedgerService;
use App\Services\Commerce\PaymentService;
use App\Support\CompanyContext;
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

        return view('commerce.customers.index', [
            'search' => $search,
            'customers' => Customer::query()
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
        $this->authorize('create', Customer::class);

        return view('commerce.customers.form', [
            'customer' => new Customer(['is_active' => true, 'kyc_status' => KycStatus::Pending, 'customer_type' => CustomerType::Retail, 'country' => 'India']),
            'kyc' => KycStatus::cases(),
            'types' => CustomerType::cases(),
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

    public function show(Customer $customer, LedgerService $ledger): View
    {
        $this->authorize('view', $customer);

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

        return view('commerce.customers.form', [
            'customer' => $customer,
            'kyc' => KycStatus::cases(),
            'types' => CustomerType::cases(),
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
