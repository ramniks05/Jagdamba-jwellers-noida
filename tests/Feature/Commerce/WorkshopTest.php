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
use App\Models\Purity;
use App\Models\RepairOrder;
use App\Models\SaleLine;
use App\Models\StockLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Commerce\LedgerService;
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
        $this->actingAs($owner)->post(route('purchases.return', $second->purchaseLines()->firstOrFail()->purchase))->assertRedirect();
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
        $this->actingAs($owner)->post(route('schemes.enroll', $scheme), [
            'customer_uuid' => $customer->uuid,
        ])->assertRedirect();

        $this->seeShop($owner);
        $enrollment = $scheme->enrollments()->firstOrFail();
        $this->actingAs($owner)->post(route('enrollments.installments.store', $enrollment), [
            'amount' => '5000',
            'method' => 'cash',
        ])->assertRedirect();
        $this->actingAs($owner)->post(route('enrollments.mature', $enrollment))->assertSessionHasErrors('scheme');

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
        return array_merge($this->plainPiece($gold, $purity, $code), [
            'supplier_uuid' => $supplier->uuid,
        ]);
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
