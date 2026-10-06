<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Enums\ItemStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\SaleRequest;
use App\Http\Requests\Commerce\SaleReturnRequest;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Sale;
use App\Models\SaleReturnLine;
use App\Services\Commerce\SaleReturnService;
use App\Services\Commerce\SaleService;
use App\Services\Foundation\NumberFormatService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Sale::class);

        return view('commerce.sales.index', [
            'sales' => Sale::query()->with('customer')->orderByDesc('sold_at')->orderByDesc('id')->paginate(20),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Sale::class);
        $search = trim((string) $request->query('search', ''));

        return view('commerce.sales.create', [
            'search' => $search,
            'customers' => Customer::query()->where('is_active', true)->orderBy('name')->get(),
            'items' => Item::query()->with(['metalType', 'purity'])->where('status', ItemStatus::Available)->matching($search)->orderBy('item_code')->limit(40)->get(),
        ]);
    }

    public function store(SaleRequest $request, SaleService $sales, CompanyContext $context): RedirectResponse
    {
        $sale = $sales->post($context->company(), $request->validated(), $request->user()?->id);

        return redirect()->route('sales.show', $sale)->with('status', 'Invoice '.$sale->number.' saved.');
    }

    public function show(Sale $sale, NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('view', $sale);
        $sale->load(['lines', 'payments', 'customer', 'branch']);

        return view('commerce.sales.show', [
            'sale' => $sale,
            'returned' => SaleReturnLine::query()->whereIn('sale_line_id', $sale->lines->modelKeys())->pluck('sale_line_id'),
            'company' => $context->company(),
            'money' => fn (string $amount) => $format->money($amount, $context->company()),
            'weight' => fn (string $amount) => $format->weight($amount, $context->company()),
        ]);
    }

    public function returnSale(SaleReturnRequest $request, Sale $sale, SaleReturnService $returns): RedirectResponse
    {
        $document = $returns->post($sale, $request->validated('lines'), (string) $request->validated('refund'), $request->user()?->id);

        return redirect()->route('sales.show', $sale)->with('status', 'Credit note '.$document->number.' saved.');
    }
}
