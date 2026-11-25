<?php

namespace Tests\Feature\Commerce;

use App\Enums\ChargeAppliesTo;
use App\Enums\ItemSource;
use App\Enums\ItemStatus;
use App\Enums\PartyType;
use App\Models\ChargeMethod;
use App\Models\Customer;
use App\Models\GirviPledge;
use App\Models\GoldScheme;
use App\Models\Item;
use App\Models\MetalType;
use App\Models\MetalStockMovement;
use App\Models\OldGoldExchange;
use App\Models\OldGoldMovement;
use App\Models\Payment;
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

    public function test_pieces_carry_their_source_and_stock_can_be_filtered_by_it(): void
    {
        [$owner, $gold, $purity] = $this->counter();
        $supplier = $this->supplier($owner);

        $this->actingAs($owner)->post(route('purchases.store'), $this->piece($supplier, $gold, $purity, 'CHAIN01'))->assertRedirect();
        $this->actingAs($owner)->post(route('items.store'), $this->plainPiece($gold, $purity, 'OWN01'))->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('items.store'), $this->plainPiece($gold, $purity, 'BOUGHT01') + ['source' => 'purchased'])->assertRedirect()->assertSessionHasNoErrors();

        $this->seeShop($owner);
        $this->assertSame(ItemSource::Purchased, Item::query()->where('item_code', 'CHAIN01')->sole()->source);
        $this->assertSame(ItemSource::Own, Item::query()->where('item_code', 'OWN01')->sole()->source);
        $this->assertSame(ItemSource::Purchased, Item::query()->where('item_code', 'BOUGHT01')->sole()->source);

        $codes = fn (string $route, string $key, string $source) => $this->actingAs($owner)->get(route($route, $route === 'reports.stock' ? ['source' => $source, 'report' => 1] : ['source' => $source]))
            ->assertOk()->viewData($key)->pluck('item_code')->sort()->values()->all();

        $this->assertTrue(Item::query()->where('item_code', 'CHAIN01')->sole()->own_gold);
        $this->assertSame(['BOUGHT01', 'OWN01'], $codes('items.index', 'items', 'own'));
        $this->assertSame(['BOUGHT01', 'OWN01'], $codes('items.index', 'items', ''));
        $this->assertSame(['BOUGHT01', 'OWN01'], $codes('reports.stock', 'pieces', 'own'));
        $this->assertSame(['BOUGHT01', 'OWN01'], $codes('reports.stock', 'pieces', ''));

        $chain = Item::query()->where('item_code', 'CHAIN01')->sole();
        $own = Item::query()->where('item_code', 'OWN01')->sole();
        $this->actingAs($owner)->put(route('items.update', $own), $this->plainPiece($gold, $purity, 'OWN01') + ['source' => 'purchased'])->assertRedirect();
        $this->actingAs($owner)->put(route('items.update', $chain), $this->plainPiece($gold, $purity, 'CHAIN01') + ['source' => 'own'])->assertRedirect();

        $this->seeShop($owner);
        $this->assertSame(ItemSource::Purchased, $own->refresh()->source);
        $this->assertSame(ItemSource::Purchased, $chain->refresh()->source);
    }

    public function test_a_sale_can_be_returned_once_and_restocked(): void
    {
        [$owner, $gold, $purity] = $this->counter();
        $buyer = $this->counterCustomer($owner);
        $this->actingAs($owner)->post(route('items.store'), $this->plainPiece($gold, $purity, 'COIN01'))->assertRedirect();
        $this->seeShop($owner);
        $item = Item::query()->where('item_code', 'COIN01')->firstOrFail();

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $buyer->uuid,
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
        $this->assertSame('0.00', app(LedgerService::class)->balance(PartyType::Customer, (int) $buyer->id));

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
        $this->assertSame(ItemSource::OldGold, $item->source);
        $this->assertSame('12.000', $stock->balances()->first()['gross']);
        $this->assertSame('72000.00', $stock->balances()->first()['value']);
        $this->actingAs($owner)->get(route('old-gold.show', $exchange))->assertOk()->assertSee('OLD-1');

        $this->actingAs($owner)->post(route('old-gold.stock.send'), [
            'purity_uuid' => $purity->uuid,
            'kind' => 'refiner',
            'gross_weight' => '13',
            'fine_weight' => '13',
        ])->assertSessionHasErrors('gross_weight');

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $customer->uuid,
            'item_ids' => [$item->uuid],
            'use_credit' => '60000',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->seeShop($owner);
        $this->assertSame(0, MetalStockMovement::query()->where('kind', 'sale')->count());

        $this->actingAs($owner)->post(route('old-gold.stock.send'), [
            'purity_uuid' => $purity->uuid,
            'kind' => 'dealer',
            'party' => 'Shree Bullion',
            'gross_weight' => '12',
            'fine_weight' => '12',
            'amount_received' => '70000',
        ])->assertRedirect(route('old-gold.stock'))->assertSessionHasNoErrors();

        $this->seeShop($owner);
        $this->assertTrue($stock->balances()->isEmpty());
        $movement = OldGoldMovement::query()->where('kind', 'dealer')->firstOrFail();
        $this->assertSame('72000.00', (string) $movement->value);
        $this->assertSame('70000.00', (string) $movement->amount_received);
        $cashIn = Payment::query()->where('old_gold_movement_id', $movement->id)->where('direction', 'in')->firstOrFail();
        $this->assertSame('70000.00', (string) $cashIn->amount);
        $this->actingAs($owner)->get(route('old-gold.stock'))->assertOk()->assertSee('Shree Bullion')->assertSee('OLD-1');
        $this->actingAs($owner)->get(route('overview'))
            ->assertOk()
            ->assertSee('Net sell today', false)
            ->assertSee('Old gold recovered', false)
            ->assertSee('70,000', false);
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

    public function test_a_supplier_payment_can_be_dated_back_but_not_into_the_future(): void
    {
        [$owner, $gold, $purity] = $this->counter();
        $supplier = $this->supplier($owner);

        $this->actingAs($owner)->post(route('purchases.store'), $this->piece($supplier, $gold, $purity, 'CHAIN09'))->assertRedirect();
        $this->seeShop($owner);

        $this->actingAs($owner)->get(route('suppliers.show', $supplier))
            ->assertOk()
            ->assertSee('name="paid_on"', false);

        $paidOn = now()->subDays(3)->toDateString();
        $this->actingAs($owner)->post(route('suppliers.payments.store', $supplier), [
            'method' => 'cash',
            'amount' => '20000',
            'paid_on' => $paidOn,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->seeShop($owner);
        $payment = \App\Models\Payment::query()->where('supplier_id', $supplier->id)->latest('id')->firstOrFail();
        $this->assertSame($paidOn, $payment->received_at->timezone(config('app.timezone'))->toDateString());

        $this->actingAs($owner)->post(route('suppliers.payments.store', $supplier), [
            'method' => 'cash',
            'amount' => '1000',
            'paid_on' => now()->addDay()->toDateString(),
        ])->assertSessionHasErrors('paid_on');

        $this->seeShop($owner);
        $this->assertSame(1, \App\Models\Payment::query()->where('supplier_id', $supplier->id)->count());
    }

    public function test_the_supplier_passbook_lists_bills_and_payments_by_their_own_dates_with_a_running_balance(): void
    {
        [$owner, $gold, $purity] = $this->counter();
        $supplier = $this->supplier($owner);
        $billDate = now()->subDays(10)->toDateString();
        $paidOn = now()->subDays(4)->toDateString();

        $bill = $this->piece($supplier, $gold, $purity, 'CHAIN10');
        $bill['purchased_on'] = $billDate;
        $this->actingAs($owner)->post(route('purchases.store'), $bill)->assertSessionHasNoErrors()->assertRedirect();
        $this->seeShop($owner);
        $this->actingAs($owner)->post(route('suppliers.payments.store', $supplier), [
            'method' => 'upi',
            'amount' => '20000',
            'reference' => 'UTR778',
            'paid_on' => $paidOn,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->seeShop($owner);

        $purchase = \App\Models\Purchase::query()->where('supplier_id', $supplier->id)->firstOrFail();
        $owed = number_format((float) $purchase->total - 20000, 2, '.', '');
        $dayOf = fn (string $day) => \Illuminate\Support\Carbon::parse($day)->format('d-m-Y');

        $passbook = $this->actingAs($owner)->get(route('reports.suppliers', [
            'supplier' => $supplier->uuid,
            'from' => now()->subDays(30)->toDateString(),
            'to' => now()->toDateString(),
        ]));
        $passbook->assertOk()
            ->assertSeeInOrder([$dayOf($billDate), 'Purchase '.$purchase->number, '1 piece', $dayOf($paidOn), 'UPI', 'Ref UTR778']);
        $this->assertSame(
            ['opening' => '0.00', 'purchased' => number_format((float) $purchase->total, 2, '.', ''), 'returned' => '0.00', 'paid' => '20000.00', 'closing' => $owed],
            $passbook->viewData('sums'),
        );
        $this->assertSame([$billDate, $paidOn], array_column($passbook->viewData('passbook'), 'date'));
        $this->assertSame($owed, $passbook->viewData('passbook')[1]['balance']);

        $later = $this->actingAs($owner)->get(route('reports.suppliers', [
            'supplier' => $supplier->uuid,
            'from' => now()->subDays(6)->toDateString(),
            'to' => now()->toDateString(),
        ]))->assertOk();
        $this->assertSame(number_format((float) $purchase->total, 2, '.', ''), $later->viewData('sums')['opening']);
        $this->assertSame('0.00', $later->viewData('sums')['purchased']);
        $this->assertSame('20000.00', $later->viewData('sums')['paid']);
        $this->assertSame($owed, $later->viewData('sums')['closing']);

        $summary = $this->actingAs($owner)->get(route('reports.suppliers', ['range' => 'all']))->assertOk()->assertSee('Mumbai Bullion');
        $this->assertSame(1, $summary->viewData('supplierCount'));
        $this->assertSame($owed, $summary->viewData('sums')['closing']);

        $this->actingAs($owner)->get(route('suppliers.show', $supplier))->assertOk()->assertSee('Full passbook');

        $pdf = $this->actingAs($owner)->get(route('reports.suppliers', ['supplier' => $supplier->uuid, 'range' => 'all', 'pdf' => 1]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('supplier-passbook-sup01-', (string) $pdf->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->actingAs($owner)->get(route('reports.suppliers', ['range' => 'all', 'pdf' => 1]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_an_installment_can_be_dated_back_to_on_or_after_the_start_date(): void
    {
        [$owner] = $this->counter();
        $customer = $this->customer($owner);

        $this->actingAs($owner)->post(route('schemes.store'), [
            'code' => 'GOLD12',
            'name' => 'Twelve month gold',
            'installment_mode' => 'fixed',
            'monthly_amount' => '5000',
            'duration_months' => '2',
            'bonus_type' => 'extra_installment',
            'bonus_value' => '0',
            'is_active' => '1',
        ])->assertRedirect();

        $this->seeShop($owner);
        $scheme = GoldScheme::query()->where('code', 'GOLD12')->firstOrFail();
        $startedOn = now()->subMonths(2)->toDateString();
        $this->actingAs($owner)->post(route('schemes.enroll', $scheme), [
            'customer_uuid' => $customer->uuid,
            'started_on' => $startedOn,
        ])->assertRedirect();

        $this->seeShop($owner);
        $enrollment = $scheme->enrollments()->firstOrFail();
        $this->actingAs($owner)->get(route('enrollments.show', $enrollment))
            ->assertOk()
            ->assertSee('name="paid_on"', false);

        $paidOn = now()->subMonth()->toDateString();
        $this->actingAs($owner)->post(route('enrollments.installments.store', $enrollment), [
            'amount' => '5000',
            'method' => 'cash',
            'paid_on' => $paidOn,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->seeShop($owner);
        $first = $enrollment->installments()->orderBy('due_on')->firstOrFail();
        $this->assertSame($paidOn, $first->paid_at->timezone(config('app.timezone'))->toDateString());
        $this->assertSame($paidOn, \App\Models\Payment::query()->findOrFail($first->payment_id)->received_at->timezone(config('app.timezone'))->toDateString());

        $this->actingAs($owner)->post(route('enrollments.installments.store', $enrollment), [
            'amount' => '5000',
            'method' => 'cash',
            'paid_on' => now()->subMonths(3)->toDateString(),
        ])->assertSessionHasErrors('paid_on');

        $this->actingAs($owner)->post(route('enrollments.installments.store', $enrollment), [
            'amount' => '5000',
            'method' => 'cash',
            'paid_on' => now()->addDay()->toDateString(),
        ])->assertSessionHasErrors('paid_on');

        $this->seeShop($owner);
        $this->assertSame(1, $enrollment->installments()->whereNotNull('paid_at')->count());
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
        $startedOn = now()->subMonths(2)->toDateString();
        $this->actingAs($owner)->get(route('schemes.show', $scheme))
            ->assertOk()
            ->assertSee('name="started_on"', false)
            ->assertSee('Start date', false);
        $this->actingAs($owner)->post(route('schemes.enroll', $scheme), [
            'customer_uuid' => $customer->uuid,
            'started_on' => $startedOn,
        ])->assertRedirect();

        $this->seeShop($owner);
        $enrollment = $scheme->enrollments()->firstOrFail();
        $this->assertSame($startedOn, $enrollment->started_on->toDateString());
        $this->assertSame($startedOn, $enrollment->installments()->orderBy('due_on')->firstOrFail()->due_on->toDateString());
        $this->actingAs($owner)->get(route('enrollments.show', $enrollment))
            ->assertOk()
            ->assertSee('Collect month 1 of 2')
            ->assertSee('Make the bill', false)
            ->assertSee(route('sales.create', ['customer' => $customer->uuid]), false);
        $this->actingAs($owner)->get(route('schemes.show', $scheme))
            ->assertOk()
            ->assertSee('>Bill</a>', false);
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
