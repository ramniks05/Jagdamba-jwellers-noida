<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\SchemeEnrollRequest;
use App\Http\Requests\Commerce\SchemeInstallmentRequest;
use App\Http\Requests\Commerce\SchemeRequest;
use App\Models\Customer;
use App\Models\GoldScheme;
use App\Models\SchemeEnrollment;
use App\Services\Commerce\SchemeService;
use App\Services\Foundation\NumberFormatService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SchemeController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', GoldScheme::class);

        return view('commerce.schemes.index', [
            'schemes' => GoldScheme::query()->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', GoldScheme::class);

        return view('commerce.schemes.create', [
            'bonuses' => config('schemes.bonus_types'),
        ]);
    }

    public function store(SchemeRequest $request, SchemeService $schemes, CompanyContext $context): RedirectResponse
    {
        $scheme = $schemes->create($context->company(), $request->validated());

        return redirect()->route('schemes.show', $scheme)->with('status', 'Scheme saved.');
    }

    public function show(GoldScheme $scheme): View
    {
        $this->authorize('view', $scheme);

        return view('commerce.schemes.show', [
            'scheme' => $scheme->load('enrollments.customer'),
            'customers' => Customer::query()->where('is_active', true)->where('is_system', false)->orderBy('name')->get(),
            'bonuses' => config('schemes.bonus_types'),
        ]);
    }

    public function enroll(SchemeEnrollRequest $request, GoldScheme $scheme, SchemeService $schemes): RedirectResponse
    {
        $customer = Customer::query()->where('uuid', $request->validated('customer_uuid'))->firstOrFail();
        $enrollment = $schemes->enroll($scheme, $customer);

        return redirect()->route('enrollments.show', $enrollment)->with('status', 'Customer enrolled.');
    }

    public function enrollment(SchemeEnrollment $enrollment, NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('view', $enrollment);
        $enrollment->load(['scheme', 'customer', 'installments']);

        return view('commerce.schemes.enrollment', [
            'enrollment' => $enrollment,
            'methods' => PaymentMethod::cases(),
            'money' => fn (string $amount) => $format->money($amount, $context->company()),
        ]);
    }

    public function installment(SchemeInstallmentRequest $request, SchemeEnrollment $enrollment, SchemeService $schemes): RedirectResponse
    {
        $schemes->pay($enrollment, $request->validated(), $request->user()?->id);

        return redirect()->route('enrollments.show', $enrollment)->with('status', 'Installment saved.');
    }

    public function mature(SchemeEnrollment $enrollment, SchemeService $schemes): RedirectResponse
    {
        $this->authorize('update', $enrollment);
        $schemes->mature($enrollment, request()->user()?->id);

        return redirect()->route('enrollments.show', $enrollment)->with('status', 'Scheme matured.');
    }
}
