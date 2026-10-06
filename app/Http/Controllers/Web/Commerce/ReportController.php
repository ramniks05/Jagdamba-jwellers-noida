<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Enums\ItemStatus;
use App\Enums\PartyType;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Item;
use App\Models\LedgerEntry;
use App\Models\Sale;
use App\Services\Foundation\NumberFormatService;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function stock(NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('reports.view');
        $pieces = Item::query()->with(['metalType', 'purity', 'category', 'location.branch'])->whereIn('status', [ItemStatus::Available, ItemStatus::Reserved])->orderBy('item_code')->get();

        $byMetal = $pieces->groupBy(fn (Item $item) => $item->metalType?->name.' '.$item->purity?->name)->map(fn ($rows) => [
            'pieces' => $rows->count(),
            'net' => $format->weight((string) $rows->sum(fn (Item $item) => (float) $item->net_weight), $context->company()),
        ]);

        return view('commerce.reports.stock', [
            'pieces' => $pieces,
            'byMetal' => $byMetal,
            'weight' => fn (string $amount) => $format->weight($amount, $context->company()),
        ]);
    }

    public function sales(Request $request, NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('reports.view');
        $from = (string) $request->query('from', now()->toDateString());
        $to = (string) $request->query('to', now()->toDateString());
        $sales = Sale::query()->with('customer')->whereDate('sold_at', '>=', $from)->whereDate('sold_at', '<=', $to)->orderByDesc('sold_at')->get();

        return view('commerce.reports.sales', [
            'from' => $from,
            'to' => $to,
            'sales' => $sales,
            'total' => $format->money((string) $sales->sum(fn (Sale $sale) => (float) $sale->total), $context->company()),
            'money' => fn (string $amount) => $format->money($amount, $context->company()),
        ]);
    }

    public function outstanding(NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('reports.view');
        $balances = LedgerEntry::query()
            ->select('party_id', DB::raw("sum(case when direction = 'debit' then amount else -amount end) as balance"))
            ->where('party_type', PartyType::Customer->value)
            ->groupBy('party_id')
            ->having('balance', '>', 0)
            ->get();
        $customers = Customer::query()->whereIn('id', $balances->pluck('party_id'))->get()->keyBy('id');

        return view('commerce.reports.outstanding', [
            'rows' => $balances->map(fn ($row) => [
                'customer' => $customers->get($row->party_id),
                'balance' => $format->money((string) $row->balance, $context->company()),
            ])->filter(fn ($row) => $row['customer']),
        ]);
    }
}
