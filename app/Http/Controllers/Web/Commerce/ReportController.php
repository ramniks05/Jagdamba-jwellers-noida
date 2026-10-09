<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Enums\ItemStatus;
use App\Enums\PartyType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Item;
use App\Models\LedgerEntry;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\Sale;
use App\Models\StockLocation;
use App\Services\Foundation\NumberFormatService;
use App\Support\CompanyContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    private const PAGE_SIZES = [25, 50, 100];

    public function stock(Request $request, NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('reports.view');
        $company = $context->company();
        $search = trim((string) $request->query('search', ''));
        $metal = trim((string) $request->query('metal', ''));
        $category = trim((string) $request->query('category', ''));
        $location = trim((string) $request->query('location', ''));
        $status = in_array($request->query('status'), ['available', 'reserved'], true) ? (string) $request->query('status') : 'all';
        $sort = in_array($request->query('sort'), ['code', 'newest', 'heaviest'], true) ? (string) $request->query('sort') : 'code';

        $query = Item::query()
            ->when($status === 'all', fn ($rows) => $rows->whereIn('status', [ItemStatus::Available, ItemStatus::Reserved]))
            ->when($status !== 'all', fn ($rows) => $rows->where('status', $status))
            ->when($search !== '', function ($rows) use ($search): void {
                $like = $this->like($search);
                $rows->where(fn ($inner) => $inner->where('item_code', 'like', $like)->orWhere('name', 'like', $like)->orWhere('huid', 'like', $like)->orWhere('barcode', 'like', $like));
            })
            ->when($metal !== '', fn ($rows) => $rows->whereHas('metalType', fn ($metals) => $metals->where('uuid', $metal)))
            ->when($category !== '', fn ($rows) => $rows->where('category_id', (int) $category))
            ->when($location !== '', fn ($rows) => $rows->whereHas('location', fn ($places) => $places->where('uuid', $location)));

        $totals = (clone $query)->toBase()
            ->selectRaw('count(*) as pieces, coalesce(sum(gross_weight), 0) as gross, coalesce(sum(net_weight), 0) as net')
            ->first();
        $groups = (clone $query)->toBase()
            ->selectRaw('metal_type_id, purity_id, count(*) as pieces, coalesce(sum(net_weight), 0) as net')
            ->groupBy('metal_type_id', 'purity_id')
            ->get();
        $metalNames = MetalType::query()->whereIn('id', $groups->pluck('metal_type_id')->filter())->pluck('name', 'id');
        $purityNames = Purity::query()->whereIn('id', $groups->pluck('purity_id')->filter())->pluck('name', 'id');
        $byMetal = $groups
            ->map(fn ($row) => [
                'label' => trim(($metalNames[$row->metal_type_id] ?? 'Other').' '.($purityNames[$row->purity_id] ?? '')),
                'pieces' => (int) $row->pieces,
                'net' => $format->weight($this->decimal($row->net, 3), $company),
            ])
            ->sortBy('label')
            ->values();

        $query->with(['metalType', 'purity', 'category', 'location.branch']);
        match ($sort) {
            'newest' => $query->orderByDesc('id'),
            'heaviest' => $query->orderByDesc('net_weight')->orderBy('item_code'),
            default => $query->orderBy('item_code'),
        };
        $query->orderBy('id');

        return view('commerce.reports.stock', [
            'pieces' => $this->page($query, $request, (int) $totals->pieces),
            'byMetal' => $byMetal,
            'metals' => MetalType::query()->where('is_active', true)->orderBy('name')->get(),
            'categories' => Category::query()->orderBy('name')->get(),
            'locations' => StockLocation::query()->with('branch')->orderBy('name')->get(),
            'filters' => compact('search', 'metal', 'category', 'location', 'status', 'sort'),
            'filtered' => $search !== '' || $metal !== '' || $category !== '' || $location !== '' || $status !== 'all',
            'pieceCount' => (int) $totals->pieces,
            'grossTotal' => $format->weight($this->decimal($totals->gross, 3), $company),
            'netTotal' => $format->weight($this->decimal($totals->net, 3), $company),
            'perPage' => $this->perPage($request),
            'company' => $company,
            'weight' => fn (string $amount) => $format->weight($amount, $company),
        ]);
    }

    public function sales(Request $request, NumberFormatService $format, CompanyContext $context): View
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
            ->whereDate('sold_at', '>=', $from)
            ->whereDate('sold_at', '<=', $to)
            ->when($search !== '', function ($rows) use ($search): void {
                $like = $this->like($search);
                $rows->where(fn ($inner) => $inner->where('number', 'like', $like)
                    ->orWhereHas('customer', fn ($customers) => $customers->where('name', 'like', $like)->orWhere('mobile', 'like', $like)->orWhere('code', 'like', $like)));
            })
            ->when($payment === 'due', fn ($rows) => $rows->whereColumn('total', '>', 'paid_amount'))
            ->when($payment === 'paid', fn ($rows) => $rows->whereColumn('total', '<=', 'paid_amount'));

        $totals = (clone $query)->toBase()
            ->selectRaw('count(*) as bills, coalesce(sum(tax_amount + making_tax_amount), 0) as tax, coalesce(sum(total), 0) as total, coalesce(sum(paid_amount), 0) as paid, coalesce(sum(total - paid_amount), 0) as due')
            ->first();

        $money = fn (string $amount) => $format->money($amount, $company);
        $today = now()->toDateString();

        return view('commerce.reports.sales', [
            'sales' => $this->page($query->with('customer')->orderBy('sold_at')->orderBy('id'), $request, (int) $totals->bills),
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

        return view('commerce.reports.outstanding', [
            'rows' => $this->page($query, $request, (int) $totals->customers),
            'filters' => compact('search', 'show', 'sort'),
            'customerCount' => (int) $totals->customers,
            'total' => $money(ltrim($total, '-')),
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
