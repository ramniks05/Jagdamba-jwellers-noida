<?php

namespace Tests\Feature\Commerce;

use App\Enums\ChargeAppliesTo;
use App\Models\ChargeMethod;
use App\Models\Customer;
use App\Models\Item;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\Sale;
use App\Models\StockLocation;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MakingChargeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_shop_default_is_a_processing_charge_without_gst(): void
    {
        [$owner, $item] = $this->counter('RING01');

        $this->sell($owner, $item, null, '56500');

        $sale = Sale::query()->latest('id')->firstOrFail();
        $this->assertSame('processing', $sale->making_mode);
        $this->assertSame('1500.00', (string) $sale->tax_amount);
        $this->assertSame('5000.00', (string) $sale->making_amount);
        $this->assertSame('56500.00', (string) $sale->total);
        $this->actingAs($owner)->get(route('sales.show', $sale))
            ->assertOk()
            ->assertSee('Processing charge (no GST)')
            ->assertSee('CGST 1.5% on jewellery')
            ->assertSeeInOrder(['Net weight', '10.000', 'Net metal value', '50,000.00']);
    }

    public function test_one_bill_can_charge_making_with_its_own_gst(): void
    {
        [$owner, $item] = $this->counter('RING02');

        $this->sell($owner, $item, 'separate', '56750');

        $sale = Sale::query()->latest('id')->firstOrFail();
        $this->assertSame('separate', $sale->making_mode);
        $this->assertSame('1750.00', (string) $sale->tax_amount);
        $this->assertSame('250.00', (string) $sale->making_tax_amount);
        $this->assertSame('56750.00', (string) $sale->total);
        $this->actingAs($owner)->get(route('sales.show', $sale))
            ->assertOk()
            ->assertSee('CGST 2.5% on making');
    }

    public function test_making_inside_jewellery_gst_is_the_old_way(): void
    {
        [$owner, $item] = $this->counter('RING03');

        $this->sell($owner, $item, 'inside', '56650');

        $sale = Sale::query()->latest('id')->firstOrFail();
        $this->assertSame('1650.00', (string) $sale->tax_amount);
        $this->assertSame('56650.00', (string) $sale->total);
    }

    private function sell(User $owner, Item $item, ?string $mode, string $pay): void
    {
        $walkIn = Customer::query()->where('code', 'WALKIN')->firstOrFail();

        $this->actingAs($owner)->post(route('sales.store'), array_filter([
            'customer_uuid' => $walkIn->uuid,
            'item_ids' => [$item->uuid],
            'discount' => '0',
            'making_mode' => $mode,
            'payments' => [['method' => 'cash', 'amount' => $pay]],
        ], fn ($value) => $value !== null))->assertRedirect()->assertSessionHasNoErrors();
        $this->seeShop($owner);
    }

    /**
     * @return array{0: User, 1: Item}
     */
    private function counter(string $code): array
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
        $this->seeShop($owner);

        return [$owner, $this->piece($owner, $gold, $purity, $code)];
    }

    private function piece(User $owner, MetalType $gold, Purity $purity, string $code): Item
    {
        $location = StockLocation::query()->where('code', 'MAIN')->firstOrFail();
        $making = ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Making)->where('code', 'fixed')->firstOrFail();
        $wastage = ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Wastage)->where('code', 'fixed')->firstOrFail();

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
            'making_value' => '5000',
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
