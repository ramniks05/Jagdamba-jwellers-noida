<?php

namespace Tests\Feature\Commerce;

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

class StoneRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_stone_value_comes_from_its_rate_per_carat_or_gram(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '22K')->firstOrFail();
        $location = StockLocation::query()->where('code', 'MAIN')->firstOrFail();
        $walkIn = Customer::query()->where('code', 'WALKIN')->firstOrFail();
        $this->actingAs($owner)->post(route('rates.store'), [
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'rate_per_gram' => '6000',
            'source' => 'manual',
        ])->assertRedirect();

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $walkIn->uuid,
            'discount' => '0',
            'making_mode' => 'inside',
            'new_pieces' => [[
                'name' => 'Ring',
                'metal_uuid' => $gold->uuid,
                'purity_uuid' => $purity->uuid,
                'location_uuid' => $location->uuid,
                'gross_weight' => '12.4',
                'other_weight' => '0',
                'making_value' => '0',
                'wastage_value' => '0',
                'stones' => [
                    ['name' => 'Diamond', 'weight' => '0.4', 'rate' => '25000', 'rate_unit' => 'carat', 'value' => '1'],
                    ['name' => 'Kundan', 'weight' => '2', 'rate' => '1500', 'rate_unit' => 'gram', 'value' => '1'],
                    ['name' => 'Pearl', 'weight' => '0', 'rate_unit' => 'fixed', 'value' => '700'],
                ],
            ]],
            'payments' => [['method' => 'cash', 'amount' => '117111']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->seeShop($owner);
        $sale = Sale::query()->latest('id')->firstOrFail();
        $line = $sale->lines()->with('stones')->sole();
        $stones = $line->stones->keyBy('name');

        $this->assertSame('10.000', (string) $line->net_weight);
        $this->assertSame('53700.00', (string) $line->stone_amount);
        $this->assertSame('50000.00', (string) $stones['Diamond']->value);
        $this->assertSame('25000.00', (string) $stones['Diamond']->rate);
        $this->assertSame('carat', $stones['Diamond']->rate_unit);
        $this->assertSame('3000.00', (string) $stones['Kundan']->value);
        $this->assertSame('gram', $stones['Kundan']->rate_unit);
        $this->assertSame('700.00', (string) $stones['Pearl']->value);
        $this->assertNull($stones['Pearl']->rate);
        $this->assertSame('117111.00', (string) $sale->total);

        $this->actingAs($owner)
            ->get(route('sales.show', $sale))
            ->assertOk()
            ->assertSee('2.00 ct')
            ->assertSee('25,000.00/ct')
            ->assertSee('1,500.00/g')
            ->assertSee('50,000.00');
    }

    public function test_a_stock_piece_keeps_its_stone_rate(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '22K')->firstOrFail();
        $location = StockLocation::query()->where('code', 'MAIN')->firstOrFail();

        $this->actingAs($owner)->post(route('items.store'), [
            'name' => 'Diamond ring',
            'item_code' => 'RINGC1',
            'sku' => 'RINGC1',
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'location_uuid' => $location->uuid,
            'gross_weight' => '10',
            'other_weight' => '0',
            'making_value' => '0',
            'wastage_value' => '0',
            'cost_price' => '0',
            'selling_price' => '0',
            'mrp' => '0',
            'stones' => [
                ['name' => 'Diamond', 'weight' => '0.2', 'rate' => '40000', 'rate_unit' => 'carat', 'value' => '0'],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->seeShop($owner);
        $item = Item::query()->where('item_code', 'RINGC1')->firstOrFail();
        $this->assertSame('40000.00', (string) $item->stone_value);
        $this->assertSame('9.800', (string) $item->net_weight);
        $this->assertSame('carat', $item->stones()->sole()->rate_unit);

        $this->actingAs($owner)->get(route('items.show', $item))->assertOk()->assertSee('1.00 ct');
    }

    private function seeShop(User $user): void
    {
        app(CompanyContext::class)->set($user->company);
    }
}
