<?php

namespace App\Services\Commerce;

use App\Enums\DocumentType;
use App\Enums\InventoryMovement;
use App\Enums\ItemStatus;
use App\Enums\LedgerDirection;
use App\Enums\PartyType;
use App\Enums\PaymentMethod;
use App\Models\AdvanceOrder;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Item;
use App\Models\LedgerEntry;
use App\Models\MetalRate;
use App\Models\MetalStockMovement;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\RetailProduct;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SaleRetailLine;
use App\Models\SaleLineStone;
use App\Models\SaleReturnLine;
use App\Models\SaleSettlement;
use App\Models\StockTray;
use App\Services\Foundation\DocumentNumberService;
use App\Services\Foundation\SettingService;
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(
        private readonly CompanyContext $context,
        private readonly ItemService $items,
        private readonly JewelleryPricer $pricer,
        private readonly RateBook $rates,
        private readonly InventoryService $inventory,
        private readonly LedgerService $ledger,
        private readonly DocumentNumberService $numbers,
        private readonly SettingService $settings,
        private readonly MetalStockService $metalStock,
        private readonly TrayService $trays,
        private readonly RetailLinePricer $retailPricer,
    ) {}

    /**
     * @return array{ready: bool, message?: string, rate?: string, metal?: string, wastage?: string, making?: string, stone?: string, line?: string}
     */
    public function quote(Item $item, ?AdvanceOrder $order = null): array
    {
        $item->loadMissing(['metalType', 'purity', 'makingMethod', 'wastageMethod']);
        $rate = $this->orderRate($order, $item)
            ?? $this->rates->current((int) $item->metal_type_id, (int) $item->purity_id, (int) $item->branch_id);

        if (! $rate || ! $item->metalType || ! $item->purity) {
            $metal = $item->metalType?->name ?: 'this metal';
            $purity = $item->purity?->name ?: '';

            return [
                'ready' => false,
                'message' => 'Enter a '.trim($metal.' '.$purity).' rate before billing this piece.',
            ];
        }

        $priced = $this->pricePiece($item, (string) $rate->rate_per_gram);

        return [
            'ready' => true,
            'rate' => (string) $rate->rate_per_gram,
            'metal' => $priced['metal_amount'],
            'wastage' => $priced['wastage_amount'],
            'making' => $priced['making_amount'],
            'stone' => $priced['stone_amount'],
            'line' => $priced['line_amount'],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    /**
     * Unpaid invoices for each customer: the amount still owed and how many bills it comes from.
     *
     * @param  array<int, int>  $customerIds
     * @return array<int, array{amount: string, bills: int}>
     */
    public function openDues(array $customerIds, ?int $exceptSaleId = null): array
    {
        if ($customerIds === []) {
            return [];
        }

        $rows = Sale::query()
            ->select('customer_id')
            ->selectRaw('count(*) as bills')
            ->selectRaw('coalesce(sum(total + previous_due - paid_amount), 0) as due')
            ->whereIn('customer_id', $customerIds)
            ->when($exceptSaleId, fn ($query) => $query->where('id', '!=', $exceptSaleId))
            ->where('status', 'posted')
            ->whereRaw('paid_amount < total + previous_due')
            ->groupBy('customer_id')
            ->get();

        $dues = [];

        foreach ($rows as $row) {
            $amount = BigDecimal::of((string) $row->due)->toScale(2, RoundingMode::HalfUp);

            if ($amount->isPositive()) {
                $dues[(int) $row->customer_id] = [
                    'amount' => (string) $amount,
                    'bills' => (int) $row->bills,
                ];
            }
        }

        return $dues;
    }

    public function post(Company $company, array $attributes, ?int $userId = null, ?Sale $existing = null): Sale
    {
        return DB::transaction(function () use ($company, $attributes, $userId, $existing) {
            $this->context->ensureId((int) $company->id);
            $keptPrevious = BigDecimal::zero();
            $keptBills = 0;

            if ($existing) {
                $existing = Sale::query()->whereKey($existing->id)->lockForUpdate()->firstOrFail();
                $keptPrevious = BigDecimal::of((string) $existing->previous_due)->toScale(2, RoundingMode::HalfUp);
                $keptBills = (int) $existing->previous_bills;
                $this->release($existing);
            }

            $soldAt = $this->soldAt($attributes, $existing);
            $customer = Customer::query()->where('uuid', $attributes['customer_uuid'])->where('is_active', true)->first();

            if (! $customer) {
                throw ValidationException::withMessages([
                    'customer_uuid' => 'Choose a customer.',
                ]);
            }

            $tray = null;

            if (trim((string) ($attributes['tray_uuid'] ?? '')) !== '') {
                $tray = StockTray::query()
                    ->where('uuid', (string) $attributes['tray_uuid'])
                    ->where('is_active', true)
                    ->first();

                if (! $tray) {
                    throw ValidationException::withMessages([
                        'tray_uuid' => 'Choose an active tray.',
                    ]);
                }
            }

            $order = null;

            if (! empty($attributes['order_uuid'])) {
                $order = AdvanceOrder::query()->where('uuid', $attributes['order_uuid'])->lockForUpdate()->first();

                if (! $order || ! $order->isOpen()) {
                    throw ValidationException::withMessages([
                        'order_uuid' => 'This order is not open any more.',
                    ]);
                }

                if ((int) $order->customer_id !== (int) $customer->id) {
                    throw ValidationException::withMessages([
                        'customer_uuid' => 'Order '.$order->number.' is booked for another customer.',
                    ]);
                }
            }

            $uuids = array_values(array_unique($attributes['item_ids'] ?? []));

            $freshPieces = array_values(array_filter(
                $attributes['new_pieces'] ?? [],
                fn ($row) => is_array($row) && trim((string) ($row['name'] ?? '')) !== '',
            ));

            if (! empty($attributes['new_piece']['name'])) {
                $freshPieces[] = $attributes['new_piece'];
            }

            $pieceRates = [];

            foreach ($freshPieces as $row) {
                $typedRate = trim((string) ($row['rate_per_gram'] ?? ''));
                unset($row['rate_per_gram']);
                $code = $this->nextPieceCode();
                $created = $this->items->create($company, $row + [
                    'item_code' => $code,
                    'sku' => $code,
                    'own_gold' => $row['own'] ?? false,
                ], $userId);
                $uuids[] = $created->uuid;

                if ($typedRate !== '') {
                    $pieceRates[$created->id] = $typedRate;
                }
            }

            $retailBundle = $this->buildRetailLines($company, $attributes['retail_items'] ?? []);
            $retailRows = $retailBundle['lines'];

            if ($uuids === [] && $retailRows === []) {
                throw ValidationException::withMessages([
                    'item_ids' => 'Add jewellery, or an other item (name and price).',
                ]);
            }

            $items = collect();
            $lines = [];
            $linesTotal = BigDecimal::zero();
            $makingTotal = BigDecimal::zero();
            $hallmarkTotal = BigDecimal::zero();
            $trayNet = BigDecimal::zero();
            $branch = null;

            if ($uuids !== []) {
                $items = Item::query()->with(['metalType', 'purity', 'makingMethod', 'wastageMethod', 'branch', 'stones'])->whereIn('uuid', $uuids)->get();

                if ($items->count() !== count($uuids)) {
                    throw ValidationException::withMessages([
                        'item_ids' => 'Choose pieces from this shop.',
                    ]);
                }

                $locked = Item::query()->whereIn('id', $items->modelKeys())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $branchIds = $locked->pluck('branch_id')->unique();

                if ($branchIds->count() !== 1) {
                    throw ValidationException::withMessages([
                        'item_ids' => 'Sell pieces from one branch on a bill.',
                    ]);
                }

                foreach ($items as $item) {
                    $piece = $locked[$item->id];

                    if ($piece->status !== ItemStatus::Available) {
                        throw ValidationException::withMessages([
                            'item_ids' => $piece->item_code.' is not available.',
                        ]);
                    }

                    $rate = $this->rateForPiece($piece, $order, $attributes);
                    $typedRate = $pieceRates[$piece->id] ?? null;

                    if ($typedRate !== null) {
                        $rate = $this->pricedRate($rate, $typedRate);
                    }

                    if (! $rate) {
                        throw ValidationException::withMessages([
                            'item_ids' => 'Enter a '.$item->metalType->name.' '.$item->purity->name.' rate before billing '.$piece->item_code.'.',
                        ]);
                    }

                    $priced = $this->pricePiece($piece, (string) $rate->rate_per_gram);

                    $trayNet = $trayNet->plus((string) $piece->net_weight);

                    $lines[] = ['item' => $piece, 'display' => $item, 'rate' => $rate, 'priced' => $priced];
                    $linesTotal = $linesTotal->plus($priced['line_amount']);
                    $makingTotal = $makingTotal->plus($priced['making_amount']);
                    $hallmarkTotal = $hallmarkTotal->plus($priced['wastage_amount']);
                }

                $branch = $items->first()->branch;
            }

            $branch ??= Branch::query()
                ->where('company_id', $company->id)
                ->where('is_head_office', true)
                ->first()
                ?? Branch::query()->where('company_id', $company->id)->orderBy('id')->firstOrFail();

            $hallmarkOutside = ! $existing || $existing->hallmark_amount !== null;

            $exclusive = $existing
                ? ! $existing->prices_include_tax
                : $this->settings->get('invoice.tax_display', $company) !== 'inclusive';
            $makingMode = in_array($attributes['making_mode'] ?? null, ['inside', 'separate', 'processing'], true)
                ? $attributes['making_mode']
                : (string) ($this->settings->get('pricing.making_mode', $company) ?? 'inside');
            $bill = $this->pricer->billWithMaking(
                (string) $linesTotal->toScale(2, RoundingMode::HalfUp),
                (string) $makingTotal->toScale(2, RoundingMode::HalfUp),
                (string) ($attributes['discount'] ?? '0'),
                $existing ? (string) $existing->tax_percent : (string) ($this->settings->get('pricing.gst_percent', $company) ?? '0'),
                $makingMode,
                $existing ? (string) $existing->making_tax_percent : (string) ($this->settings->get('pricing.making_gst_percent', $company) ?? '0'),
                $exclusive,
                (bool) $this->settings->get('pricing.round_rupee', $company),
                $hallmarkOutside ? (string) $hallmarkTotal->toScale(2, RoundingMode::HalfUp) : '0',
            );

            $retailTotals = $retailBundle['totals'];
            $merchandiseTotal = BigDecimal::of($bill->linesAmount)->plus($retailTotals['line']);
            $taxableTotal = BigDecimal::of($bill->taxableAmount)->plus($retailTotals['taxable']);
            $taxTotal = BigDecimal::of($bill->taxAmount)->plus($retailTotals['tax']);
            $invoiceTotal = BigDecimal::of($bill->total)->plus($retailTotals['line']);

            $paid = BigDecimal::zero();
            $paymentRows = [];

            foreach ($attributes['payments'] ?? [] as $index => $row) {
                $amount = BigDecimal::of((string) $row['amount'])->toScale(2, RoundingMode::HalfUp);

                if ($amount->isNegativeOrZero()) {
                    throw ValidationException::withMessages([
                        'payments' => 'Each payment must be more than zero.',
                    ]);
                }

                $paid = $paid->plus($amount);
                $paymentRows[] = [
                    'method' => PaymentMethod::from($row['method']),
                    'amount' => (string) $amount,
                    'reference' => trim((string) ($row['reference'] ?? '')) ?: null,
                ];
                unset($index);
            }

            $total = $invoiceTotal;
            $advance = BigDecimal::zero();

            if ($order) {
                $advance = BigDecimal::of($order->advanceHeld());
                $advance = $advance->isGreaterThan($total) ? $total : $advance;
            }

            $settleOld = ! $existing && ! $customer->is_system && filter_var($attributes['include_previous'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $openSales = $settleOld
                ? Sale::query()
                    ->where('customer_id', $customer->id)
                    ->where('status', 'posted')
                    ->whereRaw('paid_amount < total + previous_due')
                    ->orderBy('sold_at')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                : collect();
            $previous = $existing ? $keptPrevious : BigDecimal::zero();

            if ($existing && $keptBills > 0 && (int) $existing->customer_id !== (int) $customer->id) {
                throw ValidationException::withMessages([
                    'customer_uuid' => 'This invoice includes old due, so the customer stays the same.',
                ]);
            }

            foreach ($openSales as $open) {
                $previous = $previous->plus($open->balanceDue());
            }

            $previous = $previous->toScale(2, RoundingMode::HalfUp);
            $payable = $total->plus($previous);

            if ($customer->is_system && ! $paid->isEqualTo($total)) {
                throw ValidationException::withMessages([
                    'payments' => 'A walk-in bill must be paid in full.',
                ]);
            }

            $credit = BigDecimal::of(trim((string) ($attributes['use_credit'] ?? '')) === '' ? '0' : (string) $attributes['use_credit'])
                ->toScale(2, RoundingMode::HalfUp);

            if ($credit->isPositive()) {
                $spendable = BigDecimal::of($this->ledger->spendableCredit((int) $customer->id));

                if ($customer->is_system || $credit->isGreaterThan($spendable)) {
                    throw ValidationException::withMessages([
                        'use_credit' => 'This customer has only '.$spendable->toScale(2).' of old gold or credit to use.',
                    ]);
                }

                $remaining = $total->minus($paid)->minus($advance);
                $remaining = $remaining->isNegative() ? BigDecimal::zero() : $remaining;
                $credit = $credit->isGreaterThan($remaining) ? $remaining->toScale(2, RoundingMode::HalfUp) : $credit;
            }

            $extra = BigDecimal::max($paid->plus($advance)->plus($credit)->minus($payable), BigDecimal::zero())->toScale(2, RoundingMode::HalfUp);
            $payload = [
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'stock_tray_id' => $tray?->id,
                'status' => 'posted',
                'lines_amount' => (string) $merchandiseTotal->toScale(2, RoundingMode::HalfUp),
                'discount_amount' => $bill->discountAmount,
                'taxable_amount' => (string) $taxableTotal->toScale(2, RoundingMode::HalfUp),
                'tax_percent' => $existing ? (string) $existing->tax_percent : (string) ($this->settings->get('pricing.gst_percent', $company) ?? '0'),
                'tax_amount' => (string) $taxTotal->toScale(2, RoundingMode::HalfUp),
                'making_mode' => $makingMode,
                'making_amount' => $bill->makingAmount,
                'making_tax_percent' => $bill->makingTaxPercent,
                'making_tax_amount' => $bill->makingTaxAmount,
                'hallmark_amount' => $hallmarkOutside ? $bill->hallmarkAmount : null,
                'prices_include_tax' => ! $exclusive,
                'round_off' => $bill->roundOff,
                'total' => (string) $invoiceTotal->toScale(2, RoundingMode::HalfUp),
                'paid_amount' => (string) BigDecimal::min($paid->plus($advance)->plus($credit), $payable)->toScale(2, RoundingMode::HalfUp),                'advance_amount' => (string) $advance->toScale(2, RoundingMode::HalfUp),
                'credit_amount' => (string) $credit,
                'previous_due' => (string) $previous,
                'previous_bills' => $existing ? $keptBills : $openSales->count(),
                'notes' => trim((string) ($attributes['notes'] ?? '')) ?: null,
                'sold_at' => $soldAt,
            ];

            if ($existing) {
                $existing->fill($payload);
                $existing->save();
                $sale = $existing;
            } else {
                $invoice = $this->numbers->issue($this->numbers->for(DocumentType::Invoice, $branch));
                $sale = Sale::query()->create($payload + [
                    'number' => $invoice->number,
                    'user_id' => $userId,
                ]);
            }

            foreach ($lines as $line) {
                $piece = $line['item'];
                $display = $line['display'];
                $saleLine = SaleLine::query()->create([
                    'company_id' => $company->id,
                    'sale_id' => $sale->id,
                    'item_id' => $piece->id,
                    'metal_rate_id' => $line['rate']->id,
                    'name' => $piece->name,
                    'metal_name' => $display->metalType->name,
                    'purity_name' => $display->purity->name,
                    'hsn_code' => $display->metalType->hsn_code,
                    'item_code' => $piece->own_gold ? 'Own' : $piece->item_code,
                    'gross_weight' => $piece->gross_weight,
                    'stone_weight' => $piece->stone_weight,
                    'other_weight' => $piece->other_weight,
                    'net_weight' => $piece->net_weight,
                    'rate_per_gram' => $line['rate']->rate_per_gram,
                    'making_method' => $display->makingMethod?->code,
                    'making_value' => $piece->making_value,
                    'wastage_method' => $display->wastageMethod?->code,
                    'wastage_value' => $piece->wastage_value,
                    'metal_amount' => $line['priced']['metal_amount'],
                    'wastage_amount' => $line['priced']['wastage_amount'],
                    'making_amount' => $line['priced']['making_amount'],
                    'stone_amount' => $line['priced']['stone_amount'],
                    'line_amount' => $line['priced']['line_amount'],
                ]);
                if (! $piece->own_gold) {
                    $this->metalStock->sell(
                        (int) $sale->branch_id,
                        (int) $piece->metal_type_id,
                        (int) $piece->purity_id,
                        (string) $saleLine->net_weight,
                        $saleLine,
                        $userId,
                    );
                }
                foreach ($display->stones as $index => $stone) {
                    SaleLineStone::query()->create([
                        'company_id' => $company->id,
                        'sale_line_id' => $saleLine->id,
                        'name' => $stone->name,
                        'weight' => $stone->weight,
                        'value' => $stone->value,
                        'rate' => $stone->rate,
                        'rate_unit' => $stone->rate_unit,
                        'position' => $index,
                    ]);
                }
                $this->inventory->apply(
                    $piece,
                    InventoryMovement::Sale,
                    $sale,
                    (string) $line['rate']->rate_per_gram,
                    $line['priced']['line_amount'],
                    'Sold on '.$sale->number,
                    $userId,
                );
            }

            foreach ($retailRows as $retailRow) {
                SaleRetailLine::query()->create([
                    'company_id' => $company->id,
                    'sale_id' => $sale->id,
                    'retail_product_id' => $retailRow['product']?->id,
                    'name' => $retailRow['name'],
                    'quantity' => $retailRow['quantity'],
                    'unit_price' => $retailRow['unit_price'],
                    'tax_mode' => $retailRow['priced']['tax_mode'],
                    'tax_percent' => $retailRow['priced']['tax_percent'],
                    'taxable_amount' => $retailRow['priced']['taxable_amount'],
                    'tax_amount' => $retailRow['priced']['tax_amount'],
                    'line_amount' => $retailRow['priced']['line_amount'],
                    'hsn_code' => $retailRow['hsn_code'],
                    'position' => $retailRow['position'],
                ]);
            }

            if ($tray && $trayNet->isPositive()) {
                $this->trays->recordSale($tray, $sale, (string) $trayNet->toScale(3, RoundingMode::HalfUp), $userId);
            }

            $this->ledger->post(
                (int) $company->id,
                PartyType::Customer,
                (int) $customer->id,
                LedgerDirection::Debit,
                (string) $invoiceTotal->toScale(2, RoundingMode::HalfUp),
                'Invoice '.$sale->number,
                $sale,
                $userId,
            );

            foreach ($paymentRows as $row) {
                $receipt = $this->numbers->issue($this->numbers->for(DocumentType::Receipt, $branch));
                $payment = Payment::query()->create([
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'sale_id' => $sale->id,
                    'customer_id' => $customer->id,
                    'number' => $receipt->number,
                    'direction' => 'in',
                    'method' => $row['method'],
                    'amount' => $row['amount'],
                    'reference' => $row['reference'],
                    'narration' => ($previous->isPositive()
                        ? 'Received against '.$sale->number.' including old due'
                        : 'Received against '.$sale->number).($extra->isPositive() ? ' · '.$extra.' kept as advance' : ''),
                    'received_at' => $soldAt,
                    'user_id' => $userId,
                ]);
                $this->ledger->post(
                    (int) $company->id,
                    PartyType::Customer,
                    (int) $customer->id,
                    LedgerDirection::Credit,
                    $row['amount'],
                    'Receipt '.$payment->number,
                    $payment,
                    $userId,
                );
            }

            if ($openSales->isNotEmpty() && ! Schema::hasTable('sale_settlements')) {
                throw ValidationException::withMessages([
                    'include_previous' => 'This server needs the latest database update (php artisan migrate). Uncheck old due for now, or run migrate on live.',
                ]);
            }

            foreach ($openSales as $open) {
                SaleSettlement::query()->create([
                    'company_id' => $company->id,
                    'sale_id' => $sale->id,
                    'settled_sale_id' => $open->id,
                    'previous_paid_amount' => (string) $open->paid_amount,
                ]);
                $open->paid_amount = (string) BigDecimal::of((string) $open->total)
                    ->plus((string) $open->previous_due)
                    ->toScale(2, RoundingMode::HalfUp);
                $open->save();
            }

            if ($order) {
                $order->status = 'delivered';
                $order->delivered_at = now();
                $order->sale_id = $sale->id;
                $order->save();
            }

            return $sale->load(['lines.stones', 'retailLines', 'payments', 'customer', 'branch']);
        });
    }

    /**
     * Save a corrected bill on the same invoice number.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function revise(Sale $sale, array $attributes, ?int $userId = null): Sale
    {
        $sale->loadMissing('lines.item');
        $rates = [];

        foreach ($sale->lines as $line) {
            if (! $line->item) {
                continue;
            }

            $rates[$line->item->metal_type_id.':'.$line->item->purity_id] = [
                'id' => $line->metal_rate_id,
                'rate' => (string) $line->rate_per_gram,
            ];
        }

        $attributes['locked_rates'] = $rates;

        return $this->post(Company::query()->whereKey($sale->company_id)->firstOrFail(), $attributes, $userId, $sale);
    }

    public function editBlock(Sale $sale): ?string
    {
        if ($sale->isCancelled()) {
            return 'This invoice was cancelled and cannot be changed.';
        }

        if ($sale->advanceOrder()->exists()) {
            return 'This invoice delivered an order, so it stays as it was saved.';
        }

        $lineIds = $sale->relationLoaded('lines') ? $sale->lines->modelKeys() : $sale->lines()->pluck('id');

        if (SaleReturnLine::query()->whereIn('sale_line_id', $lineIds)->exists()) {
            return 'A piece on this invoice has been returned, so it stays as it was saved.';
        }

        return null;
    }

    public function cancel(Sale $sale, string $reason, ?int $userId = null): Sale
    {
        return DB::transaction(function () use ($sale, $reason, $userId) {
            $sale = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();
            $this->context->ensureId((int) $sale->company_id);

            if (! Schema::hasColumn('sales', 'cancelled_at')) {
                throw ValidationException::withMessages([
                    'reason' => 'This server needs the latest database update (php artisan migrate on live).',
                ]);
            }

            if ($sale->isCancelled()) {
                throw ValidationException::withMessages([
                    'reason' => 'This invoice is already cancelled.',
                ]);
            }

            $lineIds = $sale->lines()->pluck('id');

            if (SaleReturnLine::query()->whereIn('sale_line_id', $lineIds)->exists()) {
                throw ValidationException::withMessages([
                    'reason' => 'A piece on this invoice was returned. Cancel the whole bill only when nothing was returned.',
                ]);
            }

            if ((int) $sale->previous_bills > 0 && ! SaleSettlement::query()->where('sale_id', $sale->id)->exists()) {
                throw ValidationException::withMessages([
                    'reason' => 'This invoice settled older dues and cannot be cancelled here. Ask the owner to fix it manually.',
                ]);
            }

            foreach (SaleSettlement::query()->where('sale_id', $sale->id)->get() as $settlement) {
                $settled = Sale::query()->whereKey($settlement->settled_sale_id)->lockForUpdate()->first();

                if ($settled && $settled->isPosted()) {
                    $settled->paid_amount = (string) $settlement->previous_paid_amount;
                    $settled->save();
                }
            }

            $this->reverseSaleEffects($sale, 'Invoice cancelled', keepDocumentRows: true);

            $order = AdvanceOrder::query()->where('sale_id', $sale->id)->lockForUpdate()->first();

            if ($order) {
                $order->sale_id = null;
                $order->delivered_at = null;
                $order->status = $order->ready_at !== null ? 'ready' : 'booked';
                $order->save();
            }

            $sale->status = 'cancelled';
            $sale->cancel_reason = trim($reason);
            $sale->cancelled_at = now();
            $sale->cancelled_by_user_id = $userId;
            $sale->save();

            return $sale->fresh(['customer', 'cancelledBy', 'lines', 'payments']);
        });
    }

    /**
     * Put the pieces and the gram stock back, and remove the ledger rows, so the same invoice can be written again.
     */
    private function release(Sale $sale): void
    {
        $block = $this->editBlock($sale);

        if ($block) {
            throw ValidationException::withMessages([
                'sale' => $block,
            ]);
        }

        $this->reverseSaleEffects($sale, 'Invoice edited', keepDocumentRows: false);
    }

    /**
     * Undo stock, inventory, and ledger effects of a posted invoice.
     */
    private function reverseSaleEffects(Sale $sale, string $inventoryNote, bool $keepDocumentRows): void
    {
        $sale->load(['lines.item.movements', 'lines.stones', 'retailLines', 'payments']);
        $this->trays->reverseSale($sale);
        $madeForBill = [];

        foreach ($sale->lines as $line) {
            MetalStockMovement::query()
                ->where('reference_type', $line->getMorphClass())
                ->where('reference_id', $line->id)
                ->delete();

            $item = $line->item;

            if (! $item || $item->own_gold) {
                continue;
            }

            if (! $keepDocumentRows && $this->madeForBill($item, $sale)) {
                $madeForBill[] = $item;

                continue;
            }

            $this->inventory->apply($item, InventoryMovement::SaleReturn, $sale, null, null, $inventoryNote, null);
        }

        LedgerEntry::query()
            ->where('reference_type', $sale->getMorphClass())
            ->where('reference_id', $sale->id)
            ->delete();

        foreach ($sale->payments as $payment) {
            LedgerEntry::query()
                ->where('reference_type', $payment->getMorphClass())
                ->where('reference_id', $payment->id)
                ->delete();

            if (! $keepDocumentRows) {
                $payment->delete();
            }
        }

        if ($keepDocumentRows) {
            return;
        }

        foreach ($sale->lines as $line) {
            $line->stones()->delete();
            $line->delete();
        }

        $sale->retailLines()->delete();

        foreach ($madeForBill as $item) {
            $item->delete();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{lines: list<array<string, mixed>>, totals: array{line: string, taxable: string, tax: string}}
     */
    private function buildRetailLines(Company $company, array $rows): array
    {
        $fallback = (string) ($this->settings->get('pricing.gst_percent', $company) ?? '0');
        $lines = [];
        $lineTotal = BigDecimal::zero();
        $taxableTotal = BigDecimal::zero();
        $taxTotal = BigDecimal::zero();
        $position = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $product = null;

            if (trim((string) ($row['product_uuid'] ?? '')) !== '') {
                $product = RetailProduct::query()
                    ->where('uuid', (string) $row['product_uuid'])
                    ->where('company_id', $company->id)
                    ->where('is_active', true)
                    ->first();
            }

            $quantity = max(1, (int) ($row['quantity'] ?? 1));
            $priced = $this->retailPricer->price([
                'name' => $name,
                'quantity' => $quantity,
                'unit_price' => (string) ($row['unit_price'] ?? '0'),
                'tax_mode' => (string) ($row['tax_mode'] ?? 'none'),
                'tax_percent' => $row['tax_percent'] ?? null,
                'tax_amount' => $row['tax_amount'] ?? null,
            ], $fallback);

            $lines[] = [
                'product' => $product,
                'name' => $name,
                'quantity' => $quantity,
                'unit_price' => number_format((float) ($row['unit_price'] ?? 0), 2, '.', ''),
                'hsn_code' => trim((string) ($row['hsn_code'] ?? $product?->hsn_code ?? '')) ?: null,
                'priced' => $priced,
                'position' => $position,
            ];
            $position++;
            $lineTotal = $lineTotal->plus($priced['line_amount']);
            $taxableTotal = $taxableTotal->plus($priced['taxable_amount']);
            $taxTotal = $taxTotal->plus($priced['tax_amount']);
        }

        return [
            'lines' => $lines,
            'totals' => [
                'line' => (string) $lineTotal->toScale(2, RoundingMode::HalfUp),
                'taxable' => (string) $taxableTotal->toScale(2, RoundingMode::HalfUp),
                'tax' => (string) $taxTotal->toScale(2, RoundingMode::HalfUp),
            ],
        ];
    }

    public function madeForBill(Item $item, Sale $sale): bool
    {
        if ($item->purchaseLines()->exists() || $item->created_at === null || $sale->created_at === null) {
            return false;
        }

        if ($item->created_at->lt($sale->created_at->copy()->subMinute())) {
            return false;
        }

        $saleType = $sale->getMorphClass();

        foreach ($item->movements as $movement) {
            $opening = $movement->type === InventoryMovement::Opening;
            $thisSale = $movement->type === InventoryMovement::Sale
                && $movement->reference_type === $saleType
                && (int) $movement->reference_id === (int) $sale->id;

            if (! $opening && ! $thisSale) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function rateForPiece(Item $piece, ?AdvanceOrder $order, array $attributes): ?MetalRate
    {
        $locked = $attributes['locked_rates'][$piece->metal_type_id.':'.$piece->purity_id] ?? null;

        if (is_array($locked)) {
            $rate = new MetalRate;
            $rate->id = $locked['id'];
            $rate->rate_per_gram = $locked['rate'];
            $rate->exists = true;

            return $rate;
        }

        return $this->orderRate($order, $piece)
            ?? $this->rates->current((int) $piece->metal_type_id, (int) $piece->purity_id, (int) $piece->branch_id);
    }

    private function pricedRate(?MetalRate $rate, string $amount): MetalRate
    {
        $custom = $rate ? clone $rate : new MetalRate;
        $custom->rate_per_gram = (string) BigDecimal::of($amount)->toScale(2, RoundingMode::HalfUp);

        if (! $rate) {
            $custom->exists = false;
            $custom->id = null;
        }

        return $custom;
    }

    private function orderRate(?AdvanceOrder $order, Item $piece): ?MetalRate
    {
        if (! $order
            || (int) $order->metal_type_id !== (int) $piece->metal_type_id
            || (int) $order->purity_id !== (int) $piece->purity_id) {
            return null;
        }

        return $order->metalRate;
    }

    /**
     * @return array{metal_amount: string, wastage_amount: string, making_amount: string, stone_amount: string, line_amount: string}
     */
    private function pricePiece(Item $piece, string $ratePerGram): array
    {
        $piece->loadMissing(['makingMethod', 'wastageMethod']);

        return $this->pricer->line(
            (string) $piece->net_weight,
            $ratePerGram,
            $piece->wastageMethod?->code ?? 'fixed',
            (string) ($piece->wastage_value ?? '0'),
            $piece->makingMethod?->code ?? 'fixed',
            (string) ($piece->making_value ?? '0'),
            (string) ($piece->stone_value ?? '0'),
        );
    }

    private function nextPieceCode(): string
    {
        return $this->items->nextCode(true);
    }

    private function soldAt(array $attributes, ?Sale $existing): Carbon
    {
        $timezone = config('app.timezone');
        $raw = trim((string) ($attributes['sold_at'] ?? ''));

        if ($raw === '') {
            return $existing?->sold_at?->timezone($timezone) ?? now($timezone);
        }

        return Carbon::parse($raw, $timezone);
    }
}
