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

        $this->actingAs($owner)->get(route('sales.create'))
            ->assertOk()
            ->assertSee('Open orders for this customer', false)
            ->assertSee($order->number);

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

    public function test_new_bill_lists_open_orders_for_the_customer(): void
    {
        [$owner, $gold, $purity] = $this->counter();
        $customer = $this->customer($owner);
        $order = $this->book($owner, $customer, $gold, $purity, '5000');

        $response = $this->actingAs($owner)->get(route('sales.create'));
        $response->assertOk();
        $response->assertSee('id="customer-open-orders"', false);
        $response->assertSee('Make the bill', false);
        $response->assertSee($order->number, false);
        $response->assertSee($order->uuid, false);
        $response->assertSee('openOrders', false);
        $response->assertSee('Bridal ring', false);
    }

    public function test_new_order_form_supports_multiple_metal_lines(): void
    {
        [$owner] = $this->counter();

        $this->actingAs($owner)->get(route('orders.create'))
            ->assertOk()
            ->assertSee('Add another purity', false)
            ->assertSee('order-metal-lines.js', false)
            ->assertSee('lines[0][metal_uuid]', false);
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

    public function test_an_order_can_be_booked_on_an_old_date(): void
    {
        [$owner, $gold, $purity] = $this->counter();
        $customer = $this->customer($owner);
        $tz = config('app.timezone');

        $this->actingAs($owner)->post(route('orders.store'), array_merge($this->booking($customer, $gold, $purity, '8000'), [
            'booked_at' => '2026-10-01 15:30:00',
            'due_on' => '2026-10-20',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->seeShop($owner);
        $order = AdvanceOrder::query()->latest('id')->firstOrFail();
        $this->assertSame('2026-10-01', $order->booked_at->timezone($tz)->format('Y-m-d'));
        $this->assertSame('12:00', $order->booked_at->timezone($tz)->format('H:i'));
        $this->assertSame('2026-10-20', $order->due_on?->toDateString());
        $payment = $order->payments()->latest('id')->firstOrFail();
        $this->assertSame('2026-10-01 12:00:00', $payment->received_at?->timezone($tz)->format('Y-m-d H:i:s'));
    }

    public function test_an_advance_invoice_can_change_its_bill_date(): void
    {
        [$owner, $gold, $purity] = $this->counter();
        $customer = $this->customer($owner);
        $order = $this->book($owner, $customer, $gold, $purity, '20000');
        $this->actingAs($owner)->post(route('orders.ready', $order))->assertRedirect();
        $item = $this->piece($owner, $gold, $purity, 'ORD02');

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $customer->uuid,
            'order_uuid' => $order->uuid,
            'item_ids' => [$item->uuid],
            'discount' => '0',
            'payments' => [['method' => 'cash', 'amount' => '31500']],
            'sold_at' => '2026-10-06 10:00:00',
        ])->assertRedirect();

        $this->seeShop($owner);
        $sale = Sale::query()->latest('id')->firstOrFail();
        $number = $sale->number;
        $this->assertSame('20000.00', (string) $sale->advance_amount);

        $this->actingAs($owner)->get(route('sales.show', $sale))
            ->assertOk()
            ->assertSee('Edit invoice');

        $this->actingAs($owner)->get(route('sales.edit', $sale))
            ->assertOk()
            ->assertSee('Order '.$order->number)
            ->assertSee('Update '.$number);

        $this->actingAs($owner)->put(route('sales.update', $sale), [
            'customer_uuid' => $customer->uuid,
            'order_uuid' => $order->uuid,
            'discount' => '0',
            'new_pieces' => [[
                'name' => 'Bridal ring',
                'metal_uuid' => $gold->uuid,
                'purity_uuid' => $purity->uuid,
                'location_uuid' => StockLocation::query()->where('code', 'MAIN')->firstOrFail()->uuid,
                'gross_weight' => '10',
                'other_weight' => '0',
                'making_value' => '0',
                'wastage_value' => '0',
                'rate_per_gram' => '5000',
            ]],
            'payments' => [['method' => 'cash', 'amount' => '31500']],
            'sold_at' => '2026-10-01 15:30:00',
        ])->assertRedirect(route('sales.show', $sale))->assertSessionHasNoErrors();

        $this->seeShop($owner);
        $sale->refresh();
        $order->refresh();
        $this->assertSame($number, $sale->number);
        $this->assertSame('20000.00', (string) $sale->advance_amount);
        $this->assertSame('51500.00', (string) $sale->total);
        $this->assertSame('0.00', $sale->balanceDue());
        $this->assertSame('2026-10-01', $sale->sold_at->timezone(config('app.timezone'))->format('Y-m-d'));
        $this->assertSame('12:00', $sale->sold_at->timezone(config('app.timezone'))->format('H:i'));
        $this->assertSame('delivered', $order->status);
        $this->assertSame($sale->id, $order->sale_id);
        $this->assertSame('0.00', app(LedgerService::class)->balance(PartyType::Customer, (int) $customer->id));
    }

    public function test_an_open_order_can_be_edited(): void
    {
        [$owner, $gold, $purity] = $this->counter();
        $customer = $this->customer($owner);
        $order = $this->book($owner, $customer, $gold, $purity, '10000');

        $this->actingAs($owner)->get(route('orders.edit', $order))->assertOk()->assertSee('Edit order');

        $this->actingAs($owner)->put(route('orders.update', $order), [
            'description' => 'Updated necklace',
            'lines' => [[
                'metal_uuid' => $gold->uuid,
                'purity_uuid' => $purity->uuid,
                'expected_weight' => '12.500',
                'rate_per_gram' => '5100',
                'estimated_making' => '0',
            ]],
            'estimated_making' => '1500',
            'design_notes' => 'Size 16',
            'booked_at' => '2026-09-15 18:00:00',
            'due_on' => now()->addDays(20)->toDateString(),
            'notes' => 'Rush',
        ])->assertRedirect(route('orders.show', $order));

        $order->refresh();
        $this->assertSame('Updated necklace', $order->description);
        $this->assertSame('12.500', (string) $order->expected_weight);
        $this->assertSame('5100.00', (string) $order->rate_per_gram);
        $this->assertSame('1500.00', (string) $order->estimated_making);
        $this->assertSame('Size 16', $order->design_notes);
        $this->assertSame('Rush', $order->notes);
        $this->assertSame('2026-09-15', $order->booked_at->timezone(config('app.timezone'))->format('Y-m-d'));
        $this->assertSame('12:00', $order->booked_at->timezone(config('app.timezone'))->format('H:i'));
        $this->assertSame('10000.00', (string) $order->advance_paid);

        $this->actingAs($owner)->get(route('orders.show', $order))->assertOk()->assertSee('Updated necklace');

        $this->actingAs($owner)->post(route('orders.cancel', $order), [
            'refund' => '0',
            'method' => 'cash',
        ])->assertRedirect();
        $this->seeShop($owner);

        $this->actingAs($owner)->get(route('orders.edit', $order))->assertRedirect(route('orders.show', $order));
        $this->actingAs($owner)->put(route('orders.update', $order), [
            'description' => 'Too late',
            'lines' => [[
                'metal_uuid' => $gold->uuid,
                'purity_uuid' => $purity->uuid,
                'expected_weight' => '10',
                'rate_per_gram' => '5000',
                'estimated_making' => '0',
            ]],
            'estimated_making' => '0',
        ])->assertSessionHasErrors('description');
    }

    public function test_one_order_can_have_two_purities_and_bill_each_at_its_locked_rate(): void
    {
        [$owner, $gold] = $this->counter();
        $purity18 = Purity::query()->where('code', '18K')->firstOrFail();
        $purity20 = Purity::query()->where('code', '20K')->firstOrFail();
        $this->rate($owner, $gold, $purity18, '4800');
        $this->rate($owner, $gold, $purity20, '5200');

        $customer = $this->customer($owner);
        $payload = $this->booking($customer, $gold, $purity18, '15000');
        unset($payload['metal_uuid'], $payload['purity_uuid'], $payload['expected_weight']);
        $payload['description'] = 'Ring and tops set';
        $payload['lines'] = [
            [
                'metal_uuid' => $gold->uuid,
                'purity_uuid' => $purity18->uuid,
                'expected_weight' => '4',
                'rate_per_gram' => '4800',
            ],
            [
                'metal_uuid' => $gold->uuid,
                'purity_uuid' => $purity20->uuid,
                'expected_weight' => '6',
                'rate_per_gram' => '5200',
            ],
        ];

        $this->actingAs($owner)->post(route('orders.store'), $payload)->assertRedirect();
        $this->seeShop($owner);
        $order = AdvanceOrder::query()->with('lines')->latest('id')->firstOrFail();
        $this->assertSame('Ring and tops set', $order->description);
        $this->assertCount(2, $order->lines);
        $this->assertSame('10.000', (string) $order->expected_weight);

        $this->actingAs($owner)->post(route('orders.ready', $order))->assertRedirect();
        $ring = $this->piece($owner, $gold, $purity18, 'ORD18');
        $tops = $this->piece($owner, $gold, $purity20, 'ORD20');

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $customer->uuid,
            'order_uuid' => $order->uuid,
            'item_ids' => [$ring->uuid, $tops->uuid],
            'discount' => '0',
            'payments' => [['method' => 'cash', 'amount' => '35000']],
        ])->assertRedirect();

        $sale = Sale::query()->latest('id')->firstOrFail();
        $rates = $sale->lines()->orderBy('id')->pluck('rate_per_gram')->map(fn ($rate) => (string) $rate)->all();
        $this->assertContains('4800.00', $rates);
        $this->assertContains('5200.00', $rates);
        $this->assertSame('15000.00', (string) $sale->advance_amount);
    }

    public function test_an_order_can_keep_a_rate_other_than_today(): void
    {
        [$owner, $gold, $purity] = $this->counter();
        $customer = $this->customer($owner);

        $this->actingAs($owner)->post(route('orders.store'), $this->booking($customer, $gold, $purity, '1000') + [
            'rate_per_gram' => '4500',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->seeShop($owner);
        $order = AdvanceOrder::query()->latest('id')->firstOrFail();
        $this->assertSame('4500.00', (string) $order->rate_per_gram);
        $this->assertSame('5000.00', (string) $order->metalRate->rate_per_gram);
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
