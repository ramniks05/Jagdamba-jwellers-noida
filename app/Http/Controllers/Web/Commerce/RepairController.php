<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Enums\ItemStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\RepairDeliveryRequest;
use App\Http\Requests\Commerce\RepairRequest;
use App\Models\Customer;
use App\Models\Item;
use App\Models\RepairOrder;
use App\Services\Commerce\RepairService;
use App\Services\Foundation\NumberFormatService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RepairController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', RepairOrder::class);

        return view('commerce.repairs.index', [
            'repairs' => RepairOrder::query()->with('customer')->orderByDesc('received_at')->paginate(20),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', RepairOrder::class);

        return view('commerce.repairs.create', [
            'customers' => Customer::query()->where('is_active', true)->orderBy('name')->get(),
            'items' => Item::query()->where('status', ItemStatus::Available)->orderBy('item_code')->limit(40)->get(),
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

        return view('commerce.repairs.show', [
            'repair' => $repair,
            'company' => $context->company(),
            'methods' => PaymentMethod::cases(),
            'money' => fn (string $amount) => $format->money($amount, $context->company()),
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
