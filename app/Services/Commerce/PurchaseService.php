<?php

namespace App\Services\Commerce;

use App\Enums\DocumentType;
use App\Enums\InventoryMovement;
use App\Enums\LedgerDirection;
use App\Enums\PartyType;
use App\Enums\PaymentMethod;
use App\Models\ChargeMethod;
use App\Models\Company;
use App\Models\MetalType;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnLine;
use App\Models\Purity;
use App\Models\StockLocation;
use App\Models\Supplier;
use App\Services\Foundation\DocumentNumberService;
use App\Services\Foundation\SettingService;
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseService
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
     * @param  array<string, mixed>  $attributes
     */
    public function receive(Company $company, array $attributes, ?int $userId = null): Purchase
    {
        return DB::transaction(function () use ($company, $attributes, $userId) {
            $this->context->ensureId((int) $company->id);
            $supplier = Supplier::query()->where('uuid', $attributes['supplier_uuid'])->where('is_active', true)->first();

            if (! $supplier) {
                throw ValidationException::withMessages([
                    'supplier_uuid' => 'Choose a supplier.',
                ]);
            }

            return $this->post($company, $supplier, $attributes, $userId);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function post(Company $company, Supplier $supplier, array $attributes, ?int $userId): Purchase
    {
        $metal = MetalType::query()->where('uuid', $attributes['metal_uuid'])->firstOrFail();
        $purity = Purity::query()->where('uuid', $attributes['purity_uuid'])->firstOrFail();
        $location = StockLocation::query()->where('uuid', $attributes['location_uuid'])->firstOrFail();
        $making = ChargeMethod::query()->where('uuid', $attributes['making_method_uuid'] ?? '')->first();
        $wastage = ChargeMethod::query()->where('uuid', $attributes['wastage_method_uuid'] ?? '')->first();
        $net = BigDecimal::of((string) $attributes['gross_weight'])
            ->minus((string) ($attributes['stone_weight'] ?? '0'))
            ->minus((string) ($attributes['other_weight'] ?? '0'));

        if ($net->isLessThanOrEqualTo(0)) {
            throw ValidationException::withMessages([
                'gross_weight' => 'Net metal weight must be more than zero.',
            ]);
        }

        $netWeight = (string) $net->toScale(3, RoundingMode::HalfUp);
        $rate = $this->rates->current((int) $metal->id, (int) $purity->id, (int) $location->branch_id);

        if (($attributes['pricing'] ?? 'rate') === 'amount') {
            $line = BigDecimal::of((string) $attributes['purchase_amount'])->toScale(2, RoundingMode::HalfUp);
            $priced = ['line_amount' => (string) $line];
            $ratePerGram = (string) $line->dividedBy($netWeight, 2, RoundingMode::HalfUp);
        } else {
            if (! $rate) {
                throw ValidationException::withMessages([
                    'metal_uuid' => 'Enter a '.$metal->name.' '.$purity->name.' rate before this purchase, or type the purchase value.',
                ]);
            }

            $priced = $this->pricer->line(
                $netWeight,
                (string) $rate->rate_per_gram,
                $wastage?->code ?? 'fixed',
                (string) ($attributes['wastage_value'] ?? '0'),
                $making?->code ?? 'fixed',
                (string) ($attributes['making_value'] ?? '0'),
                (string) ($attributes['stone_value'] ?? '0'),
            );
            $ratePerGram = (string) $rate->rate_per_gram;
        }
        $exclusive = $this->settings->get('invoice.tax_display', $company) !== 'inclusive';
        $bill = $this->pricer->bill(
            $priced['line_amount'],
            (string) ($attributes['discount'] ?? '0'),
            (string) ($this->settings->get('pricing.gst_percent', $company) ?? '0'),
            $exclusive,
            (bool) $this->settings->get('pricing.round_rupee', $company),
        );
        $paid = $this->paid($attributes['payments'] ?? []);

        if ($paid->isGreaterThan($bill->total)) {
            throw ValidationException::withMessages([
                'payments' => 'Payments are more than the purchase total.',
            ]);
        }

        $number = $this->numbers->issue($this->numbers->for(DocumentType::Purchase, $location->branch));
        $purchase = Purchase::query()->create([
            'company_id' => $company->id,
            'branch_id' => $location->branch_id,
            'supplier_id' => $supplier->id,
            'number' => $number->number,
            'purchased_at' => now(),
            'lines_amount' => $bill->linesAmount,
            'discount_amount' => $bill->discountAmount,
            'taxable_amount' => $bill->taxableAmount,
            'tax_percent' => (string) ($this->settings->get('pricing.gst_percent', $company) ?? '0'),
            'tax_amount' => $bill->taxAmount,
            'round_off' => $bill->roundOff,
            'total' => $bill->total,
            'paid_amount' => (string) $paid->toScale(2, RoundingMode::HalfUp),
            'notes' => trim((string) ($attributes['notes'] ?? '')) ?: null,
            'user_id' => $userId,
        ]);
        $attributes['cost_price'] = $bill->total;
        $item = $this->items->create($company, $attributes, $userId, InventoryMovement::Purchase, $purchase);
        PurchaseLine::query()->create([
            'company_id' => $company->id,
            'purchase_id' => $purchase->id,
            'item_id' => $item->id,
            'name' => $item->name,
            'item_code' => $item->item_code,
            'net_weight' => $item->net_weight,
            'rate_per_gram' => $ratePerGram,
            'line_amount' => $priced['line_amount'],
        ]);
        $this->ledger->post((int) $company->id, PartyType::Supplier, (int) $supplier->id, LedgerDirection::Credit, $bill->total, 'Purchase '.$purchase->number, $purchase, $userId);

        foreach ($attributes['payments'] ?? [] as $row) {
            if (trim((string) ($row['amount'] ?? '')) === '') {
                continue;
            }

            $amount = (string) BigDecimal::of((string) $row['amount'])->toScale(2, RoundingMode::HalfUp);
            $voucher = $this->numbers->issue($this->numbers->for(DocumentType::Payment, $location->branch));
            $payment = Payment::query()->create([
                'company_id' => $company->id,
                'branch_id' => $location->branch_id,
                'purchase_id' => $purchase->id,
                'supplier_id' => $supplier->id,
                'number' => $voucher->number,
                'direction' => 'out',
                'method' => PaymentMethod::from($row['method']),
                'amount' => $amount,
                'reference' => trim((string) ($row['reference'] ?? '')) ?: null,
                'narration' => 'Paid against '.$purchase->number,
                'received_at' => now(),
                'user_id' => $userId,
            ]);
            $this->ledger->post((int) $company->id, PartyType::Supplier, (int) $supplier->id, LedgerDirection::Debit, $amount, 'Payment '.$payment->number, $payment, $userId);
        }

        return $purchase->load(['lines.item', 'supplier']);
    }

    public function sendBack(Purchase $purchase, ?int $userId = null): PurchaseReturn
    {
        return DB::transaction(function () use ($purchase, $userId) {
            $this->context->ensureId((int) $purchase->company_id);
            $purchase->load('lines.item');
            $returnedIds = PurchaseReturnLine::query()->whereIn('purchase_line_id', $purchase->lines->modelKeys())->pluck('purchase_line_id');
            $lines = $purchase->lines->reject(fn (PurchaseLine $line) => $returnedIds->contains($line->id));

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'item' => 'Every piece on this purchase has already gone back.',
                ]);
            }

            $amount = (string) BigDecimal::of((string) $purchase->total)->toScale(2, RoundingMode::HalfUp);
            $number = $this->numbers->issue($this->numbers->for(DocumentType::PurchaseReturn, $purchase->branch));
            $document = PurchaseReturn::query()->create([
                'company_id' => $purchase->company_id,
                'purchase_id' => $purchase->id,
                'supplier_id' => $purchase->supplier_id,
                'number' => $number->number,
                'amount' => $amount,
                'returned_at' => now(),
                'user_id' => $userId,
            ]);

            foreach ($lines as $line) {
                $this->inventory->apply($line->item, InventoryMovement::PurchaseReturn, $document, null, $amount, 'Returned to supplier', $userId);
                PurchaseReturnLine::query()->create([
                    'company_id' => $purchase->company_id,
                    'purchase_return_id' => $document->id,
                    'purchase_line_id' => $line->id,
                    'item_id' => $line->item_id,
                    'amount' => $amount,
                ]);
            }

            $this->ledger->post(
                (int) $purchase->company_id,
                PartyType::Supplier,
                (int) $purchase->supplier_id,
                LedgerDirection::Debit,
                $amount,
                'Purchase return '.$document->number,
                $document,
                $userId,
            );

            return $document;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function paid(array $rows): BigDecimal
    {
        $paid = BigDecimal::zero();

        foreach ($rows as $row) {
            if (trim((string) ($row['amount'] ?? '')) === '') {
                continue;
            }

            $paid = $paid->plus((string) $row['amount']);
        }

        return $paid->toScale(2, RoundingMode::HalfUp);
    }
}
