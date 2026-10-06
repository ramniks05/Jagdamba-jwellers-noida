<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Enums\ItemStatus;
use App\Enums\PartyType;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Item;
use App\Models\LedgerEntry;
use App\Models\MetalType;
use App\Models\Sale;
use App\Services\Foundation\NumberFormatService;
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function stock(Request $request, NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('reports.view');
        $company = $context->company();
        $search = trim((string) $request->query('search', ''));
        $metal = trim((string) $request->query('metal', ''));
        $query = Item::query()
            ->with(['metalType', 'purity', 'category', 'location.branch'])
            ->whereIn('status', [ItemStatus::Available, ItemStatus::Reserved]);

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('item_code', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%')
                    ->orWhere('huid', 'like', '%'.$search.'%');
            });
        }
        if ($metal !== '') {
            $query->whereHas('metalType', fn ($metals) => $metals->where('uuid', $metal));
        }

        $pieces = $query->orderBy('item_code')->get();
        $net = $pieces->reduce(fn (BigDecimal $carry, Item $item) => $carry->plus((string) $item->net_weight), BigDecimal::zero());
        $byMetal = $pieces->groupBy(fn (Item $item) => trim(($item->metalType?->name ?? '').' '.($item->purity?->name ?? '')))->map(fn ($rows) => [
            'pieces' => $rows->count(),
            'net' => $format->weight((string) $rows->reduce(fn (BigDecimal $carry, Item $item) => $carry->plus((string) $item->net_weight), BigDecimal::zero()), $company),
        ]);

        return view('commerce.reports.stock', [
            'pieces' => $pieces,
            'byMetal' => $byMetal,
            'metals' => MetalType::query()->where('is_active', true)->orderBy('name')->get(),
            'search' => $search,
            'metal' => $metal,
            'pieceCount' => $pieces->count(),
            'netTotal' => $format->weight((string) $net, $company),
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
        $search = trim((string) $request->query('search', ''));
        $query = Sale::query()->with('customer')->whereDate('sold_at', '>=', $from)->whereDate('sold_at', '<=', $to);
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('number', 'like', '%'.$search.'%')
                    ->orWhereHas('customer', function ($customers) use ($search): void {
                        $customers->where('name', 'like', '%'.$search.'%')
                            ->orWhere('mobile', 'like', '%'.$search.'%');
                    });
            });
        }
        $sales = $query->orderBy('sold_at')->orderBy('id')->get();
        $sum = fn (string $column) => $sales->reduce(fn (BigDecimal $carry, Sale $sale) => $carry->plus((string) $sale->{$column}), BigDecimal::zero());
        $billed = $sum('total');
        $received = $sum('paid_amount');

        return view('commerce.reports.sales', [
            'from' => $from,
            'to' => $to,
            'search' => $search,
            'sales' => $sales,
            'company' => $company,
            'billCount' => $sales->count(),
            'tax' => $format->money((string) $sum('tax_amount'), $company),
            'total' => $format->money((string) $billed, $company),
            'paid' => $format->money((string) $received, $company),
            'due' => $format->money((string) $billed->minus($received), $company),
            'money' => fn (string $amount) => $format->money($amount, $company),
        ]);
    }

    public function outstanding(Request $request, NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('reports.view');
        $company = $context->company();
        $search = mb_strtolower(trim((string) $request->query('search', '')));
        $balances = LedgerEntry::query()
            ->select('party_id', DB::raw("sum(case when direction = 'debit' then amount else -amount end) as balance"))
            ->where('party_type', PartyType::Customer->value)
            ->groupBy('party_id')
            ->having('balance', '>', 0)
            ->get();
        $customers = Customer::query()->whereIn('id', $balances->pluck('party_id'))->get()->keyBy('id');
        $rows = $balances->map(fn ($row) => [
            'customer' => $customers->get($row->party_id),
            'balance' => (string) $row->balance,
        ])->filter(function (array $row) use ($search): bool {
            if (! $row['customer']) {
                return false;
            }
            if ($search === '') {
                return true;
            }

            return str_contains(mb_strtolower($row['customer']->name.' '.$row['customer']->mobile.' '.$row['customer']->code), $search);
        })->values();
        $outstanding = $rows->reduce(fn (BigDecimal $carry, array $row) => $carry->plus($row['balance']), BigDecimal::zero());

        return view('commerce.reports.outstanding', [
            'rows' => $rows,
            'search' => trim((string) $request->query('search', '')),
            'company' => $company,
            'total' => $format->money((string) $outstanding, $company),
            'money' => fn (string $amount) => $format->money($amount, $company),
        ]);
    }

    private function dateQuery(mixed $value): ?string
    {
        $date = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : null;
    }
}
