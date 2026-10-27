<?php

namespace Tests\Feature\Commerce;

use App\Models\Item;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\StockLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unused_piece_can_be_removed_but_not_after_a_bill(): void
    {
        $owner = $this->shopUser();
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '22K')->firstOrFail();
        $location = StockLocation::query()->where('code', 'MAIN')->firstOrFail();
        $buyer = $this->counterCustomer($owner);

        $this->actingAs($owner)->post(route('items.store'), [
            'name' => 'Test ring',
            'item_code' => 'PC9999',
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'location_uuid' => $location->uuid,
            'gross_weight' => '5',
            'other_weight' => '0',
            'making_value' => '0',
            'wastage_value' => '0',
        ])->assertRedirect();

        $item = Item::query()->where('item_code', 'PC9999')->firstOrFail();

        $this->actingAs($owner)
            ->from(route('items.show', $item))
            ->delete(route('items.destroy', $item))
            ->assertRedirect(route('items.index'));

        $this->assertSoftDeleted($item);

        $this->actingAs($owner)->post(route('items.store'), [
            'name' => 'Sold ring',
            'item_code' => 'PC9998',
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'location_uuid' => $location->uuid,
            'gross_weight' => '5',
            'other_weight' => '0',
            'making_value' => '0',
            'wastage_value' => '0',
        ])->assertRedirect();

        $sold = Item::query()->where('item_code', 'PC9998')->firstOrFail();

        $this->actingAs($owner)->post(route('rates.store'), [
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'rate_per_gram' => '6000',
            'source' => 'manual',
        ])->assertRedirect();

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $buyer->uuid,
            'discount' => '0',
            'making_mode' => 'inside',
            'item_ids' => [$sold->uuid],
            'payments' => [['method' => 'cash', 'amount' => '50000']],
        ])->assertRedirect();

        $this->actingAs($owner)
            ->from(route('items.show', $sold))
            ->delete(route('items.destroy', $sold))
            ->assertSessionHasErrors('record');
    }
}
