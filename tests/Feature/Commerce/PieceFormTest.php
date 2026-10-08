<?php

namespace Tests\Feature\Commerce;

use App\Models\Category;
use App\Models\ChargeMethod;
use App\Models\Item;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\StockLocation;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PieceFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_piece_starts_on_22k_with_the_next_code_and_can_be_followed_by_another(): void
    {
        $owner = $this->shopUser();
        [$gold, $purity, $location] = $this->basics($owner);
        $ring = Category::query()->where('code', 'RING')->firstOrFail();
        $perGram = ChargeMethod::query()->where('applies_to', 'making')->where('code', 'per_gram')->firstOrFail();

        $this->actingAs($owner)->get(route('items.create'))
            ->assertOk()
            ->assertSee('value="PC0001"', false)
            ->assertSee('value="'.$purity->uuid.'" data-id="'.$purity->id.'" data-metal="'.$gold->id.'" selected', false)
            ->assertSee('Price today');

        $this->actingAs($owner)->post(route('items.store'), [
            'name' => 'Gold Ring',
            'item_code' => 'PC0001',
            'category_uuid' => $ring->uuid,
            'huid' => 'ab12cd',
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'location_uuid' => $location->uuid,
            'gross_weight' => '5.25',
            'making_method_uuid' => $perGram->uuid,
            'making_value' => '800',
            'stones' => '',
            'next' => 'another',
        ])->assertSessionHasNoErrors();

        $this->seeShop($owner);
        $item = Item::query()->where('item_code', 'PC0001')->firstOrFail();
        $this->assertSame('AB12CD', $item->huid);
        $this->assertSame('5.250', (string) $item->net_weight);

        $this->actingAs($owner)->post(route('items.store'), [
            'name' => 'Gold Ring',
            'item_code' => 'PC0002',
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'location_uuid' => $location->uuid,
            'gross_weight' => '4',
            'next' => 'another',
        ])->assertRedirect(route('items.create', ['like' => Item::query()->where('item_code', 'PC0002')->value('uuid')]));

        $this->actingAs($owner)->get(route('items.create', ['like' => $item->uuid]))
            ->assertOk()
            ->assertSee('copied from', false)
            ->assertSee('value="PC0003"', false)
            ->assertSee('value="'.$ring->uuid.'" data-name="'.$ring->name.'" selected', false)
            ->assertSee('value="800"', false);
    }

    public function test_a_piece_page_prices_it_at_todays_rate_and_the_list_shows_stock_first(): void
    {
        $owner = $this->shopUser();
        [$gold, $purity, $location] = $this->basics($owner);
        $perGram = ChargeMethod::query()->where('applies_to', 'making')->where('code', 'per_gram')->firstOrFail();

        $this->actingAs($owner)->post(route('items.store'), [
            'name' => 'Diamond ring',
            'item_code' => 'PC0001',
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'location_uuid' => $location->uuid,
            'gross_weight' => '6',
            'making_method_uuid' => $perGram->uuid,
            'making_value' => '500',
            'stones' => [['name' => 'Diamond', 'weight' => '1', 'rate_unit' => 'fixed', 'value' => '2000']],
        ])->assertSessionHasNoErrors();

        $this->seeShop($owner);
        $item = Item::query()->where('item_code', 'PC0001')->firstOrFail();

        $this->actingAs($owner)->get(route('items.show', $item))
            ->assertOk()
            ->assertSee('Sell this piece')
            ->assertSee('Less Diamond')
            ->assertSee('29,500.00');

        $this->actingAs($owner)->put(route('items.update', $item), [
            'name' => 'Plain ring',
            'item_code' => 'PC0001',
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'location_uuid' => $location->uuid,
            'gross_weight' => '6',
            'stones' => '',
        ])->assertSessionHasNoErrors();

        $this->seeShop($owner);
        $item->refresh();
        $this->assertSame(0, $item->stones()->count());
        $this->assertSame('6.000', (string) $item->net_weight);

        $this->actingAs($owner)->get(route('items.index'))->assertOk()->assertSee('PC0001')->assertSee('In stock');
        $this->actingAs($owner)->get(route('items.index', ['status' => 'sold']))->assertOk()->assertDontSee('Plain ring');
    }

    /**
     * @return array{0: MetalType, 1: Purity, 2: StockLocation}
     */
    private function basics(User $owner): array
    {
        $this->seeShop($owner);
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('metal_type_id', $gold->id)->where('code', '22K')->firstOrFail();
        $location = StockLocation::query()->where('code', 'MAIN')->firstOrFail();
        $this->actingAs($owner)->post(route('rates.store'), [
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'rate_per_gram' => '5000',
            'source' => 'manual',
        ])->assertRedirect();
        $this->seeShop($owner);

        return [$gold, $purity, $location];
    }

    private function seeShop(User $user): void
    {
        app(CompanyContext::class)->set($user->company);
    }
}
