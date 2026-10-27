<?php

namespace Tests\Feature\Commerce;

use App\Models\MetalType;
use App\Models\Purity;
use App\Models\StockLocation;
use App\Models\StockTray;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrayStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_bill_from_a_tray_reduces_tray_weight_and_shows_on_the_report(): void
    {
        $owner = $this->shopUser();
        app(CompanyContext::class)->set($owner->company);
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '22K')->firstOrFail();
        $location = StockLocation::query()->where('code', 'MAIN')->firstOrFail();
        $buyer = $this->counterCustomer($owner);

        $this->actingAs($owner)->post(route('trays.store'), [
            'name' => 'Silver payal tray',
            'code' => 'SPAY',
            'grams' => '1000',
            'pieces' => '100',
            'sort_order' => 0,
            'is_active' => '1',
        ])->assertRedirect(route('trays.index'));

        $tray = StockTray::query()->where('code', 'SPAY')->firstOrFail();

        $this->actingAs($owner)->post(route('rates.store'), [
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'rate_per_gram' => '6000',
            'source' => 'manual',
        ])->assertRedirect();

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $buyer->uuid,
            'tray_uuid' => $tray->uuid,
            'discount' => '0',
            'making_mode' => 'inside',
            'new_pieces' => [[
                'name' => 'Payal',
                'metal_uuid' => $gold->uuid,
                'purity_uuid' => $purity->uuid,
                'location_uuid' => $location->uuid,
                'gross_weight' => '10',
                'other_weight' => '0',
                'making_value' => '0',
                'wastage_value' => '0',
            ]],
            'payments' => [['method' => 'cash', 'amount' => '70000']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $tray->refresh();
        $this->assertSame('990.000', (string) $tray->grams);
        $this->assertSame(99, (int) $tray->pieces);

        $this->actingAs($owner)
            ->get(route('reports.trays', ['tray' => $tray->uuid]))
            ->assertOk()
            ->assertSee('Silver payal tray')
            ->assertSee('990')
            ->assertSee('99');
    }
}
