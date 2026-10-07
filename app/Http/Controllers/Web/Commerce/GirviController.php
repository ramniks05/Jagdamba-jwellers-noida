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
use App\Services\Foundation\SettingService;
use App\Support\CompanyContext;
use App\Support\CustomerShare;
use App\Support\RupeesInWords;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
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

    public function show(GirviPledge $pledge, NumberFormatService $format, CompanyContext $context, GirviPricer $pricer, SettingService $settings): View
    {
        $this->authorize('view', $pledge);
        $company = $context->company();
        $pledge->load(['customer', 'metalType', 'purity', 'items.metalType', 'items.purity', 'payments']);
        $months = $pledge->status === 'open'
            ? $pricer->monthsBetween($pledge->interest_from, now())
            : 0;
        $interest = $pledge->status === 'open'
            ? $pricer->interest((string) $pledge->principal, (string) $pledge->interest_percent, $months)
            : '0.00';
        $release = (string) BigDecimal::of((string) $pledge->principal)->plus($interest)->toScale(2, RoundingMode::HalfUp);
        $money = fn (string $amount) => $format->money($amount, $company);
        $weight = fn (string $amount) => $format->weight($amount, $company);
        $pieces = $pledge->items->isNotEmpty()
            ? $pledge->items
            : collect([(object) [
                'description' => $pledge->description,
                'metalType' => $pledge->metalType,
                'purity' => $pledge->purity,
                'gross_weight' => $pledge->gross_weight,
                'net_weight' => $pledge->net_weight,
                'rate_per_gram' => $pledge->rate_per_gram,
                'gold_value' => $pledge->gold_value,
            ]]);
        $pieceLines = $pieces->map(function ($item) use ($money, $weight) {
            $metal = trim(($item->metalType?->name).' '.($item->purity?->name));

            return $item->description."\n".$metal.' · '.$weight((string) $item->net_weight).' · '.$money((string) $item->rate_per_gram).'/g · '.$money((string) $item->gold_value);
        })->implode("\n");
        $weightLines = $pieces->groupBy(fn ($item) => trim(($item->metalType?->name).' '.($item->purity?->name)))
            ->map(function ($rows, $name) use ($weight) {
                $net = $rows->reduce(
                    fn (BigDecimal $sum, $item) => $sum->plus((string) $item->net_weight),
                    BigDecimal::zero(),
                )->toScale(3, RoundingMode::HalfUp);

                return $name.' weight '.$weight((string) $net);
            })->implode("\n");
        $share = $company->displayName()."\n"
            .'Girvi receipt '.$pledge->number."\n"
            .'Date '.$pledge->pledged_at?->timezone(config('app.timezone'))->format('d-m-Y')."\n"
            .'Customer '.($pledge->customer?->name)."\n\n"
            .$pieceLines."\n\n"
            .$weightLines."\n"
            .'Total value '.$money((string) $pledge->gold_value)."\n"
            .'Loan given '.$money((string) $pledge->principal)."\n"
            .'Interest '.rtrim(rtrim(number_format((float) $pledge->interest_percent, 2, '.', ''), '0'), '.')."% per month\n"
            .($pledge->status === 'open'
                ? 'To release the gold today '.$money($release)
                : 'Released '.($pledge->released_at?->timezone(config('app.timezone'))->format('d-m-Y') ?? ''))."\n\n"
            .'Please keep this receipt. The gold stays with the shop until the loan and the interest are paid.';

        return view('commerce.girvi.show', [
            'pledge' => $pledge,
            'company' => $company,
            'months' => $months,
            'interest' => $interest,
            'release' => $release,
            'loanWords' => RupeesInWords::format((string) $pledge->principal),
            'showLogo' => (bool) $settings->get('invoice.show_logo', $company),
            'footer' => (string) ($settings->get('invoice.footer_note', $company) ?? ''),
            'shareUrl' => CustomerShare::whatsapp($pledge->customer?->mobile, $share),
            'methods' => PaymentMethod::cases(),
            'money' => $money,
            'weight' => $weight,
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
