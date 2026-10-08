<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Enums\ChargeAppliesTo;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\AdvanceOrderActionRequest;
use App\Http\Requests\Commerce\AdvanceOrderRequest;
use App\Models\AdvanceOrder;
use App\Models\Branch;
use App\Models\Category;
use App\Models\ChargeMethod;
use App\Models\Customer;
use App\Models\MetalRate;
use App\Models\MetalType;
use App\Services\Commerce\AdvanceOrderService;
use App\Services\Foundation\NumberFormatService;
use App\Services\Foundation\SettingService;
use App\Support\CompanyContext;
use App\Support\CustomerShare;
use App\Support\RupeesInWords;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdvanceOrderController extends Controller
{
    public function index(Request $request, NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('viewAny', AdvanceOrder::class);
        $show = in_array($request->query('show'), ['open', 'delivered', 'cancelled', 'all'], true) ? $request->query('show') : 'open';

        return view('commerce.orders.index', [
            'orders' => AdvanceOrder::query()
                ->with(['customer', 'metalType', 'purity'])
                ->when($show === 'open', fn ($query) => $query->whereIn('status', AdvanceOrder::OPEN))
                ->when(in_array($show, ['delivered', 'cancelled'], true), fn ($query) => $query->where('status', $show))
                ->orderByRaw('due_on is null')
                ->orderBy('due_on')
                ->orderByDesc('booked_at')
                ->paginate(20)
                ->withQueryString(),
            'show' => $show,
            'money' => fn (string $amount) => $format->money($amount, $context->company()),
            'weight' => fn (string $amount) => $format->weight($amount, $context->company()),
        ]);
    }

    public function create(SettingService $settings, CompanyContext $context): View
    {
        $this->authorize('create', AdvanceOrder::class);
        $company = $context->company();

        return view('commerce.orders.create', [
            'customers' => Customer::query()->where('is_active', true)->where('is_system', false)->orderBy('name')->get(),
            'metals' => MetalType::query()->with('purities')->where('is_active', true)->orderBy('name')->get(),
            'categories' => Category::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'making' => ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Making)->where('is_active', true)->orderBy('name')->get(),
            'rates' => MetalRate::query()->orderByDesc('effective_at')->orderByDesc('id')->get(['metal_type_id', 'purity_id', 'branch_id', 'rate_per_gram']),
            'branchId' => Branch::query()->where('is_head_office', true)->value('id'),
            'methods' => PaymentMethod::cases(),
            'gstPercent' => (string) ($settings->get('pricing.gst_percent', $company) ?? '0'),
            'makingMode' => (string) ($settings->get('pricing.making_mode', $company) ?? 'inside'),
            'makingGstPercent' => (string) ($settings->get('pricing.making_gst_percent', $company) ?? '0'),
            'taxExclusive' => $settings->get('invoice.tax_display', $company) !== 'inclusive',
            'roundRupee' => (bool) $settings->get('pricing.round_rupee', $company),
        ]);
    }

    public function store(AdvanceOrderRequest $request, AdvanceOrderService $orders, CompanyContext $context): RedirectResponse
    {
        $order = $orders->book($context->company(), $request->validated(), $request->user()?->id);

        return redirect()->route('orders.show', $order)->with('status', 'Order '.$order->number.' booked.');
    }

    public function show(AdvanceOrder $order, AdvanceOrderService $orders, NumberFormatService $format, CompanyContext $context, SettingService $settings): View
    {
        $this->authorize('view', $order);
        $company = $context->company();
        $order->load(['customer', 'metalType', 'purity', 'payments', 'sale']);
        $money = fn (string $amount) => $format->money($amount, $company);
        $weight = fn (string $amount) => $format->weight($amount, $company);
        $estimate = $orders->estimate($order, $company);
        $metal = trim(($order->metalType?->name).' '.($order->purity?->name));
        $share = $company->displayName()."\n"
            .'Order booking '.$order->number."\n"
            .'Date '.$order->booked_at?->timezone(config('app.timezone'))->format('d-m-Y')."\n"
            .'Customer '.($order->customer?->name)."\n\n"
            .$order->description."\n"
            .$metal.' about '.$weight((string) $order->expected_weight)."\n"
            .'Rate locked '.$money((string) $order->rate_per_gram).' per gram'."\n"
            .($order->due_on ? 'Delivery by '.$order->due_on->format('d-m-Y')."\n" : '')
            ."\n".'Estimated bill '.$money($estimate['total'])."\n"
            .'Advance paid '.$money($order->advanceHeld())."\n"
            .'Estimated balance '.$money($estimate['balance'])."\n\n"
            .'The final bill uses the actual weight at the locked rate. Please bring this slip at delivery.';

        return view('commerce.orders.show', [
            'order' => $order,
            'company' => $company,
            'estimate' => $estimate,
            'advanceWords' => RupeesInWords::format($order->advanceHeld()),
            'showLogo' => (bool) $settings->get('invoice.show_logo', $company),
            'footer' => (string) ($settings->get('invoice.footer_note', $company) ?? ''),
            'shareUrl' => CustomerShare::whatsapp($order->customer?->mobile, $share),
            'methods' => PaymentMethod::cases(),
            'gstPercent' => (string) ($settings->get('pricing.gst_percent', $company) ?? '0'),
            'makingGstPercent' => (string) ($settings->get('pricing.making_gst_percent', $company) ?? '0'),
            'money' => $money,
            'weight' => $weight,
        ]);
    }

    public function advance(AdvanceOrderActionRequest $request, AdvanceOrder $order, AdvanceOrderService $orders): RedirectResponse
    {
        $orders->addAdvance($order, $request->validated(), $request->user()?->id);

        return redirect()->route('orders.show', $order)->with('status', 'Advance saved.');
    }

    public function ready(AdvanceOrderActionRequest $request, AdvanceOrder $order, AdvanceOrderService $orders): RedirectResponse
    {
        $orders->markReady($order);

        return redirect()->route('orders.show', $order)->with('status', 'Order marked ready. Call the customer.');
    }

    public function cancel(AdvanceOrderActionRequest $request, AdvanceOrder $order, AdvanceOrderService $orders): RedirectResponse
    {
        $order = $orders->cancel($order, $request->validated(), $request->user()?->id);
        $left = (float) $order->advanceHeld() > 0 ? ' The rest of the advance stays on the customer’s account.' : '';

        return redirect()->route('orders.show', $order)->with('status', 'Order cancelled.'.$left);
    }
}
