<?php

namespace Tests\Feature\Commerce;

use App\Enums\ChargeAppliesTo;
use App\Enums\ItemStatus;
use App\Enums\PartyType;
use App\Models\Category;
use App\Models\ChargeMethod;
use App\Models\Customer;
use App\Models\Item;
use App\Models\MetalRate;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\SaleLine;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\Access\AccessProvisioner;
use App\Services\Commerce\LedgerService;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounterTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_piece_is_billed_from_the_rate_saved_at_that_moment(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '22K')->firstOrFail();
        $walkIn = Customer::query()->where('code', 'WALKIN')->firstOrFail();

        $this->actingAs($owner)->post(route('rates.store'), [
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'rate_per_gram' => '6000',
            'source' => 'manual',
        ])->assertRedirect(route('rates.index'));

        $this->actingAs($owner)->post(route('items.store'), $this->piece('RING01', $gold, $purity))->assertRedirect();

        $this->seeShop($owner);
        $item = Item::query()->where('item_code', 'RING01')->firstOrFail();
        $this->assertSame(ItemStatus::Available, $item->status);

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $walkIn->uuid,
            'item_ids' => [$item->uuid],
            'discount' => '500',
            'payments' => [
                ['method' => 'cash', 'amount' => '20000'],
            ],
        ])->assertSessionHasErrors('payments');

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $walkIn->uuid,
            'item_ids' => [$item->uuid],
            'discount' => '500',
            'payments' => [
                ['method' => 'cash', 'amount' => '20000'],
                ['method' => 'upi', 'amount' => '50555', 'reference' => 'UPI123'],
            ],
        ])->assertRedirect();

        $this->seeShop($owner);
        $item->refresh();
        $this->assertSame(ItemStatus::Sold, $item->status);
        $line = SaleLine::query()->where('item_id', $item->id)->firstOrFail();
        $this->assertSame('6000.00', (string) $line->rate_per_gram);
        $this->assertSame('70555.00', (string) $line->sale->total);

        $this->actingAs($owner)->post(route('rates.store'), [
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'rate_per_gram' => '6100',
            'source' => 'manual',
        ])->assertRedirect(route('rates.index'));

        $this->seeShop($owner);
        $this->assertSame(2, MetalRate::query()->count());
        $this->assertSame('6000.00', (string) $line->fresh()->rate_per_gram);

        $this->actingAs($owner)
            ->get(route('sales.show', $line->sale))
            ->assertOk()
            ->assertSee('70,555.00')
            ->assertSee('UPI123');

        $this->actingAs($owner)->post(route('items.store'), $this->piece('RING02', $gold, $purity))->assertRedirect();
        $this->seeShop($owner);
        $second = Item::query()->where('item_code', 'RING02')->firstOrFail();

        $this->actingAs($owner)->post(route('customers.store'), [
            'code' => 'MEERA',
            'name' => 'Meera Shah',
            'mobile' => '9876500000',
            'kyc_status' => 'pending',
            'customer_type' => 'retail',
            'is_active' => '1',
        ])->assertRedirect();

        $this->seeShop($owner);
        $customer = Customer::query()->where('code', 'MEERA')->firstOrFail();

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $customer->uuid,
            'item_ids' => [$second->uuid],
            'discount' => '500',
            'payments' => [
                ['method' => 'cash', 'amount' => '10000'],
            ],
        ])->assertRedirect();

        $this->seeShop($owner);
        $this->assertSame('61637.00', app(LedgerService::class)->balance(PartyType::Customer, (int) $customer->id));

        $this->actingAs($owner)
            ->get(route('reports.outstanding'))
            ->assertOk()
            ->assertSee('Meera Shah');

        $this->actingAs($owner)->post(route('customers.payments.store', $customer), [
            'method' => 'upi',
            'amount' => '61637',
            'reference' => 'SETTLE',
        ])->assertRedirect(route('customers.show', $customer));

        $this->seeShop($owner);
        $this->assertSame('0.00', app(LedgerService::class)->balance(PartyType::Customer, (int) $customer->id));
    }

    public function test_a_cashier_can_see_pieces_but_cannot_add_one(): void
    {
        $owner = $this->shopUser();
        $cashier = User::factory()->create([
            'company_id' => $owner->company_id,
            'email' => 'cashier@jagdamba.test',
        ]);
        $this->seeShop($owner);
        app(AccessProvisioner::class)->grant($cashier, 'cashier');

        $this->actingAs($cashier)->get(route('items.index'))->assertOk();
        $this->actingAs($cashier)->post(route('items.store'), [])->assertForbidden();
        $this->actingAs($cashier)->get(route('sales.create'))->assertOk()->assertSee('Walk-in');
    }

    public function test_another_shop_cannot_open_this_piece(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '22K')->firstOrFail();
        $this->actingAs($owner)->post(route('items.store'), $this->piece('RING01', $gold, $purity))->assertRedirect();
        $this->seeShop($owner);
        $item = Item::query()->where('item_code', 'RING01')->firstOrFail();

        $other = $this->shopUser([
            'name' => 'Other Jewellers',
            'code' => 'OTHER',
            'gstin' => '29ABCDE1234F1Z5',
            'pan' => 'ABCDE1234F',
            'email' => 'other@jagdamba.test',
        ], [
            'email' => 'owner-other@jagdamba.test',
        ]);

        $this->actingAs($other)->get(route('items.show', $item))->assertNotFound();
    }

    /**
     * @return array<string, mixed>
     */
    private function piece(string $code, MetalType $gold, Purity $purity): array
    {
        $this->seeShop(User::query()->where('company_id', $gold->company_id)->firstOrFail());
        $location = StockLocation::query()->where('code', 'MAIN')->firstOrFail();
        $making = ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Making)->where('code', 'per_gram')->firstOrFail();
        $wastage = ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Wastage)->where('code', 'percentage')->firstOrFail();
        $category = Category::query()->where('code', 'RING')->firstOrFail();

        return [
            'name' => 'Gold ring',
            'item_code' => $code,
            'sku' => $code,
            'category_uuid' => $category->uuid,
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'location_uuid' => $location->uuid,
            'gross_weight' => '10',
            'stone_weight' => '0',
            'other_weight' => '0',
            'making_method_uuid' => $making->uuid,
            'making_value' => '500',
            'wastage_method_uuid' => $wastage->uuid,
            'wastage_value' => '5',
            'stone_value' => '1000',
            'cost_price' => '60000',
            'selling_price' => '70000',
            'mrp' => '75000',
        ];
    }

    private function seeShop(User $user): void
    {
        app(CompanyContext::class)->set($user->company);
    }
}
