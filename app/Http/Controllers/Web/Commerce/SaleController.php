<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Enums\ChargeAppliesTo;
use App\Enums\ItemStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\ReceiptRequest;
use App\Http\Requests\Commerce\SaleRequest;
use App\Http\Requests\Commerce\SaleReturnRequest;
use App\Models\AdvanceOrder;
use App\Models\Category;
use App\Models\ChargeMethod;
use App\Models\Customer;
use App\Models\Item;
use App\Models\MetalRate;
use App\Models\MetalType;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SaleReturnLine;
use App\Models\StockLocation;
use App\Models\WhatsappMessage;
use App\Services\Commerce\InvoiceSheet;
use App\Services\Commerce\WeightSummary;
use App\Services\Commerce\LedgerService;
use App\Services\Commerce\PaymentService;
use App\Services\Commerce\SaleReturnService;
use App\Services\Commerce\SaleService;
use App\Services\Commerce\WhatsappService;
use App\Services\Foundation\NumberFormatService;
use App\Services\Foundation\SettingService;
use App\Support\CompanyContext;
use App\Support\CustomerShare;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function index(Request $request, NumberFormatService $format, WeightSummary $weights, CompanyContext $context): View
    {
        $this->authorize('viewAny', Sale::class);
        $company = $context->company();
        $search = trim((string) $request->query('search', ''));
        $from = $this->dateQuery($request->query('from'));
        $to = $this->dateQuery($request->query('to'));
        $status = in_array($request->query('status'), ['paid', 'due'], true) ? (string) $request->query('status') : 'all';

        $query = Sale::query()->with('customer');
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('number', 'like', '%'.$search.'%')
                    ->orWhereHas('customer', function ($customers) use ($search): void {
                        $customers->where('name', 'like', '%'.$search.'%')
                            ->orWhere('mobile', 'like', '%'.$search.'%')
                            ->orWhere('code', 'like', '%'.$search.'%');
                    });
            });
        }
        if ($from) {
            $query->whereDate('sold_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('sold_at', '<=', $to);
        }
        if ($status === 'paid') {
            $query->whereRaw('paid_amount >= total + previous_due');
        }
        if ($status === 'due') {
            $query->whereRaw('paid_amount < total + previous_due');
        }

        $matched = (clone $query)->get(['total', 'paid_amount', 'previous_due']);
        $billed = $matched->reduce(fn (BigDecimal $carry, Sale $sale) => $carry->plus((string) $sale->total), BigDecimal::zero());
        $received = $matched->reduce(fn (BigDecimal $carry, Sale $sale) => $carry->plus((string) $sale->paid_amount), BigDecimal::zero());
        $carried = $matched->reduce(fn (BigDecimal $carry, Sale $sale) => $carry->plus((string) $sale->previous_due), BigDecimal::zero());
        $weightRows = $weights->sales(clone $query);

        return view('commerce.sales.index', [
            'sales' => $query->withSum('lines as net_weight', 'net_weight')->withSum('lines as gross_weight', 'gross_weight')->orderByDesc('sold_at')->orderByDesc('id')->paginate(20)->withQueryString(),
            'weights' => $weightRows,
            'search' => $search,
            'from' => $from ?? '',
            'to' => $to ?? '',
            'status' => $status,
            'billCount' => $matched->count(),
            'billed' => $format->money((string) $billed, $company),
            'received' => $format->money((string) $received, $company),
            'due' => $format->money((string) $billed->minus($received)->plus($carried), $company),
            'money' => fn (string $amount) => $format->money($amount, $company),
            'weight' => fn (string $amount) => $format->weight($amount, $company),
        ]);
    }

    private function dateQuery(mixed $value): ?string
    {
        $date = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : null;
    }

    public function create(Request $request, SaleService $sales, LedgerService $ledger, SettingService $settings, NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('create', Sale::class);

        return $this->billScreen($request, $sales, $ledger, $settings, $format, $context, null);
    }

    public function edit(Request $request, Sale $sale, SaleService $sales, LedgerService $ledger, SettingService $settings, NumberFormatService $format, CompanyContext $context): View|RedirectResponse
    {
        $this->authorize('update', $sale);

        if ($message = $sales->editBlock($sale)) {
            return redirect()->route('sales.show', $sale)->withErrors(['sale' => $message]);
        }

        return $this->billScreen($request, $sales, $ledger, $settings, $format, $context, $sale);
    }

    public function update(SaleRequest $request, Sale $sale, SaleService $sales, CompanyContext $context): RedirectResponse
    {
        $this->authorize('update', $sale);
        $revised = $sales->revise($sale, $request->validated(), $request->user()?->id);

        return redirect()->route('sales.show', $revised)->with('status', 'Invoice '.$revised->number.' updated.');
    }

    private function billScreen(Request $request, SaleService $sales, LedgerService $ledger, SettingService $settings, NumberFormatService $format, CompanyContext $context, ?Sale $sale): View
    {
        $company = $context->company();
        $draft = $sale ? $this->invoiceDraft($sale, $sales) : null;
        $order = $request->filled('order')
            ? AdvanceOrder::query()->with(['customer', 'metalType', 'purity'])->where('uuid', (string) $request->query('order'))->whereIn('status', AdvanceOrder::OPEN)->first()
            : null;
        $items = Item::query()
            ->with(['metalType', 'purity', 'makingMethod', 'wastageMethod', 'stones'])
            ->where('own_gold', false)
            ->where(function ($query) use ($draft): void {
                $query->where('status', ItemStatus::Available);
                if ($draft && $draft['stockIds'] !== []) {
                    $query->orWhereIn('id', $draft['stockIds']);
                }
            })
            ->orderBy('item_code')
            ->limit(300)
            ->get();
        $customers = Customer::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->where('is_system', false)
                ->when($sale?->customer?->is_system, fn ($query) => $query->orWhere('id', $sale->customer_id)))
            ->orderBy('name')
            ->get();

        return view('commerce.sales.create', [
            'order' => $order,
            'customers' => $customers,
            'credits' => $ledger->spendableCredits($customers->where('is_system', false)->modelKeys()),
            'openDues' => $sales->openDues($customers->where('is_system', false)->modelKeys(), $sale?->id),
            'ledgerDues' => $sale ? null : $ledger->outstandingDues($customers->where('is_system', false)->modelKeys()),
            'sale' => $sale,
            'draft' => $draft,
            'rows' => $items->map(fn (Item $item): array => [
                'item' => $item,
                'quote' => $sales->quote($item, $order),
            ]),
            'categories' => Category::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'metals' => MetalType::query()->with(['purities' => fn ($purities) => $purities->active()])->active()->orderBy('name')->get(),
            'locations' => StockLocation::query()->with('branch')->where('is_active', true)->orderBy('name')->get(),
            'making' => ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Making)->where('is_active', true)->orderBy('name')->get(),
            'wastage' => ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Wastage)->where('is_active', true)->orderBy('name')->get(),
            'rates' => MetalRate::query()->inForce()->orderByDesc('effective_at')->orderByDesc('id')->get(['metal_type_id', 'purity_id', 'branch_id', 'rate_per_gram']),
            'gstPercent' => (string) ($sale?->tax_percent ?? $settings->get('pricing.gst_percent', $company) ?? '0'),
            'makingMode' => (string) old('making_mode', $sale?->making_mode ?? $settings->get('pricing.making_mode', $company) ?? 'inside'),
            'makingGstPercent' => (string) ($sale?->making_tax_percent ?? $settings->get('pricing.making_gst_percent', $company) ?? '0'),
            'taxExclusive' => $sale ? ! $sale->prices_include_tax : $settings->get('invoice.tax_display', $company) !== 'inclusive',
            'roundRupee' => (bool) $settings->get('pricing.round_rupee', $company),
            'money' => fn (string $amount): string => $format->money($amount, $company),
            'weight' => fn (string $amount): string => $format->weight($amount, $company),
        ]);
    }

    /**
     * @return array{stock: list<string>, stockIds: list<int>, fresh: list<array<string, mixed>>, lockedRates: list<array<string, mixed>>, payments: list<array<string, string>>, fixedPrevious: ?array{amount: float, bills: int}}
     */
    private function invoiceDraft(Sale $sale, SaleService $sales): array
    {
        $sale->load([
            'lines.item.movements', 'lines.item.metalType', 'lines.item.purity', 'lines.item.makingMethod',
            'lines.item.wastageMethod', 'lines.item.location', 'lines.stones', 'payments',
        ]);
        $stock = [];
        $stockIds = [];
        $fresh = [];
        $rates = [];

        foreach ($sale->lines as $line) {
            $item = $line->item;

            if (! $item) {
                continue;
            }

            $rates[] = [
                'metal_type_id' => $item->metal_type_id,
                'purity_id' => $item->purity_id,
                'rate_per_gram' => (string) $line->rate_per_gram,
            ];

            if ($sales->madeForBill($item, $sale)) {
                $fresh[] = $this->freshDraft($line);
            } else {
                $stock[] = $item->uuid;
                $stockIds[] = $item->id;
            }
        }

        return [
            'stock' => $stock,
            'stockIds' => $stockIds,
            'fresh' => $fresh,
            'lockedRates' => $rates,
            'payments' => $sale->payments->map(fn (Payment $payment): array => [
                'method' => $payment->method->value,
                'amount' => (string) $payment->amount,
                'reference' => (string) ($payment->reference ?? ''),
            ])->values()->all(),
            'fixedPrevious' => (float) $sale->previous_due > 0 ? [
                'amount' => (float) $sale->previous_due,
                'bills' => (int) $sale->previous_bills,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function freshDraft(SaleLine $line): array
    {
        $item = $line->item;
        $stones = $line->stones->map(fn ($stone): array => [
            'name' => $stone->name,
            'weight' => (string) $stone->weight,
            'value' => (string) $stone->value,
            'rate' => $stone->rate !== null ? (string) $stone->rate : '',
            'rate_unit' => (string) ($stone->rate_unit ?? ''),
        ])->values()->all();

        return [
            'name' => $line->name,
            'category' => (string) ($item->category?->name ?? ''),
            'metal' => trim($line->metal_name.' '.$line->purity_name),
            'line' => (float) $line->line_amount,
            'netWeight' => (float) $line->net_weight,
            'metalAmount' => (float) $line->metal_amount,
            'makingAmount' => (float) $line->making_amount,
            'wastageAmount' => (float) $line->wastage_amount,
            'stoneAmount' => (float) $line->stone_amount,
            'parts' => [[
                'kind' => 'metal',
                'name' => trim($line->metal_name.' '.$line->purity_name),
                'weight' => number_format((float) $line->net_weight, 3, '.', '').' g',
                'rate' => '',
                'amount' => (float) $line->metal_amount,
            ]],
            'stones' => $stones,
            'fields' => [
                'name' => $line->name,
                'category_uuid' => (string) ($item->category?->uuid ?? ''),
                'huid' => (string) ($item->huid ?? ''),
                'metal_uuid' => $item->metalType->uuid,
                'purity_uuid' => $item->purity->uuid,
                'location_uuid' => $item->location->uuid,
                'gross_weight' => number_format((float) $item->gross_weight, 3, '.', ''),
                'stone_weight' => (string) $item->stone_weight,
                'other_weight' => (string) $item->other_weight,
                'stone_value' => (string) $item->stone_value,
                'making_method_uuid' => (string) ($item->makingMethod?->uuid ?? ''),
                'making_value' => (string) $item->making_value,
                'wastage_method_uuid' => (string) ($item->wastageMethod?->uuid ?? ''),
                'wastage_value' => (string) $item->wastage_value,
                'own' => $item->own_gold ? '1' : '0',
            ],
            'form' => [
                'name' => $line->name,
                'huid' => (string) ($item->huid ?? ''),
                'product' => (string) ($item->category?->uuid ?? ''),
                'metal' => $item->metalType->uuid,
                'purity' => $item->purity->uuid,
                'location' => $item->location->uuid,
                'net' => (string) $item->net_weight,
                'gross' => (string) $item->gross_weight,
                'stone' => (string) $item->stone_weight,
                'other' => (string) $item->other_weight,
                'making' => (string) ($item->makingMethod?->uuid ?? ''),
                'makingValue' => (string) $item->making_value,
                'wastage' => (string) ($item->wastageMethod?->uuid ?? ''),
                'wastageValue' => (string) $item->wastage_value,
                'own' => $item->own_gold ? '1' : '0',
                'stones' => [],
            ],
        ];
    }

    public function store(SaleRequest $request, SaleService $sales, CompanyContext $context): RedirectResponse
    {
        $sale = $sales->post($context->company(), $request->validated(), $request->user()?->id);

        return redirect()->route('sales.show', $sale)->with('status', 'Invoice '.$sale->number.' saved.');
    }

    public function show(Sale $sale, InvoiceSheet $invoice, WhatsappService $whatsapp, CompanyContext $context): View
    {
        $this->authorize('view', $sale);
        $data = $invoice->data($sale, $context->company());

        return view('commerce.sales.show', $data + [
            'returned' => SaleReturnLine::query()->whereIn('sale_line_id', $sale->lines->modelKeys())->pluck('sale_line_id'),
            'methods' => PaymentMethod::cases(),
            'shareUrl' => CustomerShare::whatsapp($sale->customer?->mobile, 'Your bill '.$sale->number.' from '.$data['company']->displayName().': '.$data['billLink']),
            'whatsappReady' => $whatsapp->configured(),
            'whatsappNumber' => CustomerShare::number($sale->customer?->mobile),
            'whatsappMessages' => WhatsappMessage::query()->with('user')->where('sale_id', $sale->id)->latest('id')->limit(5)->get(),
        ]);
    }

    public function whatsapp(Request $request, Sale $sale, WhatsappService $whatsapp, CompanyContext $context): RedirectResponse
    {
        $this->authorize('view', $sale);
        $message = $whatsapp->sendBill($sale, $context->company(), $request->user()?->id);

        if ($message->status === 'failed') {
            return redirect()->route('sales.show', $sale)->withErrors(['whatsapp' => 'WhatsApp did not send the bill: '.$message->error]);
        }

        return redirect()->route('sales.show', $sale)->with('status', 'Bill '.$sale->number.' sent on WhatsApp to +'.$message->recipient.'.');
    }

    public function payment(ReceiptRequest $request, Sale $sale, PaymentService $payments): RedirectResponse
    {
        $this->authorize('view', $sale);
        $payment = $payments->receiveForSale($sale, $request->validated(), $request->user()?->id);

        return redirect()->route('sales.show', $sale)->with('status', 'Receipt '.$payment->number.' saved for '.$sale->number.'.');
    }

    public function returnSale(SaleReturnRequest $request, Sale $sale, SaleReturnService $returns): RedirectResponse
    {
        $document = $returns->post($sale, $request->validated('lines'), (string) $request->validated('refund'), $request->user()?->id);

        return redirect()->route('sales.show', $sale)->with('status', 'Credit note '.$document->number.' saved.');
    }
}
