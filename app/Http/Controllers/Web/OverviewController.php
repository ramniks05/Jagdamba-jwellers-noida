<?php

namespace App\Http\Controllers\Web;

use App\Enums\DocumentType;
use App\Enums\ItemStatus;
use App\Enums\PartyType;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\DocumentSequence;
use App\Models\FinancialYear;
use App\Models\GirviPledge;
use App\Models\GoldScheme;
use App\Models\Item;
use App\Models\LedgerEntry;
use App\Models\MetalRate;
use App\Models\Payment;
use App\Models\RepairOrder;
use App\Models\Sale;
use App\Models\SchemeEnrollment;
use App\Models\SchemeInstallment;
use App\Services\Foundation\DocumentNumberService;
use App\Services\Foundation\NumberFormatService;
use App\Services\Foundation\SettingService;
use App\Support\CompanyContext;
use App\Support\TenantScopeKey;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OverviewController extends Controller
{
    public function index(
        CompanyContext $context,
        DocumentNumberService $numbers,
        NumberFormatService $format,
        SettingService $settings,
    ): View {
        $company = $context->company();
        $this->authorize('dashboard', $company);

        $branches = Branch::query()->visibleTo(auth()->user())->orderByDesc('is_head_office')->orderBy('name')->get();
        $year = FinancialYear::query()->where('is_current', true)->first();
        $invoice = DocumentSequence::query()
            ->where('document_type', DocumentType::Invoice)
            ->where('scope_key', TenantScopeKey::COMPANY)
            ->first();

        $invoicePreview = null;
        $invoicePreviewError = null;

        if ($invoice) {
            try {
                $invoicePreview = $numbers->preview($invoice);
            } catch (ValidationException $exception) {
                $invoicePreviewError = collect($exception->errors())->flatten()->first();
            }
        }

        $timezone = (string) $settings->get('datetime.timezone', $company);
        $moment = now()->timezone($timezone);
        $today = $moment->toDateString();
        $money = fn (string $amount) => $format->money($amount, $company);
        $canSales = auth()->user()->can('sales.view');
        $canPurchase = auth()->user()->can('purchase.view');
        $canRepairs = auth()->user()->can('viewAny', RepairOrder::class);
        $collect = $canSales ? $this->moneyToCollect($money) : null;
        $cashOut = ($canSales || $canPurchase) ? $this->cashOut($today, $canSales, $canPurchase, $money) : null;
        $openGirvi = $canSales
            ? GirviPledge::query()->with('customer')->where('status', 'open')->orderByDesc('pledged_at')->limit(6)->get()
            : collect();
        $dueInstallments = auth()->user()->can('viewAny', GoldScheme::class)
            ? SchemeInstallment::query()
                ->with(['enrollment.customer', 'enrollment.scheme'])
                ->whereNull('paid_at')
                ->whereDate('due_on', '<=', $today)
                ->orderBy('due_on')
                ->limit(6)
                ->get()
            : collect();

        return view('foundation.overview', [
            'company' => $company,
            'branches' => $branches,
            'currentBranch' => $branches->firstWhere('is_head_office', true) ?? $branches->first(),
            'year' => $year,
            'invoicePreview' => $invoicePreview,
            'invoicePreviewError' => $invoicePreviewError,
            'rateGroups' => $this->todayRates(),
            'openGirvi' => $openGirvi,
            'girviCount' => $canSales ? GirviPledge::query()->where('status', 'open')->count() : 0,
            'girviPrincipal' => $canSales ? $money((string) (GirviPledge::query()->where('status', 'open')->sum('principal') ?: 0)) : null,
            'schemeMembers' => auth()->user()->can('viewAny', GoldScheme::class)
                ? SchemeEnrollment::query()->where('status', 'active')->count()
                : null,
            'dueInstallments' => $dueInstallments,
            'dueCount' => auth()->user()->can('viewAny', GoldScheme::class)
                ? SchemeInstallment::query()->whereNull('paid_at')->whereDate('due_on', '<=', $today)->count()
                : 0,
            'cashOut' => $cashOut['total'] ?? null,
            'cashOutParts' => $cashOut['parts'] ?? [],
            'collectTotal' => $collect['total'] ?? null,
            'collectCount' => $collect['count'] ?? 0,
            'collectRows' => $collect['rows'] ?? collect(),
            'openRepairs' => $canRepairs
                ? RepairOrder::query()->with('customer')->whereNotIn('status', ['delivered', 'cancelled'])->orderBy('expected_on')->limit(6)->get()
                : collect(),
            'repairCount' => $canRepairs
                ? RepairOrder::query()->whereNotIn('status', ['delivered', 'cancelled'])->count()
                : null,
            'today' => $today,
            'availablePieces' => auth()->user()->can('inventory.view')
                ? Item::query()->where('status', ItemStatus::Available)->count()
                : null,
            'todaySales' => $canSales
                ? $money((string) (Sale::query()->whereDate('sold_at', $today)->sum('total') ?: 0))
                : null,
            'todayBills' => $canSales
                ? Sale::query()->whereDate('sold_at', $today)->count()
                : null,
            'money' => $money,
            'timezone' => $timezone,
        ]);
    }

    /**
     * @param  callable(string): string  $money
     * @return array{total: string, count: int, rows: Collection<int, array{customer: Customer, balance: string}>}
     */
    private function moneyToCollect(callable $money): array
    {
        $balances = LedgerEntry::query()
            ->select('party_id')
            ->selectRaw("sum(case when direction = 'debit' then amount else -amount end) as balance")
            ->where('party_type', PartyType::Customer->value)
            ->groupBy('party_id')
            ->having('balance', '>', 0)
            ->get();
        $customers = Customer::query()->whereIn('id', $balances->pluck('party_id'))->get()->keyBy('id');
        $rows = $balances->map(function ($row) use ($customers) {
            $customer = $customers->get($row->party_id);

            return $customer ? ['customer' => $customer, 'amount' => (string) $row->balance] : null;
        })->filter()->sort(fn (array $left, array $right) => BigDecimal::of($right['amount'])->compareTo($left['amount']))->values();
        $total = $rows->reduce(fn (BigDecimal $carry, array $row) => $carry->plus($row['amount']), BigDecimal::zero());

        return [
            'total' => $money((string) $total),
            'count' => $rows->count(),
            'rows' => $rows->take(6)->map(fn (array $row) => [
                'customer' => $row['customer'],
                'balance' => $money($row['amount']),
            ])->values(),
        ];
    }

    /**
     * @param  callable(string): string  $money
     * @return array{total: string, detail: string, parts: array<int, string>}
     */
    private function cashOut(string $today, bool $canSales, bool $canPurchase, callable $money): array
    {
        $parts = [];
        $total = BigDecimal::zero();

        if ($canPurchase) {
            $purchases = $this->paidOut('purchase_id', $today);
            $total = $total->plus($purchases);
            $parts[] = 'Purchases '.$money($purchases);
        }

        if ($canSales) {
            $oldGold = $this->paidOut('old_gold_exchange_id', $today);
            $girvi = $this->paidOut('girvi_pledge_id', $today);
            $total = $total->plus($oldGold)->plus($girvi);
            $parts[] = 'Old gold '.$money($oldGold);
            if (BigDecimal::of($girvi)->isPositive()) {
                $parts[] = 'Girvi '.$money($girvi);
            }
        }

        return [
            'total' => $money((string) $total),
            'detail' => implode(' · ', $parts),
            'parts' => $parts,
        ];
    }

    private function paidOut(string $column, string $today): string
    {
        return (string) (Payment::query()
            ->where('direction', 'out')
            ->whereNotNull($column)
            ->whereDate('received_at', $today)
            ->sum('amount') ?: 0);
    }

    /**
     * @return Collection<string, Collection<int, MetalRate>>
     */
    private function todayRates(): Collection
    {
        if (! auth()->user()->can('viewAny', MetalRate::class)) {
            return collect();
        }

        return MetalRate::query()
            ->with(['metalType', 'purity'])
            ->inForce()
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->get()
            ->unique(fn (MetalRate $rate) => $rate->metal_type_id.'-'.$rate->purity_id)
            ->sortByDesc(fn (MetalRate $rate) => (string) ($rate->purity?->fineness ?? '0'))
            ->groupBy(fn (MetalRate $rate) => $rate->metalType?->name ?? 'Metal');
    }
}
