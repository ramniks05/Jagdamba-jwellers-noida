<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Enums\ChargeAppliesTo;
use App\Enums\ItemStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\SaleRequest;
use App\Http\Requests\Commerce\SaleReturnRequest;
use App\Models\Category;
use App\Models\ChargeMethod;
use App\Models\Customer;
use App\Models\Item;
use App\Models\MetalRate;
use App\Models\MetalType;
use App\Models\Sale;
use App\Models\SaleReturnLine;
use App\Models\StockLocation;
use App\Models\Company;
use App\Services\Commerce\SaleReturnService;
use App\Services\Commerce\SaleService;
use App\Services\Foundation\NumberFormatService;
use App\Services\Foundation\SettingService;
use App\Support\CompanyContext;
use App\Support\RupeesInWords;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function index(Request $request, NumberFormatService $format, CompanyContext $context): View
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
            $query->whereColumn('paid_amount', '>=', 'total');
        }
        if ($status === 'due') {
            $query->whereColumn('paid_amount', '<', 'total');
        }

        $matched = (clone $query)->get(['total', 'paid_amount']);
        $billed = $matched->reduce(fn (BigDecimal $carry, Sale $sale) => $carry->plus((string) $sale->total), BigDecimal::zero());
        $received = $matched->reduce(fn (BigDecimal $carry, Sale $sale) => $carry->plus((string) $sale->paid_amount), BigDecimal::zero());

        return view('commerce.sales.index', [
            'sales' => $query->orderByDesc('sold_at')->orderByDesc('id')->paginate(20)->withQueryString(),
            'search' => $search,
            'from' => $from ?? '',
            'to' => $to ?? '',
            'status' => $status,
            'billCount' => $matched->count(),
            'billed' => $format->money((string) $billed, $company),
            'received' => $format->money((string) $received, $company),
            'due' => $format->money((string) $billed->minus($received), $company),
            'money' => fn (string $amount) => $format->money($amount, $company),
        ]);
    }

    private function dateQuery(mixed $value): ?string
    {
        $date = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : null;
    }

    public function create(Request $request, SaleService $sales, SettingService $settings, NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('create', Sale::class);
        $company = $context->company();
        $search = trim((string) $request->query('search', ''));
        $items = Item::query()
            ->with(['metalType', 'purity', 'makingMethod', 'wastageMethod', 'stones'])
            ->where('status', ItemStatus::Available)
            ->orderBy('item_code')
            ->limit(300)
            ->get();

        return view('commerce.sales.create', [
            'search' => $search,
            'customers' => Customer::query()->where('is_active', true)->orderBy('name')->get(),
            'rows' => $items->map(fn (Item $item): array => [
                'item' => $item,
                'quote' => $sales->quote($item),
            ]),
            'categories' => Category::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'metals' => MetalType::query()->with('purities')->where('is_active', true)->orderBy('name')->get(),
            'locations' => StockLocation::query()->with('branch')->where('is_active', true)->orderBy('name')->get(),
            'making' => ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Making)->where('is_active', true)->orderBy('name')->get(),
            'wastage' => ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Wastage)->where('is_active', true)->orderBy('name')->get(),
            'rates' => MetalRate::query()->orderByDesc('effective_at')->orderByDesc('id')->get(['metal_type_id', 'purity_id', 'branch_id', 'rate_per_gram']),
            'gstPercent' => (string) ($settings->get('pricing.gst_percent', $company) ?? '0'),
            'taxExclusive' => $settings->get('invoice.tax_display', $company) !== 'inclusive',
            'roundRupee' => (bool) $settings->get('pricing.round_rupee', $company),
            'money' => fn (string $amount): string => $format->money($amount, $company),
            'weight' => fn (string $amount): string => $format->weight($amount, $company),
        ]);
    }

    public function store(SaleRequest $request, SaleService $sales, CompanyContext $context): RedirectResponse
    {
        $sale = $sales->post($context->company(), $request->validated(), $request->user()?->id);

        return redirect()->route('sales.show', $sale)->with('status', 'Invoice '.$sale->number.' saved.');
    }

    public function show(Sale $sale, NumberFormatService $format, SettingService $settings, CompanyContext $context): View
    {
        $this->authorize('view', $sale);
        $company = $context->company();
        $sale->load(['lines.item', 'lines.stones', 'payments', 'customer', 'branch']);

        return view('commerce.sales.show', [
            'sale' => $sale,
            'returned' => SaleReturnLine::query()->whereIn('sale_line_id', $sale->lines->modelKeys())->pluck('sale_line_id'),
            'company' => $company,
            'terms' => (string) ($settings->get('invoice.terms', $company) ?? ''),
            'footer' => (string) ($settings->get('invoice.footer_note', $company) ?? ''),
            'showLogo' => (bool) $settings->get('invoice.show_logo', $company),
            'amountWords' => RupeesInWords::format((string) $sale->total),
            'taxes' => $this->taxRows($sale, $company),
            'money' => fn (string $amount) => $format->money($amount, $company),
            'weight' => fn (string $amount) => $format->weight($amount, $company),
        ]);
    }

    /**
     * @return list<array{label: string, amount: string}>
     */
    private function taxRows(Sale $sale, Company $company): array
    {
        $tax = BigDecimal::of((string) $sale->tax_amount);
        $percent = (float) $sale->tax_percent;
        $customerState = mb_strtolower(trim((string) $sale->customer?->state));
        $shopState = mb_strtolower(trim((string) $company->state));
        $interstate = $customerState !== '' && $shopState !== '' && $customerState !== $shopState;

        if ($interstate) {
            return [[
                'label' => 'IGST '.$this->percentLabel($percent).'%',
                'amount' => (string) $tax,
            ]];
        }

        $half = $tax->dividedBy(2, 2, RoundingMode::HalfUp);

        return [
            ['label' => 'CGST '.$this->percentLabel($percent / 2).'%', 'amount' => (string) $half],
            ['label' => 'SGST '.$this->percentLabel($percent / 2).'%', 'amount' => (string) $tax->minus($half)],
        ];
    }

    private function percentLabel(float $percent): string
    {
        $label = rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.');

        return $label === '' ? '0' : $label;
    }

    public function returnSale(SaleReturnRequest $request, Sale $sale, SaleReturnService $returns): RedirectResponse
    {
        $document = $returns->post($sale, $request->validated('lines'), (string) $request->validated('refund'), $request->user()?->id);

        return redirect()->route('sales.show', $sale)->with('status', 'Credit note '.$document->number.' saved.');
    }
}
