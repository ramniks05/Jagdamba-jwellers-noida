<?php

namespace Tests\Feature\Commerce;

use App\Models\Customer;
use App\Models\Item;
use App\Models\MetalType;
use App\Models\Payment;
use App\Models\Purity;
use App\Models\Sale;
use App\Models\StockLocation;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalePaymentDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_backdated_bill_uses_noon_on_invoice_and_separate_payment_received_at(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $tz = 'Asia/Kolkata';
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '22K')->firstOrFail();
        $buyer = $this->customer($owner);
        $item = $this->stockPiece($owner, $gold, $purity, 'OLD01');

        $billDay = now($tz)->subDays(2)->setTime(15, 45);
        $paidDay = now($tz)->subDay()->setTime(9, 30);

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $buyer->uuid,
            'discount' => '0',
            'sold_at' => $billDay->format('Y-m-d\TH:i'),
            'item_ids' => [$item->uuid],
            'payments' => [[
                'method' => 'cash',
                'amount' => '61800',
                'received_at' => $paidDay->format('Y-m-d\TH:i'),
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->seeShop($owner);
        $sale = Sale::query()->latest('id')->firstOrFail();
        $payment = Payment::query()->where('sale_id', $sale->id)->firstOrFail();

        $invoiceWhen = $sale->sold_at?->timezone($tz);
        $this->assertSame($billDay->format('Y-m-d'), $invoiceWhen?->format('Y-m-d'));
        $this->assertSame('12:00', $invoiceWhen?->format('H:i'));
        $this->assertSame(
            $paidDay->format('Y-m-d H:i:s'),
            $payment->received_at?->timezone($tz)->format('Y-m-d H:i:s'),
        );
    }

    public function test_old_bill_without_payment_date_defaults_to_invoice_noon(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $tz = 'Asia/Kolkata';
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '22K')->firstOrFail();
        $buyer = $this->customer($owner);
        $item = $this->stockPiece($owner, $gold, $purity, 'OLD02');
        $billDay = now($tz)->subDays(3)->setTime(18, 0);

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $buyer->uuid,
            'discount' => '0',
            'sold_at' => $billDay->format('Y-m-d\TH:i'),
            'item_ids' => [$item->uuid],
            'payments' => [['method' => 'cash', 'amount' => '61800']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->seeShop($owner);
        $payment = Payment::query()->where('sale_id', Sale::query()->latest('id')->value('id'))->firstOrFail();
        $expected = $billDay->copy()->setTime(12, 0)->format('Y-m-d H:i:s');

        $this->assertSame($expected, $payment->received_at?->timezone($tz)->format('Y-m-d H:i:s'));
    }

    public function test_today_bill_keeps_payment_on_invoice_time(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $tz = 'Asia/Kolkata';
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '22K')->firstOrFail();
        $buyer = $this->customer($owner);
        $item = $this->stockPiece($owner, $gold, $purity, 'TOD01');
        $soldAt = now($tz)->setTime(9, 30);

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $buyer->uuid,
            'discount' => '0',
            'sold_at' => $soldAt->format('Y-m-d\TH:i'),
            'item_ids' => [$item->uuid],
            'payments' => [['method' => 'cash', 'amount' => '61800']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->seeShop($owner);
        $sale = Sale::query()->latest('id')->firstOrFail();
        $payment = Payment::query()->where('sale_id', $sale->id)->firstOrFail();

        $this->assertSame(
            $soldAt->format('Y-m-d H:i:s'),
            $payment->received_at?->timezone($tz)->format('Y-m-d H:i:s'),
        );
    }

    private function customer(User $owner): Customer
    {
        $this->actingAs($owner)->post(route('customers.store'), [
            'name' => 'Payment Date Customer',
            'customer_type' => 'retail',
            'kyc_status' => 'pending',
            'is_active' => '1',
        ])->assertRedirect();
        $this->seeShop($owner);

        return Customer::query()->where('name', 'Payment Date Customer')->firstOrFail();
    }

    private function stockPiece(User $owner, MetalType $gold, Purity $purity, string $code): Item
    {
        $location = StockLocation::query()->where('code', 'MAIN')->firstOrFail();
        $making = \App\Models\ChargeMethod::query()->where('applies_to', \App\Enums\ChargeAppliesTo::Making)->where('code', 'fixed')->firstOrFail();
        $wastage = \App\Models\ChargeMethod::query()->where('applies_to', \App\Enums\ChargeAppliesTo::Wastage)->where('code', 'fixed')->firstOrFail();

        $this->actingAs($owner)->post(route('rates.store'), [
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'rate_per_gram' => '6000',
            'source' => 'manual',
        ])->assertRedirect();

        $this->actingAs($owner)->post(route('items.store'), [
            'name' => 'Ring',
            'item_code' => $code,
            'sku' => $code,
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'location_uuid' => $location->uuid,
            'gross_weight' => '10',
            'stone_weight' => '0',
            'other_weight' => '0',
            'making_method_uuid' => $making->uuid,
            'making_value' => '0',
            'wastage_method_uuid' => $wastage->uuid,
            'wastage_value' => '0',
            'stone_value' => '0',
            'cost_price' => '0',
            'selling_price' => '0',
            'mrp' => '0',
        ])->assertRedirect();

        $this->seeShop($owner);

        return Item::query()->where('item_code', $code)->firstOrFail();
    }

    private function seeShop(User $user): void
    {
        app(CompanyContext::class)->set($user->company);
    }
}
