<?php

namespace Tests\Feature\Commerce;

use App\Enums\ChargeAppliesTo;
use App\Enums\ItemStatus;
use App\Enums\PartyType;
use App\Models\ChargeMethod;
use App\Models\Customer;
use App\Models\GirviPledge;
use App\Models\GoldScheme;
use App\Models\Item;
use App\Models\MetalType;
use App\Models\OldGoldExchange;
use App\Models\OldGoldMovement;
use App\Models\Purity;
use App\Models\RepairOrder;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\StockLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Commerce\LedgerService;
use App\Services\Commerce\OldGoldStockService;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkshopTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_purchase_is_payable_until_it_is_paid_or_sent_back(): void
    {
        [$owner, $gold, $purity] = $this->counter();
        $supplier = $this->supplier($owner);

        $this->actingAs($owner)->post(route('purchases.store'), $this->piece($supplier, $gold, $purity, 'CHAIN01'))->assertRedirect();
        $this->seeShop($owner);
        $item = Item::query()->where('item_code', 'CHAIN01')->firstOrFail();
        $this->assertSame(ItemStatus::Available, $item->status);
        $this->assertSame('51500.00', (string) $item->purchaseLines()->firstOrFail()->purchase->total);
        $this->assertSame('-51500.00', app(LedgerService::class)->balance(PartyType::Supplier, (int) $supplier->id));

        $this->actingAs($owner)->post(route('suppliers.payments.store', $supplier), [
            'method' => 'cash',
            'amount' => '51500',
        ])->assertRedirect();
        $this->seeShop($owner);
        $this->assertSame('0.00', app(LedgerService::class)->balance(PartyType::Supplier, (int) $supplier->id));

        $this->actingAs($owner)->post(route('purchases.store'), $this->piece($supplier, $gold, $purity, 'CHAIN02'))->assertRedirect();
        $this->seeShop($owner);
        $second = Item::query()->where('item_code', 'CHAIN02')->firstOrFail();
        $this->actingAs($owner)->post(route('purchases.return', $second->purchaseLines()->firstOrFail()->purchase), [
            'lines' => [$second->purchaseLines()->firstOrFail()->uuid],
        ])->assertRedirect();
        $this->seeShop($owner);
        $second->refresh();
        $this->assertSame(ItemStatus::SentBack, $second->status);
        $this->assertSame('0.00', app(LedgerService::class)->balance(PartyType::Supplier, (int) $supplier->id));
    }

    public function test_a_sale_can_be_returned_once_and_restocked(): void
    {
        [$owner, $gold, $purity] = $this->counter();
        $walkIn = Customer::query()->where('code', 'WALKIN')->firstOrFail();
        $this->actingAs($owner)->post(route('items.store'), $this->plainPiece($gold, $purity, 'COIN01'))->assertRedirect();
        $this->seeShop($owner);
        $item = Item::query()->where('item_code', 'COIN01')->firstOrFail();

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $walkIn->uuid,
            'item_ids' => [$item->uuid],
            'discount' => '0',
            'payments' => [
                ['method' => 'cash', 'amount' => '51500'],
            ],
        ])->assertRedirect();

        $this->seeShop($owner);
        $line = SaleLine::query()->where('item_id', $item->id)->firstOrFail();
        $this->assertSame('51500.00', (string) $line->sale->total);

        $this->actingAs($owner)->post(route('sales.returns.store', $line->sale), [
            'lines' => [$line->uuid],
            'refund' => '51500',
        ])->assertRedirect();

        $this->seeShop($owner);
        $item->refresh();
        $this->assertSame(ItemStatus::Available, $item->status);
        $this->assertSame('0.00', app(LedgerService::class)->balance(PartyType::Customer, (int) $walkIn->id));

        $this->actingAs($owner)->post(route('sales.returns.store', $line->sale), [
            'lines' => [$line->uuid],
            'refund' => '0',
        ])->assertSessionHasErrors('lines');
    }

    public function test_old_gold_credits_the_customer(): void
    {
        [$owner, $gold, $purity] = $this->counter();
        $customer = $this->customer($owner);

        $this->actingAs($owner)->post(route('old-gold.store'), [
            'customer_uuid' => $customer->uuid,
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'gross_weight' => '10',
            'stone_weight' => '0',
            'melting_loss_percent' => '2',
            'rate_per_gram' => '6000',
            'deduction_amount' => '100',
            'testing_result' => '22K',
            'refund' => '0',
        ])->assertRedirect();

        $this->seeShop($owner);
        $this->assertSame('-58700.00', app(LedgerService::class)->balance(PartyType::Customer, (int) $customer->id));
    }

    public function test_old_gold_credit_comes_off_the_next_bill_but_order_advances_stay_with_the_order(): void
    {
        [$owner, $gold, $purity] = $this->counter();
        $customer = $this->customer($owner);
        $walkIn = Customer::query()->where('code', 'WALKIN')->firstOrFail();
        $ledger = app(LedgerService::class);

        $this->actingAs($owner)->post(route('old-gold.store'), [
            'customer_uuid' => $customer->uuid,
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'gross_weight' => '10',
            'rate_per_gram' => '6000',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('orders.store'), [
            'customer_uuid' => $customer->uuid,
            'description' => 'Bridal ring',
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'expected_weight' => '10',
            'estimated_making' => '0',
            'advance' => '10000',
            'method' => 'cash',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->seeShop($owner);
        $this->assertSame('60000.00', $ledger->spendableCredit((int) $customer->id));

        $this->actingAs($owner)->post(route('items.store'), $this->plainPiece($gold, $purity, 'COIN01'))->assertRedirect();
        $this->actingAs($owner)->post(route('items.store'), $this->plainPiece($gold, $purity, 'COIN02'))->assertRedirect();
        $this->seeShop($owner);
        $first = Item::query()->where('item_code', 'COIN01')->firstOrFail();
        $second = Item::query()->where('item_code', 'COIN02')->firstOrFail();

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $customer->uuid,
            'item_ids' => [$first->uuid],
            'use_credit' => '60000',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->seeShop($owner);
        $sale = Sale::query()->latest('id')->firstOrFail();
        $this->assertSame('51500.00', (string) $sale->credit_amount);
        $this->assertSame('0.00', $sale->balanceDue());
        $this->assertSame('8500.00', $ledger->spendableCredit((int) $customer->id));
        $this->actingAs($owner)->get(route('sales.show', $sale))->assertOk()->assertSee('Old gold / credit adjusted');

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $customer->uuid,
            'item_ids' => [$second->uuid],
            'use_credit' => '9000',
        ])->assertSessionHasErrors('use_credit');
        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $walkIn->uuid,
            'item_ids' => [$second->uuid],
            'use_credit' => '100',
            'payments' => [['method' => 'cash', 'amount' => '51400']],
        ])->assertSessionHasErrors();
        $this->seeShop($owner);
        $this->assertSame(1, Sale::query()->count());
    }

    public function test_old_gold_can_be_paid_by_upi_and_printed(): void
    {
        [$owner, $gold, $purity] = $this->counter();
        $customer = $this->customer($owner);

        $this->actingAs($owner)->post(route('old-gold.store'), [
            'customer_uuid' => $customer->uuid,
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'gross_weight' => '10',
            'melting_loss_percent' => '2',
            'rate_per_gram' => '6000',
            'refund' => '20000',
            'method' => 'upi',
            'reference' => 'UPI777',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->seeShop($owner);
        $exchange = OldGoldExchange::query()->firstOrFail();
        $payment = $exchange->payments()->firstOrFail();
        $this->assertSame('upi', $payment->method->value);
        $this->assertSame('UPI777', $payment->reference);
        $this->assertSame('-38800.00', app(LedgerService::class)->balance(PartyType::Customer, (int) $customer->id));
        $this->actingAs($owner)->get(route('old-gold.show', $exchange))->assertOk()->assertSee('Old gold purchase voucher')->assertSee('UPI777');
        $this->actingAs($owner)->get(route('old-gold.index', ['search' => 'Meera']))->assertOk()->assertSee($exchange->number);
    }

    public function test_old_gold_goes_into_old_gold_stock_and_can_become_a_piece_or_be_sent_out(): void
    {
        [$owner, $gold, $purity] = $this->counter();
        $customer = $this->customer($owner);
        $location = StockLocation::query()->where('code', 'MAIN')->firstOrFail();

        $this->actingAs($owner)->post(route('old-gold.store'), [
            'customer_uuid' => $customer->uuid,
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'gross_weight' => '20',
            'rate_per_gram' => '6000',
            'refund' => '0',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->seeShop($owner);
        $exchange = OldGoldExchange::query()->firstOrFail();
        $stock = app(OldGoldStockService::class);
        $this->assertSame('20.000', $stock->balances()->first()['gross']);
        $this->assertSame('120000.00', $stock->balances()->first()['value']);

        $this->actingAs($owner)->get(route('items.create', ['from_old_gold' => $exchange->uuid]))
            ->assertOk()->assertSee('Making a stock piece from old gold')->assertSee($exchange->uuid);
        $this->actingAs($owner)->post(route('items.store'), [
            'name' => 'Old bangle',
            'item_code' => 'OLD-1',
            'old_gold_uuid' => $exchange->uuid,
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'location_uuid' => $location->uuid,
            'gross_weight' => '8',
            'cost_price' => '48000',
            'selling_price' => '60000',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->seeShop($owner);
        $item = Item::query()->where('item_code', 'OLD-1')->firstOrFail();
        $this->assertSame(ItemStatus::Available, $item->status);
        $this->assertSame('12.000', $stock->balances()->first()['gross']);
        $this->assertSame('72000.00', $stock->balances()->first()['value']);
        $this->actingAs($owner)->get(route('old-gold.show', $exchange))->assertOk()->assertSee('OLD-1');

        $this->actingAs($owner)->post(route('old-gold.stock.send'), [
            'purity_uuid' => $purity->uuid,
            'kind' => 'refiner',
            'gross_weight' => '13',
            'fine_weight' => '13',
        ])->assertSessionHasErrors('gross_weight');

        $this->actingAs($owner)->post(route('old-gold.stock.send'), [
            'purity_uuid' => $purity->uuid,
            'kind' => 'refiner',
            'party' => 'Shree Refinery',
            'gross_weight' => '12',
            'fine_weight' => '12',
        ])->assertRedirect(route('old-gold.stock'))->assertSessionHasNoErrors();

        $this->seeShop($owner);
        $this->assertTrue($stock->balances()->isEmpty());
        $this->assertSame('72000.00', (string) OldGoldMovement::query()->where('kind', 'refiner')->value('value'));
        $this->actingAs($owner)->get(route('old-gold.stock'))->assertOk()->assertSee('Shree Refinery')->assertSee('OLD-1');
    }

    public function test_a_repair_is_charged_when_it_is_delivered(): void
    {
        [$owner] = $this->counter();
        $walkIn = Customer::query()->where('code', 'WALKIN')->firstOrFail();

        $this->actingAs($owner)->post(route('repairs.store'), [
            'customer_uuid' => $walkIn->uuid,
            'description' => 'Chain link',
            'problem' => 'Broken clasp',
            'gross_weight' => '12.5',
            'estimated_cost' => '1500',
        ])->assertRedirect();

        $this->seeShop($owner);
        $repair = RepairOrder::query()->firstOrFail();

        foreach (['inspection', 'repairing', 'ready'] as $status) {
            $this->actingAs($owner)->post(route('repairs.status', $repair), ['status' => $status])->assertRedirect();
        }

        $this->actingAs($owner)->post(route('repairs.deliver', $repair), [
            'final_charge' => '1500',
            'payment' => '1500',
            'method' => 'cash',
        ])->assertRedirect();

        $this->seeShop($owner);
        $repair->refresh();
        $this->assertSame('delivered', $repair->status);
        $this->assertSame('12.500', (string) $repair->gross_weight);
        $this->assertSame('0.00', app(LedgerService::class)->balance(PartyType::Customer, (int) $walkIn->id));
    }

    public function test_a_quick_repair_can_be_marked_ready_straight_away(): void
    {
        [$owner] = $this->counter();
        $customer = $this->customer($owner);

        $this->actingAs($owner)->post(route('repairs.store'), [
            'customer_uuid' => $customer->uuid,
            'description' => 'Ring',
            'problem' => 'Polish',
            'gross_weight' => '4.2',
            'estimated_cost' => '300',
        ])->assertRedirect();

        $this->seeShop($owner);
        $repair = RepairOrder::query()->firstOrFail();
        $this->actingAs($owner)->post(route('repairs.status', $repair), ['status' => 'ready'])->assertRedirect();
        $this->assertSame('ready', $repair->refresh()->status);

        $this->actingAs($owner)->get(route('repairs.index', ['show' => 'ready']))->assertOk()->assertSee($repair->number);
        $this->actingAs($owner)->get(route('repairs.index', ['show' => 'delivered']))->assertOk()->assertDontSee($repair->number);
        $this->actingAs($owner)->get(route('repairs.show', $repair))->assertOk()->assertSee('Deliver and collect');

        $this->actingAs($owner)->post(route('repairs.status', $repair), ['status' => 'cancelled'])->assertSessionHasErrors();
    }

    public function test_a_scheme_matures_only_after_every_installment(): void
    {
        [$owner] = $this->counter();
        $customer = $this->customer($owner);

        $this->actingAs($owner)->post(route('schemes.store'), [
            'code' => 'GOLD11',
            'name' => 'Eleven month gold',
            'installment_mode' => 'fixed',
            'monthly_amount' => '5000',
            'duration_months' => '2',
            'bonus_type' => 'extra_installment',
            'bonus_value' => '0',
            'is_active' => '1',
        ])->assertRedirect();

        $this->seeShop($owner);
        $scheme = GoldScheme::query()->where('code', 'GOLD11')->firstOrFail();
        $this->actingAs($owner)->get(route('schemes.show', $scheme))
            ->assertOk()
            ->assertSee('Add a member')
            ->assertSee('Open to join')
            ->assertSee('No members yet.');
        $this->actingAs($owner)->post(route('schemes.enroll', $scheme), [
            'customer_uuid' => $customer->uuid,
        ])->assertRedirect();

        $this->seeShop($owner);
        $enrollment = $scheme->enrollments()->firstOrFail();
        $this->actingAs($owner)->get(route('enrollments.show', $enrollment))
            ->assertOk()
            ->assertSee('Collect month 1 of 2');
        $this->actingAs($owner)->post(route('enrollments.installments.store', $enrollment), [
            'amount' => '5000',
            'method' => 'cash',
        ])->assertRedirect();
        $this->actingAs($owner)->post(route('enrollments.mature', $enrollment))->assertSessionHasErrors('scheme');
        $this->actingAs($owner)->get(route('schemes.show', $scheme))
            ->assertOk()
            ->assertSee($customer->name)
            ->assertSee('1 of 2')
            ->assertSee('Paying');

        $this->actingAs($owner)->post(route('enrollments.installments.store', $enrollment), [
            'amount' => '5000',
            'method' => 'cash',
        ])->assertRedirect();
        $this->actingAs($owner)->post(route('enrollments.mature', $enrollment))->assertRedirect();

        $this->seeShop($owner);
        $enrollment->refresh();
        $this->assertSame('matured', $enrollment->status);
        $this->assertSame('15000.00', (string) $enrollment->maturity_amount);
        $this->assertSame('-15000.00', app(LedgerService::class)->balance(PartyType::Customer, (int) $customer->id));
        $this->actingAs($owner)->get(route('enrollments.show', $enrollment))
            ->assertOk()
            ->assertSee('Matured')
            ->assertSee('15,000.00')
            ->assertDontSee('Collect month');
    }

    public function test_girvi_lends_a_percentage_of_the_gold_and_charges_monthly_interest(): void
    {
        [$owner, $gold, $purity] = $this->counter();
        $customer = $this->customer($owner);

        $this->actingAs($owner)->post(route('girvi.store'), [
            'customer_uuid' => $customer->uuid,
            'pieces' => [
                [
                    'description' => 'Chain',
                    'metal_uuid' => $gold->uuid,
                    'purity_uuid' => $purity->uuid,
                    'gross_weight' => '10',
                    'stone_weight' => '0',
                    'rate_per_gram' => '6000',
                ],
                [
                    'description' => 'Ring',
                    'metal_uuid' => $gold->uuid,
                    'purity_uuid' => $purity->uuid,
                    'gross_weight' => '5',
                    'stone_weight' => '0',
                    'rate_per_gram' => '6000',
                ],
            ],
            'loan_mode' => 'percent',
            'loan_percent' => '70',
            'interest_percent' => '2',
        ])->assertRedirect();

        $this->seeShop($owner);
        $pledge = GirviPledge::query()->with('items')->firstOrFail();
        $this->assertCount(2, $pledge->items);
        $this->assertSame('90000.00', (string) $pledge->gold_value);
        $this->assertSame('63000.00', (string) $pledge->principal);
        $this->assertSame('63000.00', app(LedgerService::class)->balance(PartyType::Customer, (int) $customer->id));
        $this->actingAs($owner)->get(route('girvi.show', $pledge))
            ->assertOk()
            ->assertSee('Gold in shop')
            ->assertSee('Collect interest or release')
            ->assertSee('63,000.00')
            ->assertSee('64,260.00');
        $this->actingAs($owner)->get(route('girvi.index'))->assertOk()->assertSee($pledge->number);
        $this->actingAs($owner)->get(route('girvi.index', ['show' => 'released']))->assertOk()->assertDontSee($pledge->number);

        $this->actingAs($owner)->post(route('girvi.settle', $pledge), [
            'action' => 'interest',
            'months' => '1',
            'payment' => '1260',
            'method' => 'cash',
        ])->assertRedirect();

        $this->seeShop($owner);
        $pledge->refresh();
        $this->assertSame('open', $pledge->status);
        $this->assertSame('63000.00', app(LedgerService::class)->balance(PartyType::Customer, (int) $customer->id));

        $this->actingAs($owner)->post(route('girvi.settle', $pledge), [
            'action' => 'release',
            'months' => '1',
            'payment' => '64260',
            'method' => 'cash',
        ])->assertRedirect();

        $this->seeShop($owner);
        $pledge->refresh();
        $this->assertSame('released', $pledge->status);
        $this->assertSame('2520.00', (string) $pledge->interest_charged);
        $this->assertSame('0.00', app(LedgerService::class)->balance(PartyType::Customer, (int) $customer->id));
        $this->actingAs($owner)->get(route('girvi.show', $pledge))
            ->assertOk()
            ->assertSee('Interest collected')
            ->assertSee('2,520.00')
            ->assertDontSee('Collect interest or release');
        $this->actingAs($owner)->get(route('girvi.index', ['show' => 'released']))->assertOk()->assertSee($pledge->number);
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
        $this->actingAs($owner)->post(route('rates.store'), [
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'rate_per_gram' => '5000',
            'source' => 'manual',
        ])->assertRedirect();

        return [$owner, $gold, $purity];
    }

    private function supplier(User $owner): Supplier
    {
        $this->actingAs($owner)->post(route('suppliers.store'), [
            'code' => 'SUP01',
            'name' => 'Mumbai Bullion',
            'kyc_status' => 'pending',
            'is_active' => '1',
        ])->assertRedirect();
        $this->seeShop($owner);

        return Supplier::query()->where('code', 'SUP01')->firstOrFail();
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

    /**
     * @return array<string, mixed>
     */
    private function piece(Supplier $supplier, MetalType $gold, Purity $purity, string $code): array
    {
        $piece = $this->plainPiece($gold, $purity, $code);

        return [
            'supplier_uuid' => $supplier->uuid,
            'purchased_on' => now()->toDateString(),
            'location_uuid' => $piece['location_uuid'],
            'pricing' => 'rate',
            'gst_percent' => '3',
            'lines' => [
                array_merge($piece, ['rate_per_gram' => '5000']),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function plainPiece(MetalType $gold, Purity $purity, string $code): array
    {
        $this->seeShop(User::query()->where('company_id', $gold->company_id)->firstOrFail());
        $location = StockLocation::query()->where('code', 'MAIN')->firstOrFail();
        $making = ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Making)->where('code', 'fixed')->firstOrFail();
        $wastage = ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Wastage)->where('code', 'fixed')->firstOrFail();

        return [
            'name' => 'Plain chain',
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
        ];
    }

    private function seeShop(User $user): void
    {
        app(CompanyContext::class)->set($user->company);
    }
}
