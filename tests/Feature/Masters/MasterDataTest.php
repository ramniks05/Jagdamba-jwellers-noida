<?php

namespace Tests\Feature\Masters;

use App\Enums\ChargeAppliesTo;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ChargeMethod;
use App\Models\Item;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\Access\AccessProvisioner;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_shop_gets_categories_metals_stones_and_charge_methods(): void
    {
        $owner = $this->shopUser();

        $this->actingAs($owner)
            ->get(route('categories.index'))
            ->assertOk()
            ->assertSee('Ring');

        $this->actingAs($owner)
            ->get(route('metals.index'))
            ->assertOk()
            ->assertSee('Gold')
            ->assertSee('6 purities');

        $this->seeShop($owner);
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();

        $this->actingAs($owner)
            ->get(route('metals.purities.index', $gold))
            ->assertOk()
            ->assertSee('91.6');

        $this->actingAs($owner)
            ->get(route('stones.index'))
            ->assertOk()
            ->assertSee('Diamond');

        $this->actingAs($owner)
            ->get(route('charge-methods.index'))
            ->assertOk()
            ->assertSee('Per gram')
            ->assertSee('Percentage');
    }

    public function test_categories_cannot_loop_or_be_removed_while_a_child_exists(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $ring = Category::query()->where('code', 'RING')->firstOrFail();

        $this->actingAs($owner)->post(route('categories.store'), [
            'name' => 'Engagement',
            'code' => 'ENGAGE',
            'parent_uuid' => $ring->uuid,
            'sort_order' => 0,
            'is_active' => '1',
        ])->assertRedirect(route('categories.index'));

        $this->seeShop($owner);
        $child = Category::query()->where('code', 'ENGAGE')->firstOrFail();
        $this->assertSame($ring->id, $child->parent_id);

        $this->actingAs($owner)->put(route('categories.update', $ring), [
            'name' => 'Ring',
            'code' => 'RING',
            'parent_uuid' => $child->uuid,
            'sort_order' => 10,
            'is_active' => '1',
        ])->assertSessionHasErrors('parent_uuid');

        $this->actingAs($owner)
            ->from(route('categories.index'))
            ->delete(route('categories.destroy', $ring))
            ->assertRedirect(route('categories.index'))
            ->assertSessionHasErrors('record');
    }

    public function test_purity_is_stored_as_a_ratio_and_a_metal_with_purities_stays(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();

        $this->actingAs($owner)->post(route('metals.purities.store', $gold), [
            'name' => '23K',
            'code' => '23K',
            'fineness_percent' => '95.8',
            'sort_order' => 0,
            'is_active' => '1',
        ])->assertRedirect(route('metals.purities.index', $gold));

        $this->seeShop($owner);
        $purity = Purity::query()->where('code', '23K')->firstOrFail();
        $this->assertSame('0.958000', (string) $purity->fineness);

        $this->actingAs($owner)
            ->from(route('metals.index'))
            ->delete(route('metals.destroy', $gold))
            ->assertRedirect(route('metals.index'))
            ->assertSessionHasErrors('record');
    }

    public function test_charge_methods_can_be_renamed_and_hidden_but_not_deleted(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $method = ChargeMethod::query()
            ->where('applies_to', ChargeAppliesTo::Making)
            ->where('code', 'percentage')
            ->firstOrFail();

        $this->actingAs($owner)->put(route('charge-methods.update', $method), [
            'name' => 'Percent of value',
            'sort_order' => 20,
            'is_active' => '0',
        ])->assertRedirect(route('charge-methods.index'));

        $this->seeShop($owner);
        $method->refresh();
        $this->assertSame('percentage', $method->code);
        $this->assertSame('Percent of value', $method->name);
        $this->assertFalse($method->is_active);

        $this->actingAs($owner)
            ->delete(route('charge-methods.destroy', $method))
            ->assertForbidden();
    }

    public function test_cashier_can_view_masters_and_a_manager_cannot_delete_them(): void
    {
        $owner = $this->shopUser();
        $cashier = User::factory()->create([
            'company_id' => $owner->company_id,
            'email' => 'cashier@jagdamba.test',
        ]);
        $manager = User::factory()->create([
            'company_id' => $owner->company_id,
            'email' => 'manager@jagdamba.test',
        ]);
        $this->seeShop($owner);
        app(AccessProvisioner::class)->grant($cashier, 'cashier');
        app(AccessProvisioner::class)->grant($manager, 'manager');
        $ring = Category::query()->where('code', 'RING')->firstOrFail();

        $this->actingAs($cashier)
            ->get(route('categories.index'))
            ->assertOk()
            ->assertSee('Ring')
            ->assertDontSee('Add category');

        $this->actingAs($cashier)->post(route('categories.store'), [
            'name' => 'Bridal',
            'code' => 'BRIDAL',
            'parent_uuid' => null,
            'sort_order' => 0,
            'is_active' => '1',
        ])->assertForbidden();

        $this->actingAs($manager)
            ->delete(route('categories.destroy', $ring))
            ->assertForbidden();
    }

    public function test_master_lists_can_filter_by_active_and_block_removal_when_in_use(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $this->actingAs($owner)->post(route('brands.store'), [
            'name' => 'House brand',
            'code' => 'HOUSE',
            'sort_order' => 0,
            'is_active' => '1',
        ])->assertRedirect(route('brands.index'));

        $this->seeShop($owner);
        $brand = Brand::query()->where('code', 'HOUSE')->firstOrFail();
        $location = StockLocation::query()->where('code', 'MAIN')->firstOrFail();
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '22K')->firstOrFail();
        $branch = $owner->company->branches()->firstOrFail();

        $this->actingAs($owner)->put(route('brands.update', $brand), [
            'name' => $brand->name,
            'code' => $brand->code,
            'sort_order' => 0,
            'is_active' => '0',
        ])->assertRedirect(route('brands.index'));

        $this->actingAs($owner)
            ->get(route('brands.index', ['show' => 'hidden']))
            ->assertOk()
            ->assertSee('Hidden')
            ->assertSee($brand->name);

        $this->actingAs($owner)
            ->get(route('brands.index', ['show' => 'active']))
            ->assertOk()
            ->assertDontSee($brand->name);

        Item::query()->create([
            'company_id' => $owner->company_id,
            'branch_id' => $branch->id,
            'stock_location_id' => $location->id,
            'brand_id' => $brand->id,
            'metal_type_id' => $gold->id,
            'purity_id' => $purity->id,
            'item_code' => 'TSTBR1',
            'sku' => 'TSTBR1',
            'name' => 'Brand test ring',
            'status' => 'available',
            'gross_weight' => 10,
            'net_weight' => 10,
            'stone_weight' => 0,
            'other_weight' => 0,
        ]);

        $this->actingAs($owner)
            ->from(route('brands.index'))
            ->delete(route('brands.destroy', $brand))
            ->assertRedirect(route('brands.index'))
            ->assertSessionHasErrors('record');
    }

    public function test_another_shop_cannot_open_this_shops_category(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $ring = Category::query()->where('code', 'RING')->firstOrFail();

        $other = $this->shopUser([
            'name' => 'Other Jewellers',
            'code' => 'OTHER',
            'gstin' => '29ABCDE1234F1Z5',
            'pan' => 'ABCDE1234F',
            'email' => 'other@jagdamba.test',
        ], [
            'email' => 'owner-other@jagdamba.test',
        ]);

        $this->actingAs($other)
            ->get(route('categories.edit', $ring))
            ->assertNotFound();
    }

    private function seeShop(User $user): void
    {
        app(CompanyContext::class)->set($user->company);
    }
}
