<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Enums\ItemStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\RepairDeliveryRequest;
use App\Http\Requests\Commerce\RepairRequest;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Payment;
use App\Models\RepairOrder;
use App\Services\Commerce\RepairService;
use App\Services\Foundation\NumberFormatService;
use App\Services\Foundation\SettingService;
use App\Support\CompanyContext;
use App\Support\CustomerShare;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RepairController extends Controller
{
    public function index(Request $request, NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('viewAny', RepairOrder::class);
        $show = in_array($request->query('show'), ['open', 'ready', 'delivered', 'cancelled', 'all'], true) ? (string) $request->query('show') : 'open';

        return view('commerce.repairs.index', [
            'repairs' => RepairOrder::query()
                ->with('customer')
                ->when($show === 'open', fn ($query) => $query->whereIn('status', RepairOrder::OPEN))
                ->when(in_array($show, ['ready', 'delivered', 'cancelled'], true), fn ($query) => $query->where('status', $show))
                ->orderByRaw('expected_on is null')
                ->orderBy('expected_on')
                ->orderByDesc('received_at')
                ->paginate(20)
                ->withQueryString(),
            'show' => $show,
            'money' => fn (string $amount) => $format->money($amount, $context->company()),
            'weight' => fn (string $amount) => $format->weight($amount, $context->company()),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', RepairOrder::class);

        return view('commerce.repairs.create', [
            'customers' => Customer::query()->where('is_active', true)->orderBy('name')->get(),
            'items' => Item::query()->where('status', ItemStatus::Available)->orderBy('item_code')->limit(100)->get(),
            'categories' => Category::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(RepairRequest $request, RepairService $repairs, CompanyContext $context): RedirectResponse
    {
        $repair = $repairs->receive($context->company(), $request->validated(), $request->user()?->id);

        return redirect()->route('repairs.show', $repair)->with('status', 'Repair '.$repair->number.' received.');
    }

    public function show(RepairOrder $repair, NumberFormatService $format, SettingService $settings, CompanyContext $context): View
    {
        $this->authorize('view', $repair);
        $company = $context->company();
        $repair->load(['customer', 'item', 'branch']);
        $payments = Payment::query()->where('repair_order_id', $repair->id)->orderBy('id')->get();
        $paid = $payments->reduce(fn (BigDecimal $sum, Payment $payment) => $sum->plus((string) $payment->amount), BigDecimal::zero())
            ->toScale(2, RoundingMode::HalfUp);
        $charge = BigDecimal::of((string) ($repair->final_charge ?? '0'))->toScale(2, RoundingMode::HalfUp);
        $money = fn (string $amount) => $format->money($amount, $company);
        $weight = fn (string $amount) => $format->weight($amount, $company);
        $share = $company->displayName()."\n"
            .'Repair '.$repair->number."\n"
            .'Received '.$repair->received_at?->timezone(config('app.timezone'))->format('d-m-Y')."\n"
            .'Customer '.($repair->customer?->name)."\n\n"
            .$repair->description.' · '.$repair->problem."\n"
            .'Weight received '.$weight((string) $repair->gross_weight)."\n"
            .($repair->expected_on ? 'Ready by '.$repair->expected_on->format('d-m-Y')."\n" : '')
            .'Status '.$repair->statusLabel()."\n"
            .($repair->final_charge !== null
                ? 'Repair charge '.$money((string) $charge)."\n"
                : 'Estimate '.$money((string) $repair->estimated_cost)."\n")
            ."\n".'Please bring this receipt when collecting the jewellery.';

        return view('commerce.repairs.show', [
            'repair' => $repair,
            'company' => $company,
            'payments' => $payments,
            'methods' => PaymentMethod::cases(),
            'paid' => (string) $paid,
            'due' => (string) $charge->minus($paid)->toScale(2, RoundingMode::HalfUp),
            'showLogo' => (bool) $settings->get('invoice.show_logo', $company),
            'footer' => (string) ($settings->get('invoice.footer_note', $company) ?? ''),
            'shareUrl' => CustomerShare::whatsapp($repair->customer?->mobile, $share),
            'money' => $money,
            'weight' => $weight,
        ]);
    }

    public function status(Request $request, RepairOrder $repair, RepairService $repairs): RedirectResponse
    {
        $this->authorize('update', $repair);
        $repairs->advance($repair, (string) $request->input('status'));

        return redirect()->route('repairs.show', $repair)->with('status', 'Repair '.$repair->number.' moved to: '.$repair->statusLabel().'.');
    }

    public function deliver(RepairDeliveryRequest $request, RepairOrder $repair, RepairService $repairs): RedirectResponse
    {
        $repairs->deliver($repair, $request->validated(), $request->user()?->id);

        return redirect()->route('repairs.show', $repair)->with('status', 'Repair delivered.');
    }
}
