<?php

namespace App\Services\Commerce;

use App\Enums\DocumentType;
use App\Enums\InventoryMovement;
use App\Enums\ItemStatus;
use App\Enums\LedgerDirection;
use App\Enums\PartyType;
use App\Enums\PaymentMethod;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SaleLineStone;
use App\Services\Foundation\DocumentNumberService;
use App\Services\Foundation\SettingService;
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
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
    ) {}

    /**
     * @return array{ready: bool, message?: string, rate?: string, metal?: string, wastage?: string, making?: string, stone?: string, line?: string}
     */
    public function quote(Item $item): array
    {
        $item->loadMissing(['metalType', 'purity', 'makingMethod', 'wastageMethod']);
        $rate = $this->rates->current((int) $item->metal_type_id, (int) $item->purity_id, (int) $item->branch_id);

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
    public function post(Company $company, array $attributes, ?int $userId = null): Sale
    {
        return DB::transaction(function () use ($company, $attributes, $userId) {
            $this->context->ensureId((int) $company->id);
            $customer = Customer::query()->where('uuid', $attributes['customer_uuid'])->where('is_active', true)->first();

            if (! $customer) {
                throw ValidationException::withMessages([
                    'customer_uuid' => 'Choose a customer.',
                ]);
            }

            $uuids = array_values(array_unique($attributes['item_ids'] ?? []));

            $freshPieces = array_values(array_filter(
                $attributes['new_pieces'] ?? [],
                fn ($row) => is_array($row) && trim((string) ($row['name'] ?? '')) !== '',
            ));

            if (! empty($attributes['new_piece']['name'])) {
                $freshPieces[] = $attributes['new_piece'];
            }

            foreach ($freshPieces as $row) {
                $code = $this->nextPieceCode();
                $created = $this->items->create($company, $row + [
                    'item_code' => $code,
                    'sku' => $code,
                ], $userId);
                $uuids[] = $created->uuid;
            }

            if ($uuids === []) {
                throw ValidationException::withMessages([
                    'item_ids' => 'Choose a piece in stock, or add a new piece on this bill.',
                ]);
            }

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

            $lines = [];
            $linesTotal = BigDecimal::zero();

            foreach ($items as $item) {
                $piece = $locked[$item->id];

                if ($piece->status !== ItemStatus::Available) {
                    throw ValidationException::withMessages([
                        'item_ids' => $piece->item_code.' is not available.',
                    ]);
                }

                $rate = $this->rates->current((int) $piece->metal_type_id, (int) $piece->purity_id, (int) $piece->branch_id);

                if (! $rate) {
                    throw ValidationException::withMessages([
                        'item_ids' => 'Enter a '.$item->metalType->name.' '.$item->purity->name.' rate before billing '.$piece->item_code.'.',
                    ]);
                }

                $priced = $this->pricePiece($piece, (string) $rate->rate_per_gram);

                $lines[] = ['item' => $piece, 'display' => $item, 'rate' => $rate, 'priced' => $priced];
                $linesTotal = $linesTotal->plus($priced['line_amount']);
            }

            $exclusive = $this->settings->get('invoice.tax_display', $company) !== 'inclusive';
            $bill = $this->pricer->bill(
                (string) $linesTotal->toScale(2, RoundingMode::HalfUp),
                (string) ($attributes['discount'] ?? '0'),
                (string) ($this->settings->get('pricing.gst_percent', $company) ?? '0'),
                $exclusive,
                (bool) $this->settings->get('pricing.round_rupee', $company),
            );

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

            $total = BigDecimal::of($bill->total);

            if ($paid->isGreaterThan($total)) {
                throw ValidationException::withMessages([
                    'payments' => 'Payments are more than the bill total.',
                ]);
            }

            if ($customer->is_system && ! $paid->isEqualTo($total)) {
                throw ValidationException::withMessages([
                    'payments' => 'A walk-in bill must be paid in full.',
                ]);
            }

            $branch = $items->first()->branch;
            $invoice = $this->numbers->issue($this->numbers->for(DocumentType::Invoice, $branch));
            $sale = Sale::query()->create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'number' => $invoice->number,
                'status' => 'posted',
                'sold_at' => now(),
                'lines_amount' => $bill->linesAmount,
                'discount_amount' => $bill->discountAmount,
                'taxable_amount' => $bill->taxableAmount,
                'tax_percent' => (string) ($this->settings->get('pricing.gst_percent', $company) ?? '0'),
                'tax_amount' => $bill->taxAmount,
                'prices_include_tax' => ! $exclusive,
                'round_off' => $bill->roundOff,
                'total' => $bill->total,
                'paid_amount' => (string) $paid->toScale(2, RoundingMode::HalfUp),
                'notes' => trim((string) ($attributes['notes'] ?? '')) ?: null,
                'user_id' => $userId,
            ]);

            foreach ($lines as $line) {
                $piece = $line['item'];
                $display = $line['display'];
                $saleLine = SaleLine::query()->create([
                    'company_id' => $company->id,
                    'sale_id' => $sale->id,
                    'item_id' => $piece->id,
                    'metal_rate_id' => $line['rate']->id,
                    'name' => $piece->name,
                    'item_code' => $piece->item_code,
                    'metal_name' => $display->metalType->name,
                    'purity_name' => $display->purity->name,
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
                foreach ($display->stones as $index => $stone) {
                    SaleLineStone::query()->create([
                        'company_id' => $company->id,
                        'sale_line_id' => $saleLine->id,
                        'name' => $stone->name,
                        'weight' => $stone->weight,
                        'value' => $stone->value,
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

            $this->ledger->post(
                (int) $company->id,
                PartyType::Customer,
                (int) $customer->id,
                LedgerDirection::Debit,
                $bill->total,
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
                    'narration' => 'Received against '.$sale->number,
                    'received_at' => now(),
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

            return $sale->load(['lines.stones', 'payments', 'customer', 'branch']);
        });
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
        $highest = Item::withTrashed()
            ->lockForUpdate()
            ->pluck('item_code')
            ->reduce(function (int $highest, string $code): int {
                if (preg_match('/^PC(\d+)$/', $code, $matches) !== 1) {
                    return $highest;
                }

                return max($highest, (int) $matches[1]);
            }, 0);

        return 'PC'.str_pad((string) ($highest + 1), 4, '0', STR_PAD_LEFT);
    }
}
