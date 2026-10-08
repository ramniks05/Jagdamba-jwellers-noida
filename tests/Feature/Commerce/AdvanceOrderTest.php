<?php

namespace Tests\Feature\Commerce;

use App\Enums\ChargeAppliesTo;
use App\Enums\PartyType;
use App\Models\AdvanceOrder;
use App\Models\ChargeMethod;
use App\Models\Customer;
use App\Models\Item;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\Sale;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\Commerce\LedgerService;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvanceOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_booking_rate_is_used_on_the_bill_and_the_advance_is_taken_off(): void
    {
        [$owner, $gold, $purity] = $this->counter();
        $customer = $this->customer($owner);
        $order = $this->book($owner, $customer, $gold, $purity, '20000');

        $this->assertStringStartsWith('ORD', $order->number);
        $this->assertSame('5000.00', (string) $order->rate_per_gram);
        $this->assertSame('20000.00', (string) $order->advance_paid);
        $this->assertSame('-20000.00', app(LedgerService::class)->balance(PartyType::Customer, (int) $customer->id));
        $this->actingAs($owner)->get(route('orders.show', $order))->assertOk()->assertSee('Order booking slip');

        $this->rate($owner, $gold, $purity, '6000');
        $this->actingAs($owner)->post(route('orders.ready', $order))->assertRedirect();
        $this->assertSame('ready', $order->refresh()->status);
        $item = $this->piece($owner, $gold, $purity, 'ORD01');

        $this->actingAs($owner)->get(route('sales.create', ['order' => $order->uuid]))->assertOk()->assertSee('Delivering order '.$order->number);

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $customer->uuid,
            'order_uuid' => $order->uuid,
            'item_ids' => [$item->uuid],
            'discount' => '0',
            'payments' => [['method' => 'cash', 'amount' => '31501']],
        ])->assertSessionHasErrors('payments');

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $customer->uuid,
            'order_uuid' => $order->uuid,
            'item_ids' => [$item->uuid],
            'discount' => '0',
            'payments' => [['method' => 'cash', 'amount' => '31500']],
        ])->assertRedirect();

        $this->seeShop($owner);
        $sale = Sale::query()->latest('id')->firstOrFail();
        $this->assertSame('51500.00', (string) $sale->total);
        $this->assertSame('20000.00', (string) $sale->advance_amount);
        $this->assertSame('51500.00', (string) $sale->paid_amount);
        $this->assertSame('5000.00', (string) $sale->lines()->firstOrFail()->rate_per_gram);
        $this->assertSame('0.00', app(LedgerService::class)->balance(PartyType::Customer, (int) $customer->id));
        $order->refresh();
        $this->assertSame('delivered', $order->status);
        $this->assertSame($sale->id, $order->sale_id);
        $this->actingAs($owner)->get(route('sales.show', $sale))->assertOk()->assertSee('Advance '.$order->number);
    }

    public function test_more_advance_can_be_taken_and_a_cancelled_order_refunds_part(): void
    {
        [$owner, $gold, $purity] = $this->counter();
        $customer = $this->customer($owner);
        $order = $this->book($owner, $customer, $gold, $purity, '10000');

        $this->actingAs($owner)->post(route('orders.advance', $order), [
            'amount' => '5000',
            'method' => 'upi',
            'reference' => 'UPI123',
        ])->assertRedirect();
        $this->seeShop($owner);
        $this->assertSame('15000.00', (string) $order->refresh()->advance_paid);

        $this->actingAs($owner)->post(route('orders.cancel', $order), [
            'refund' => '15001',
            'method' => 'cash',
        ])->assertSessionHasErrors('refund');

        $this->actingAs($owner)->post(route('orders.cancel', $order), [
            'refund' => '9000',
            'method' => 'cash',
        ])->assertRedirect();
        $this->seeShop($owner);
        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('9000.00', (string) $order->advance_refunded);
        $this->assertSame('-6000.00', app(LedgerService::class)->balance(PartyType::Customer, (int) $customer->id));

        $this->actingAs($owner)->post(route('orders.advance', $order), [
            'amount' => '100',
            'method' => 'cash',
        ])->assertSessionHasErrors('amount');
    }

    public function test_an_order_needs_a_named_customer(): void
    {
        [$owner, $gold, $purity] = $this->counter();
        $walkIn = Customer::query()->where('code', 'WALKIN')->firstOrFail();

        $this->actingAs($owner)->post(route('orders.store'), $this->booking($walkIn, $gold, $purity, '1000'))
            ->assertSessionHasErrors('customer_uuid');
        $this->seeShop($owner);
        $this->assertSame(0, AdvanceOrder::query()->count());
    }

    private function book(User $owner, Customer $customer, MetalType $gold, Purity $purity, string $advance): AdvanceOrder
    {
        $this->actingAs($owner)->post(route('orders.store'), $this->booking($customer, $gold, $purity, $advance))->assertRedirect();
        $this->seeShop($owner);

        return AdvanceOrder::query()->latest('id')->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function booking(Customer $customer, MetalType $gold, Purity $purity, string $advance): array
    {
        return [
            'customer_uuid' => $customer->uuid,
            'description' => 'Bridal ring',
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'expected_weight' => '10',
            'estimated_making' => '0',
            'due_on' => now()->addDays(10)->toDateString(),
            'advance' => $advance,
            'method' => 'cash',
        ];
    }

    /**
     * @return array{0: User, 1: MetalType, 2: Purity}
     */
    private function counter(): array
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '22K')->firstOrFail();
        $this->rate($owner, $gold, $purity, '5000');

        return [$owner, $gold, $purity];
    }

    private function rate(User $owner, MetalType $gold, Purity $purity, string $rate): void
    {
        $this->actingAs($owner)->post(route('rates.store'), [
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'rate_per_gram' => $rate,
            'source' => 'manual',
        ])->assertRedirect();
        $this->seeShop($owner);
    }

    private function customer(User $owner): Customer
    {
        $this->actingAs($owner)->post(route('customers.store'), [
            'name' => 'Meera Shah',
            'customer_type' => 'retail',
            'kyc_status' => 'pending',
            'is_active' => '1',
        ])->assertRedirect();
        $this->seeShop($owner);

        return Customer::query()->where('name', 'Meera Shah')->firstOrFail();
    }

    private function piece(User $owner, MetalType $gold, Purity $purity, string $code): Item
    {
        $location = StockLocation::query()->where('code', 'MAIN')->firstOrFail();
        $making = ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Making)->where('code', 'fixed')->firstOrFail();
        $wastage = ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Wastage)->where('code', 'fixed')->firstOrFail();

        $this->actingAs($owner)->post(route('items.store'), [
            'name' => 'Bridal ring',
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
