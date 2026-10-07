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
use App\Services\Commerce\SchemeBenefit;
use App\Services\Commerce\SchemeService;
use App\Services\Foundation\NumberFormatService;
use App\Services\Foundation\SettingService;
use App\Support\CompanyContext;
use App\Support\CustomerShare;
use App\Support\RupeesInWords;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SchemeController extends Controller
{
    public function index(NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('viewAny', GoldScheme::class);

        return view('commerce.schemes.index', [
            'schemes' => GoldScheme::query()->withCount('enrollments')->orderBy('name')->paginate(20),
            'bonuses' => config('schemes.bonus_types'),
            'money' => fn (string $amount) => $format->money($amount, $context->company()),
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

    public function show(GoldScheme $scheme, NumberFormatService $format, CompanyContext $context, SchemeBenefit $benefit, SettingService $settings): View
    {
        $this->authorize('view', $scheme);
        $company = $context->company();
        $scheme->load('enrollments.customer');
        $money = fn (string $amount) => $format->money($amount, $company);
        $payable = $this->fixedPayable($scheme);
        $maturity = $this->fixedMaturity($scheme, $benefit);
        $share = $company->displayName()."\n"
            .'Scheme '.$scheme->name."\n"
            .$scheme->duration_months.' months'
            .($scheme->monthly_amount !== null ? ' · '.$money((string) $scheme->monthly_amount).' each month' : '')."\n"
            .'Customer pays '.($payable !== null ? $money($payable) : 'the amount chosen each month')."\n"
            .'Customer gets '.($maturity !== null ? $money($maturity) : 'the closing amount after every month is paid');

        return view('commerce.schemes.show', [
            'scheme' => $scheme,
            'company' => $company,
            'customers' => Customer::query()->where('is_active', true)->where('is_system', false)->orderBy('name')->get(),
            'bonuses' => config('schemes.bonus_types'),
            'maturity' => $maturity,
            'payable' => $payable,
            'maturityWords' => $maturity !== null ? RupeesInWords::format($maturity) : null,
            'showLogo' => (bool) $settings->get('invoice.show_logo', $company),
            'footer' => (string) ($settings->get('invoice.footer_note', $company) ?? ''),
            'shareUrl' => CustomerShare::whatsapp(null, $share),
            'money' => $money,
        ]);
    }

    public function enroll(SchemeEnrollRequest $request, GoldScheme $scheme, SchemeService $schemes): RedirectResponse
    {
        $customer = Customer::query()->where('uuid', $request->validated('customer_uuid'))->firstOrFail();
        $enrollment = $schemes->enroll($scheme, $customer);

        return redirect()->route('enrollments.show', $enrollment)->with('status', 'Customer enrolled.');
    }

    public function enrollment(SchemeEnrollment $enrollment, NumberFormatService $format, CompanyContext $context, SchemeBenefit $benefit, SettingService $settings): View
    {
        $this->authorize('view', $enrollment);
        $enrollment->load(['scheme', 'customer', 'installments']);
        $installments = $enrollment->installments->sortBy([['due_on', 'asc'], ['id', 'asc']])->values();
        $enrollment->setRelation('installments', $installments);
        $paid = $installments->whereNotNull('paid_at');
        $collected = $paid->reduce(
            fn (BigDecimal $sum, $row) => $sum->plus((string) $row->amount),
            BigDecimal::zero(),
        )->toScale(2, RoundingMode::HalfUp);
        $closing = $installments->isNotEmpty() && $paid->count() === $installments->count()
            ? $benefit->maturity(
                (string) $collected,
                (string) ($enrollment->scheme->monthly_amount ?? '0'),
                $enrollment->scheme->bonus_type,
                (string) $enrollment->scheme->bonus_value,
            )
            : null;

        $company = $context->company();
        $money = fn (string $amount) => $format->money($amount, $company);
        $payable = $this->fixedPayable($enrollment->scheme);
        $maturity = $this->fixedMaturity($enrollment->scheme, $benefit);
        $gets = $enrollment->status === 'matured'
            ? (string) $enrollment->maturity_amount
            : ($closing ?? $maturity);
        $share = $company->displayName()."\n"
            .'Scheme '.$enrollment->scheme?->name."\n"
            .'Passbook '.$enrollment->number."\n"
            .'Customer '.($enrollment->customer?->name)."\n"
            .'Paid '.$paid->count().' of '.$installments->count()."\n"
            .'Collected '.$money((string) $collected)."\n"
            .'Customer gets '.($gets !== null ? $money($gets) : 'the closing amount after every month is paid');

        return view('commerce.schemes.enrollment', [
            'enrollment' => $enrollment,
            'company' => $company,
            'nextInstallment' => $installments->first(fn ($row) => $row->paid_at === null),
            'paidCount' => $paid->count(),
            'collected' => (string) $collected,
            'closing' => $closing,
            'maturity' => $maturity,
            'payable' => $payable,
            'getsWords' => $gets !== null ? RupeesInWords::format($gets) : null,
            'showLogo' => (bool) $settings->get('invoice.show_logo', $company),
            'footer' => (string) ($settings->get('invoice.footer_note', $company) ?? ''),
            'shareUrl' => CustomerShare::whatsapp($enrollment->customer?->mobile, $share),
            'methods' => PaymentMethod::cases(),
            'money' => $money,
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

    private function fixedPayable(?GoldScheme $scheme): ?string
    {
        if (! $scheme || $scheme->installment_mode !== 'fixed' || $scheme->monthly_amount === null) {
            return null;
        }

        return (string) BigDecimal::of((string) $scheme->monthly_amount)
            ->multipliedBy((int) $scheme->duration_months)
            ->toScale(2, RoundingMode::HalfUp);
    }

    private function fixedMaturity(?GoldScheme $scheme, SchemeBenefit $benefit): ?string
    {
        if (! $scheme || $scheme->installment_mode !== 'fixed' || $scheme->monthly_amount === null) {
            return null;
        }

        $paid = BigDecimal::of((string) $scheme->monthly_amount)
            ->multipliedBy((int) $scheme->duration_months)
            ->toScale(2, RoundingMode::HalfUp);

        return $benefit->maturity(
            (string) $paid,
            (string) $scheme->monthly_amount,
            $scheme->bonus_type,
            (string) $scheme->bonus_value,
        );
    }
}
