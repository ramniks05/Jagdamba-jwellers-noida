<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Enums\LedgerDirection;
use App\Enums\PartyType;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\OldGoldRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\LedgerEntry;
use App\Models\MetalRate;
use App\Models\MetalType;
use App\Models\OldGoldExchange;
use App\Models\Payment;
use App\Services\Commerce\OldGoldService;
use App\Services\Foundation\NumberFormatService;
use App\Services\Foundation\SettingService;
use App\Support\CompanyContext;
use App\Support\RupeesInWords;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OldGoldController extends Controller
{
    public function index(Request $request, NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('viewAny', OldGoldExchange::class);
        $search = trim((string) $request->query('search', ''));

        return view('commerce.old-gold.index', [
            'exchanges' => OldGoldExchange::query()
                ->with(['customer', 'metalType', 'purity'])
                ->withSum('payments', 'amount')
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($inner) use ($search): void {
                        $inner->where('number', 'like', '%'.$search.'%')
                            ->orWhereHas('customer', function ($customers) use ($search): void {
                                $customers->where('name', 'like', '%'.$search.'%')
                                    ->orWhere('mobile', 'like', '%'.$search.'%');
                            });
                    });
                })
                ->orderByDesc('exchanged_at')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString(),
            'search' => $search,
            'money' => fn (string $amount) => $format->money($amount, $context->company()),
            'weight' => fn (string $amount) => $format->weight($amount, $context->company()),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', OldGoldExchange::class);

        $customers = Customer::query()->where('is_active', true)->orderBy('name')->get();

        return view('commerce.old-gold.create', [
            'customers' => $customers,
            'balances' => $this->customerBalances($customers->pluck('id')->all()),
            'metals' => MetalType::query()->with('purities')->where('is_active', true)->orderBy('name')->get(),
            'rates' => MetalRate::query()->orderByDesc('effective_at')->orderByDesc('id')->get(['metal_type_id', 'purity_id', 'branch_id', 'rate_per_gram']),
            'branchId' => Branch::query()->where('is_head_office', true)->value('id'),
            'methods' => PaymentMethod::cases(),
        ]);
    }

    public function store(OldGoldRequest $request, OldGoldService $exchanges, CompanyContext $context): RedirectResponse
    {
        $exchange = $exchanges->exchange($context->company(), $request->validated(), $request->user()?->id);

        return redirect()->route('old-gold.show', $exchange)->with('status', 'Old gold '.$exchange->number.' saved.');
    }

    public function show(OldGoldExchange $exchange, NumberFormatService $format, SettingService $settings, CompanyContext $context): View
    {
        $this->authorize('view', $exchange);
        $company = $context->company();
        $exchange->load(['customer', 'metalType', 'purity', 'payments', 'pieceMovements.item']);
        $refund = $exchange->payments
            ->reduce(fn (BigDecimal $sum, Payment $payment) => $sum->plus((string) $payment->amount), BigDecimal::zero())
            ->toScale(2, RoundingMode::HalfUp);
        $metalValue = BigDecimal::of((string) $exchange->melted_weight)
            ->multipliedBy((string) $exchange->rate_per_gram)
            ->toScale(2, RoundingMode::HalfUp);

        return view('commerce.old-gold.show', [
            'exchange' => $exchange,
            'company' => $company,
            'refund' => (string) $refund,
            'kept' => (string) BigDecimal::of((string) $exchange->exchange_value)->minus($refund)->toScale(2, RoundingMode::HalfUp),
            'metalValue' => (string) $metalValue,
            'pieceGrossLeft' => (string) $exchange->pieceMovements
                ->reduce(fn (BigDecimal $left, $movement) => $left->minus((string) $movement->gross_weight), BigDecimal::of((string) $exchange->gross_weight))
                ->toScale(3, RoundingMode::HalfUp),
            'valueWords' => RupeesInWords::format((string) $exchange->exchange_value),
            'showLogo' => (bool) $settings->get('invoice.show_logo', $company),
            'footer' => (string) ($settings->get('invoice.footer_note', $company) ?? ''),
            'money' => fn (string $amount) => $format->money($amount, $company),
            'weight' => fn (string $amount) => $format->weight($amount, $company),
        ]);
    }

    /**
     * @param  array<int, int>  $customerIds
     * @return array<int, string>
     */
    private function customerBalances(array $customerIds): array
    {
        $balances = [];

        if ($customerIds === []) {
            return $balances;
        }

        $rows = LedgerEntry::query()
            ->where('party_type', PartyType::Customer)
            ->whereIn('party_id', $customerIds)
            ->get(['party_id', 'direction', 'amount']);

        foreach ($rows as $row) {
            $current = isset($balances[$row->party_id])
                ? BigDecimal::of($balances[$row->party_id])
                : BigDecimal::zero();
            $amount = BigDecimal::of((string) $row->amount);
            $next = $row->direction === LedgerDirection::Debit
                ? $current->plus($amount)
                : $current->minus($amount);
            $balances[$row->party_id] = (string) $next->toScale(2, RoundingMode::HalfUp);
        }

        return $balances;
    }
}
