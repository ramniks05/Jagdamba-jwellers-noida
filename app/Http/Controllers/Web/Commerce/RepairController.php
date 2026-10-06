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
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RepairController extends Controller
{
    public function index(NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('viewAny', RepairOrder::class);

        return view('commerce.repairs.index', [
            'repairs' => RepairOrder::query()->with('customer')->orderByDesc('received_at')->paginate(20),
            'money' => fn (string $amount) => $format->money($amount, $context->company()),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', RepairOrder::class);

        return view('commerce.repairs.create', [
            'customers' => Customer::query()->where('is_active', true)->orderBy('name')->get(),
            'items' => Item::query()->where('status', ItemStatus::Available)->orderBy('item_code')->limit(100)->get(),
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(RepairRequest $request, RepairService $repairs, CompanyContext $context): RedirectResponse
    {
        $repair = $repairs->receive($context->company(), $request->validated(), $request->user()?->id);

        return redirect()->route('repairs.show', $repair)->with('status', 'Repair '.$repair->number.' received.');
    }

    public function show(RepairOrder $repair, NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('view', $repair);
        $repair->load(['customer', 'item', 'branch']);
        $paid = BigDecimal::zero();

        foreach (Payment::query()->where('repair_order_id', $repair->id)->get(['amount']) as $payment) {
            $paid = $paid->plus((string) $payment->amount);
        }

        $paid = $paid->toScale(2, RoundingMode::HalfUp);
        $charge = BigDecimal::of((string) ($repair->final_charge ?? '0'))->toScale(2, RoundingMode::HalfUp);

        return view('commerce.repairs.show', [
            'repair' => $repair,
            'company' => $context->company(),
            'methods' => PaymentMethod::cases(),
            'paid' => (string) $paid,
            'due' => (string) $charge->minus($paid)->toScale(2, RoundingMode::HalfUp),
            'money' => fn (string $amount) => $format->money($amount, $context->company()),
            'weight' => fn (string $amount) => $format->weight($amount, $context->company()),
        ]);
    }

    public function status(Request $request, RepairOrder $repair, RepairService $repairs): RedirectResponse
    {
        $this->authorize('update', $repair);
        $repairs->advance($repair, (string) $request->input('status'));

        return redirect()->route('repairs.show', $repair)->with('status', 'Repair updated.');
    }

    public function deliver(RepairDeliveryRequest $request, RepairOrder $repair, RepairService $repairs): RedirectResponse
    {
        $repairs->deliver($repair, $request->validated(), $request->user()?->id);

        return redirect()->route('repairs.show', $repair)->with('status', 'Repair delivered.');
    }
}
