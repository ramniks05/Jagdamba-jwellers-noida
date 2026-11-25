<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Enums\ItemStatus;
use App\Enums\LedgerDirection;
use App\Enums\MetalStockKind;
use App\Enums\PartyType;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Branch;
use App\Models\Item;
use App\Models\LedgerEntry;
use App\Models\MetalStockMovement;
use App\Models\MetalType;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SaleRetailLine;
use App\Models\SaleReturnLine;
use App\Models\StockLocation;
use App\Models\StockTray;
use App\Models\Supplier;
use App\Services\Commerce\MetalStockService;
use App\Services\Commerce\PieceCategorySummary;
use App\Services\Commerce\WeightSummary;
use App\Services\Foundation\NumberFormatService;
use App\Services\Foundation\SettingService;
use App\Support\CompanyContext;
use App\Support\Pdf;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ReportController extends Controller
{
    private const PAGE_SIZES = [25, 50, 100];

    public function stock(Request $request, NumberFormatService $format, MetalStockService $metalStock, SettingService $settings, CompanyContext $context): View
    {
        $this->authorize('reports.view');
        $company = $context->company();
        $search = trim((string) $request->query('search', ''));
        $metal = trim((string) $request->query('metal', ''));
        $purity = trim((string) $request->query('purity', ''));
        $supplierUuid = trim((string) $request->query('supplier', ''));
        $category = trim((string) $request->query('category', ''));
        $location = trim((string) $request->query('location', ''));
        $status = in_array($request->query('status'), ['available', 'reserved'], true) ? (string) $request->query('status') : 'all';
        $sort = in_array($request->query('sort'), ['code', 'newest', 'heaviest'], true) ? (string) $request->query('sort') : 'code';

        $metalTotals = collect();
        $pieces = null;
        $pieceCount = 0;
        $grossTotal = $format->weight('0.000', $company);
        $netTotal = $grossTotal;

        if ($request->boolean('report')) {
            $query = Item::query()
                ->where('own_gold', false)
                ->when($status === 'all', fn ($rows) => $rows->whereIn('status', [ItemStatus::Available, ItemStatus::Reserved]))
                ->when($status !== 'all', fn ($rows) => $rows->where('status', $status))
                ->when($search !== '', function ($rows) use ($search): void {
                    $like = $this->like($search);
                    $rows->where(fn ($inner) => $inner->where('item_code', 'like', $like)->orWhere('name', 'like', $like)->orWhere('huid', 'like', $like)->orWhere('barcode', 'like', $like));
                })
                ->when($metal !== '', fn ($rows) => $rows->whereHas('metalType', fn ($metals) => $metals->where('uuid', $metal)))
                ->when($purity !== '', fn ($rows) => $rows->whereHas('purity', fn ($purities) => $purities->where('uuid', $purity)))
                ->when($category !== '', fn ($rows) => $rows->where('category_id', (int) $category))
                ->when($location !== '', fn ($rows) => $rows->whereHas('location', fn ($places) => $places->where('uuid', $location)));

            $totals = (clone $query)->toBase()
                ->selectRaw('count(*) as pieces, coalesce(sum(gross_weight), 0) as gross, coalesce(sum(net_weight), 0) as net')
                ->first();
            $grouped = (clone $query)->toBase()
                ->selectRaw('metal_type_id, count(*) as pieces, coalesce(sum(gross_weight), 0) as gross, coalesce(sum(net_weight), 0) as net')
                ->groupBy('metal_type_id')
                ->get();
            $metalNames = MetalType::query()->whereIn('id', $grouped->pluck('metal_type_id')->filter())->pluck('name', 'id');
            $metalTotals = $grouped
                ->map(fn ($row) => [
                    'label' => $metalNames[$row->metal_type_id] ?? 'Other',
                    'pieces' => (int) $row->pieces,
                    'gross' => $format->weight($this->decimal($row->gross, 3), $company),
                    'net' => $format->weight($this->decimal($row->net, 3), $company),
                ])
                ->sortBy('label')
                ->values();
            $pieceCount = (int) $totals->pieces;
            $grossTotal = $format->weight($this->decimal($totals->gross, 3), $company);
            $netTotal = $format->weight($this->decimal($totals->net, 3), $company);
            $query->with(['metalType', 'purity', 'category', 'location.branch']);
            match ($sort) {
                'newest' => $query->orderByDesc('id'),
                'heaviest' => $query->orderByDesc('net_weight')->orderBy('item_code'),
                default => $query->orderBy('item_code'),
            };
            $query->orderBy('id');
            $pieces = $this->page($query, $request, $pieceCount);
        }

        $canCorrect = ! $request->boolean('report') && ! $request->boolean('grams') && $request->user()?->can('inventory.create');
        $editUuid = $canCorrect ? trim((string) $request->query('edit', '')) : '';
        $editing = $editUuid === '' ? null : MetalStockMovement::query()
            ->with(['branch', 'metalType', 'purity', 'reference'])
            ->where('uuid', $editUuid)
            ->whereIn('kind', [MetalStockKind::Purchase, MetalStockKind::Opening])
            ->first();
        $purchases = $canCorrect
            ? MetalStockMovement::query()
                ->with(['metalType', 'purity', 'reference'])
                ->where('kind', MetalStockKind::Purchase)
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->limit(5)
                ->get()
            : collect();

        $chosenSupplier = $supplierUuid === '' ? null : Supplier::query()->where('uuid', $supplierUuid)->first();
        $balances = ($supplierUuid !== '' && ! $chosenSupplier)
            ? collect()
            : $metalStock->balances($chosenSupplier)
                ->when($metal !== '', fn ($rows) => $rows->where('metal_uuid', $metal))
                ->when($purity !== '', fn ($rows) => $rows->where('purity_uuid', $purity))
                ->values();

        return view('commerce.reports.stock', [
            'pieces' => $pieces,
            'metalTotals' => $metalTotals,
            'metals' => MetalType::query()->with(['purities' => fn ($purities) => $purities->where('is_active', true)->orderByDesc('fineness')])->where('is_active', true)->orderBy('name')->get(),
            'branches' => Branch::query()->visibleTo($request->user())->orderByDesc('is_head_office')->orderBy('name')->get(),
            'metalStock' => $balances,
            'supplierName' => $chosenSupplier?->name,
            'suppliers' => Supplier::query()->where(fn ($rows) => $rows->where('is_active', true)->when($supplierUuid !== '', fn ($rows) => $rows->orWhere('uuid', $supplierUuid)))->orderBy('name')->get(),
            'categories' => Category::query()->orderBy('name')->get(),
            'locations' => StockLocation::query()->with('branch')->orderBy('name')->get(),
            'filters' => compact('search', 'metal', 'purity', 'supplierUuid', 'category', 'location', 'status', 'sort'),
            'filtered' => $search !== '' || $metal !== '' || $purity !== '' || $supplierUuid !== '' || $category !== '' || $location !== '' || $status !== 'all',
            'pieceCount' => $pieceCount,
            'grossTotal' => $grossTotal,
            'netTotal' => $netTotal,
            'perPage' => $this->perPage($request),
            'company' => $company,
            'weight' => fn (string $amount) => $format->weight($amount, $company),
            'money' => fn (string $amount) => $format->money($amount, $company),
            'gstPercent' => (string) ($settings->get('pricing.gst_percent', $company) ?? '0'),
            'roundRupee' => (bool) $settings->get('pricing.round_rupee', $company),
            'editing' => $editing,
            'purchases' => $purchases,
            'canCorrect' => $canCorrect,
        ]);
    }

    public function categories(Request $request, PieceCategorySummary $summary, NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('reports.view');
        $company = $context->company();
        $metal = trim((string) $request->query('metal', ''));
        $location = trim((string) $request->query('location', ''));
        $rows = $summary->rows($metal ?: null, $location ?: null);
        $sum = fn (string $key) => number_format($rows->sum(fn (array $row) => (float) $row[$key]), 3, '.', '');

        return view('commerce.reports.categories', [
            'rows' => $rows,
            'totals' => [
                'in_stock' => $rows->sum('in_stock'),
                'gross' => $sum('gross'),
                'net' => $sum('net'),
                'reserved' => $rows->sum('reserved'),
                'repair' => $rows->sum('repair'),
                'sold' => $rows->sum('sold'),
            ],
            'metals' => MetalType::query()->where('is_active', true)->orderBy('name')->get(),
            'locations' => StockLocation::query()->with('branch')->orderBy('name')->get(),
            'filters' => compact('metal', 'location'),
            'company' => $company,
            'weight' => fn (string $amount) => $format->weight($amount, $company),
        ]);
    }

    public function ownGold(NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('reports.view');
        $company = $context->company();
        $lines = SaleLine::query()
            ->with(['sale.customer'])
            ->whereHas('item', fn ($items) => $items->where('own_gold', true))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('commerce.reports.own-gold', [
            'lines' => $lines,
            'returned' => SaleReturnLine::query()->whereIn('sale_line_id', $lines->modelKeys())->pluck('sale_line_id'),
            'money' => fn (string $amount): string => $format->money($amount, $company),
            'weight' => fn (string $amount): string => $format->weight($amount, $company),
        ]);
    }

    public function sales(Request $request, NumberFormatService $format, WeightSummary $weights, CompanyContext $context): View
    {
        $this->authorize('reports.view');
        $company = $context->company();
        $from = $this->dateQuery($request->query('from')) ?? now()->startOfMonth()->toDateString();
        $to = $this->dateQuery($request->query('to')) ?? now()->toDateString();
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        $search = trim((string) $request->query('search', ''));
        $payment = in_array($request->query('payment'), ['due', 'paid'], true) ? (string) $request->query('payment') : 'all';

        $query = Sale::query()
            ->where('status', 'posted')
            ->whereDate('sold_at', '>=', $from)
            ->whereDate('sold_at', '<=', $to)
            ->when($search !== '', function ($rows) use ($search): void {
                $like = $this->like($search);
                $rows->where(fn ($inner) => $inner->where('number', 'like', $like)
                    ->orWhereHas('customer', fn ($customers) => $customers->where('name', 'like', $like)->orWhere('mobile', 'like', $like)->orWhere('code', 'like', $like)));
            })
            ->when($payment === 'due', fn ($rows) => $rows->whereRaw('paid_amount < total + previous_due'))
            ->when($payment === 'paid', fn ($rows) => $rows->whereRaw('paid_amount >= total + previous_due'));

        $totals = (clone $query)->toBase()
            ->selectRaw('count(*) as bills, coalesce(sum(tax_amount + making_tax_amount), 0) as tax, coalesce(sum(total), 0) as total, coalesce(sum(paid_amount), 0) as paid, coalesce(sum(total + previous_due - paid_amount), 0) as due')
            ->first();

        $money = fn (string $amount) => $format->money($amount, $company);
        $today = now()->toDateString();
        $weightRows = $weights->sales(clone $query);

        return view('commerce.reports.sales', [
            'sales' => $this->page($query->with('customer')->withSum('lines as net_weight', 'net_weight')->withSum('lines as gross_weight', 'gross_weight')->orderByRaw('date(sold_at) asc')->orderBy('number'), $request, (int) $totals->bills),
            'weights' => $weightRows,
            'filters' => compact('search', 'from', 'to', 'payment'),
            'ranges' => [
                'Today' => [$today, $today],
                'Yesterday' => [now()->subDay()->toDateString(), now()->subDay()->toDateString()],
                'This month' => [now()->startOfMonth()->toDateString(), $today],
                'Last month' => [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()],
            ],
            'billCount' => (int) $totals->bills,
            'tax' => $money($this->decimal($totals->tax)),
            'total' => $money($this->decimal($totals->total)),
            'paid' => $money($this->decimal($totals->paid)),
            'due' => $money($this->decimal($totals->due)),
            'perPage' => $this->perPage($request),
            'company' => $company,
            'money' => $money,
            'weight' => fn (string $amount) => $format->weight($amount, $company),
        ]);
    }

    public function outstanding(Request $request, NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('reports.view');
        $company = $context->company();
        $search = trim((string) $request->query('search', ''));
        $show = $request->query('show') === 'advance' ? 'advance' : 'due';
        $sort = in_array($request->query('sort'), ['name', 'last_bill'], true) ? (string) $request->query('sort') : 'balance';

        $ledger = LedgerEntry::query()
            ->select('party_id')
            ->selectRaw("sum(case when direction = 'debit' then amount else -amount end) as balance")
            ->where('party_type', PartyType::Customer->value)
            ->groupBy('party_id');
        $query = Customer::query()
            ->joinSub($ledger, 'ledger', 'ledger.party_id', '=', 'customers.id')
            ->whereRaw($show === 'advance' ? 'ledger.balance < -0.004' : 'ledger.balance > 0.004')
            ->when($search !== '', function ($rows) use ($search): void {
                $like = $this->like($search);
                $rows->where(fn ($inner) => $inner->where('customers.name', 'like', $like)->orWhere('customers.mobile', 'like', $like)->orWhere('customers.code', 'like', $like));
            });

        $totals = DB::query()
            ->fromSub((clone $query)->select('ledger.balance'), 'rows')
            ->selectRaw('count(*) as customers, coalesce(sum(balance), 0) as balance')
            ->first();

        $query->select('customers.*', 'ledger.balance')
            ->addSelect(['last_bill_at' => Sale::query()->select('sold_at')->whereColumn('sales.customer_id', 'customers.id')->orderByDesc('sold_at')->limit(1)]);
        match ($sort) {
            'name' => $query->orderBy('customers.name'),
            'last_bill' => $query->orderByRaw('last_bill_at is null')->orderBy('last_bill_at'),
            default => $query->orderBy('ledger.balance', $show === 'advance' ? 'asc' : 'desc'),
        };
        $query->orderBy('customers.id');

        $money = fn (string $amount) => $format->money($amount, $company);
        $total = $this->decimal($totals->balance);
        $rows = $this->page($query, $request, (int) $totals->customers);
        $openBills = $show === 'due'
            ? Sale::query()
                ->whereIn('customer_id', $rows->getCollection()->pluck('id'))
                ->where('status', 'posted')
                ->whereRaw('paid_amount + 0.004 < total + coalesce(previous_due, 0)')
                ->orderBy('sold_at')
                ->orderBy('id')
                ->get()
                ->groupBy('customer_id')
            : collect();

        return view('commerce.reports.outstanding', [
            'rows' => $rows,
            'openBills' => $openBills,
            'filters' => compact('search', 'show', 'sort'),
            'customerCount' => (int) $totals->customers,
            'total' => $money(ltrim($total, '-')),
            'perPage' => $this->perPage($request),
            'company' => $company,
            'money' => $money,
        ]);
    }

    /**
     * What each supplier sold us, what we paid, and what we still owe. Pick a supplier for the
     * date-by-date passbook; leave it empty for one line per supplier.
     */
    public function suppliers(Request $request, NumberFormatService $format, CompanyContext $context): View|Response
    {
        $this->authorize('reports.view');
        $company = $context->company();
        $today = now()->toDateString();
        $first = LedgerEntry::query()->where('party_type', PartyType::Supplier)->min('occurred_at');
        $download = $request->boolean('pdf');

        if ($download) {
            $request->merge(['all' => 1]);
        }

        if ($request->query('range') === 'all') {
            $from = $first ? Carbon::parse($first)->toDateString() : $today;
            $to = $today;
        } else {
            $from = $this->dateQuery($request->query('from')) ?? now()->startOfMonth()->toDateString();
            $to = $this->dateQuery($request->query('to')) ?? $today;
        }

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $supplierUuid = trim((string) $request->query('supplier', ''));
        $supplier = $supplierUuid === '' ? null : Supplier::query()->where('uuid', $supplierUuid)->first();
        $money = fn (string $amount) => $format->money($amount, $company);
        $ranges = [
            'This month' => [now()->startOfMonth()->toDateString(), $today],
            'Last month' => [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()],
            'Last 3 months' => [now()->subMonthsNoOverflow(2)->startOfMonth()->toDateString(), $today],
            'All time' => [$first ? Carbon::parse($first)->toDateString() : $today, $today],
        ];

        $data = [
            'supplier' => $supplier,
            'suppliers' => Supplier::query()->orderBy('name')->get(['uuid', 'code', 'name', 'is_active']),
            'filters' => ['supplier' => $supplier?->uuid ?? '', 'from' => $from, 'to' => $to],
            'ranges' => $ranges,
            'perPage' => $this->perPage($request),
            'company' => $company,
            'money' => $money,
        ];

        $data += $supplier
            ? $this->supplierPassbook($supplier, $from, $to, $format, $company)
            : $this->supplierSummary($request, $from, $to, $money);

        if ($download) {
            $name = 'supplier-passbook-'.($supplier ? Str::slug($supplier->code) : 'all-suppliers').'-'.$from.'-to-'.$to.'.pdf';

            return Pdf::download('commerce.reports.suppliers-pdf', $data, $name);
        }

        return view('commerce.reports.suppliers', $data);
    }

    /**
     * @return array<string, mixed>
     */
    private function supplierSummary(Request $request, string $from, string $to, \Closure $money): array
    {
        $returnType = (new PurchaseReturn)->getMorphClass();
        $credit = "direction = 'credit'";
        $inRange = 'date(occurred_at) between ? and ?';
        $ledger = LedgerEntry::query()
            ->select('party_id')
            ->selectRaw("sum(case when date(occurred_at) < ? then (case when {$credit} then amount else -amount end) else 0 end) as opening", [$from])
            ->selectRaw("sum(case when {$inRange} and {$credit} then amount else 0 end) as purchased", [$from, $to])
            ->selectRaw("sum(case when {$inRange} and direction = 'debit' and reference_type = ? then amount else 0 end) as returned", [$from, $to, $returnType])
            ->selectRaw("sum(case when {$inRange} and direction = 'debit' and (reference_type is null or reference_type <> ?) then amount else 0 end) as paid", [$from, $to, $returnType])
            ->selectRaw("sum(case when date(occurred_at) <= ? then (case when {$credit} then amount else -amount end) else 0 end) as closing", [$to])
            ->where('party_type', PartyType::Supplier->value)
            ->groupBy('party_id');
        $query = Supplier::query()
            ->joinSub($ledger, 'ledger', 'ledger.party_id', '=', 'suppliers.id')
            ->whereRaw('(abs(ledger.closing) > 0.004 or ledger.purchased > 0 or ledger.returned > 0 or ledger.paid > 0)');

        $totals = DB::query()
            ->fromSub((clone $query)->select('ledger.opening', 'ledger.purchased', 'ledger.returned', 'ledger.paid', 'ledger.closing'), 'rows')
            ->selectRaw('count(*) as suppliers, coalesce(sum(opening), 0) as opening, coalesce(sum(purchased), 0) as purchased, coalesce(sum(returned), 0) as returned, coalesce(sum(paid), 0) as paid, coalesce(sum(closing), 0) as closing')
            ->first();

        $query->select('suppliers.*', 'ledger.opening', 'ledger.purchased', 'ledger.returned', 'ledger.paid', 'ledger.closing')
            ->orderBy('suppliers.name')
            ->orderBy('suppliers.id');

        return [
            'rows' => $this->page($query, $request, (int) $totals->suppliers),
            'supplierCount' => (int) $totals->suppliers,
            'sums' => [
                'opening' => $this->decimal($totals->opening),
                'purchased' => $this->decimal($totals->purchased),
                'returned' => $this->decimal($totals->returned),
                'paid' => $this->decimal($totals->paid),
                'closing' => $this->decimal($totals->closing),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function supplierPassbook(Supplier $supplier, string $from, string $to, NumberFormatService $format, \App\Models\Company $company): array
    {
        $entries = LedgerEntry::query()
            ->where('party_type', PartyType::Supplier)
            ->where('party_id', $supplier->id)
            ->whereDate('occurred_at', '<=', $to)
            ->orderByRaw('date(occurred_at) asc')
            ->orderBy('id')
            ->get();

        $day = fn (LedgerEntry $entry): string => $entry->occurred_at->timezone(config('app.timezone'))->toDateString();
        $opening = BigDecimal::zero();
        $inRange = collect();

        foreach ($entries as $entry) {
            if ($day($entry) < $from) {
                $opening = $this->owed($opening, $entry);
            } else {
                $inRange->push($entry);
            }
        }

        $ids = fn (string $class) => $inRange->where('reference_type', (new $class)->getMorphClass())->pluck('reference_id');
        $purchases = Purchase::query()->whereIn('id', $ids(Purchase::class))->withCount('lines')->withSum('lines', 'gross_weight')->get()->keyBy('id');
        $payments = Payment::query()->whereIn('id', $ids(Payment::class))->get()->keyBy('id');
        $purchaseType = (new Purchase)->getMorphClass();
        $paymentType = (new Payment)->getMorphClass();
        $returnType = (new PurchaseReturn)->getMorphClass();

        $running = $opening;
        $totals = ['purchased' => BigDecimal::zero(), 'returned' => BigDecimal::zero(), 'paid' => BigDecimal::zero()];
        $rows = [];

        foreach ($inRange as $entry) {
            $amount = BigDecimal::of((string) $entry->amount);
            $running = $this->owed($running, $entry);
            $row = [
                'date' => $day($entry),
                'title' => $entry->narration,
                'detail' => null,
                'link' => null,
                'purchased' => null,
                'returned' => null,
                'paid' => null,
                'balance' => (string) $running->toScale(2, RoundingMode::HalfUp),
            ];

            if ($entry->direction === LedgerDirection::Credit) {
                $row['purchased'] = (string) $amount;
                $totals['purchased'] = $totals['purchased']->plus($amount);
            } elseif ($entry->reference_type === $returnType) {
                $row['returned'] = (string) $amount;
                $totals['returned'] = $totals['returned']->plus($amount);
            } else {
                $row['paid'] = (string) $amount;
                $totals['paid'] = $totals['paid']->plus($amount);
            }

            if ($entry->reference_type === $purchaseType && ($purchase = $purchases->get($entry->reference_id))) {
                $row['title'] = 'Purchase '.$purchase->number;
                $row['link'] = route('purchases.show', $purchase);
                $row['detail'] = collect([
                    $purchase->supplier_bill_number ? 'Bill '.$purchase->supplier_bill_number : null,
                    $purchase->lines_count.' '.($purchase->lines_count === 1 ? 'piece' : 'pieces'),
                    $format->weight($this->decimal($purchase->lines_sum_gross_weight ?? 0, 3), $company).' gross',
                ])->filter()->join(' · ');
            } elseif ($entry->reference_type === $paymentType && ($payment = $payments->get($entry->reference_id))) {
                $row['title'] = 'Paid · '.$payment->number;
                $row['detail'] = collect([
                    $payment->method instanceof PaymentMethod ? $payment->method->label() : (string) $payment->method,
                    $payment->reference ? 'Ref '.$payment->reference : null,
                ])->filter()->join(' · ');
            }

            $rows[] = $row;
        }

        return [
            'passbook' => $rows,
            'sums' => [
                'opening' => (string) $opening->toScale(2, RoundingMode::HalfUp),
                'purchased' => (string) $totals['purchased']->toScale(2, RoundingMode::HalfUp),
                'returned' => (string) $totals['returned']->toScale(2, RoundingMode::HalfUp),
                'paid' => (string) $totals['paid']->toScale(2, RoundingMode::HalfUp),
                'closing' => (string) $running->toScale(2, RoundingMode::HalfUp),
            ],
        ];
    }

    /**
     * What we owe the supplier after this row: bills add to it, payments and returns take away.
     */
    private function owed(BigDecimal $owed, LedgerEntry $entry): BigDecimal
    {
        $amount = BigDecimal::of((string) $entry->amount);

        return $entry->direction === LedgerDirection::Credit ? $owed->plus($amount) : $owed->minus($amount);
    }

    public function trays(Request $request, NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('reports.view');
        $company = $context->company();
        $trayUuid = trim((string) $request->query('tray', ''));
        $from = $this->dateQuery($request->query('from'));
        $to = $this->dateQuery($request->query('to'));
        $tray = $trayUuid === '' ? null : StockTray::query()->where('uuid', $trayUuid)->first();
        $sales = collect();

        if ($tray) {
            $sales = Sale::query()
                ->with(['customer', 'lines'])
                ->where('stock_tray_id', $tray->id)
                ->where('status', 'posted')
                ->when($from, fn ($query) => $query->whereDate('sold_at', '>=', $from))
                ->when($to, fn ($query) => $query->whereDate('sold_at', '<=', $to))
                ->orderByDesc('sold_at')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString();
        }

        $weight = fn (string $amount) => $format->weight($amount, $company);

        return view('commerce.reports.trays', [
            'trays' => StockTray::query()->orderBy('sort_order')->orderBy('name')->get(['uuid', 'code', 'name', 'grams', 'pieces', 'is_active']),
            'tray' => $tray,
            'trayUuid' => $trayUuid,
            'from' => $from ?? '',
            'to' => $to ?? '',
            'sales' => $sales,
            'weight' => $weight,
            'company' => $company,
        ]);
    }

    public function otherItems(Request $request, NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('reports.view');
        $company = $context->company();
        $from = $this->dateQuery($request->query('from')) ?? now()->startOfMonth()->toDateString();
        $to = $this->dateQuery($request->query('to')) ?? now()->toDateString();

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $search = trim((string) $request->query('search', ''));
        $query = SaleRetailLine::query()
            ->select('sale_retail_lines.*')
            ->join('sales', 'sales.id', '=', 'sale_retail_lines.sale_id')
            ->with(['sale.customer'])
            ->where('sales.status', 'posted')
            ->whereDate('sales.sold_at', '>=', $from)
            ->whereDate('sales.sold_at', '<=', $to)
            ->when($search !== '', function ($rows) use ($search): void {
                $like = $this->like($search);
                $rows->where(fn ($inner) => $inner->where('sale_retail_lines.name', 'like', $like)
                    ->orWhere('sales.number', 'like', $like));
            })
            ->orderByDesc('sales.sold_at')
            ->orderByDesc('sale_retail_lines.id');

        $totals = (clone $query)->toBase()
            ->selectRaw('count(*) as lines, coalesce(sum(quantity), 0) as qty, coalesce(sum(line_amount), 0) as amount, coalesce(sum(tax_amount), 0) as tax')
            ->first();

        $money = fn (string $amount) => $format->money($amount, $company);

        return view('commerce.reports.other-items', [
            'lines' => $this->page($query, $request, (int) $totals->lines),
            'filters' => compact('search', 'from', 'to'),
            'lineCount' => (int) $totals->lines,
            'quantity' => (int) $totals->qty,
            'amount' => $money($this->decimal($totals->amount)),
            'tax' => $money($this->decimal($totals->tax)),
            'perPage' => $this->perPage($request),
            'company' => $company,
            'money' => $money,
        ]);
    }

    private function page(Builder $query, Request $request, int $total): LengthAwarePaginator
    {
        $size = $request->boolean('all') ? max(1, $total) : $this->perPage($request);
        $page = max(1, min((int) $request->query('page', 1), (int) ceil(max(1, $total) / $size)));

        return $query->paginate($size, ['*'], 'page', $page)->withQueryString();
    }

    private function perPage(Request $request): int
    {
        $size = (int) $request->query('per_page', self::PAGE_SIZES[0]);

        return in_array($size, self::PAGE_SIZES, true) ? $size : self::PAGE_SIZES[0];
    }

    private function like(string $search): string
    {
        return '%'.addcslashes($search, '%_\\').'%';
    }

    private function decimal(mixed $value, int $scale = 2): string
    {
        return number_format((float) $value, $scale, '.', '');
    }

    private function dateQuery(mixed $value): ?string
    {
        $date = trim((string) $value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $date)->format('Y-m-d') === $date ? $date : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
