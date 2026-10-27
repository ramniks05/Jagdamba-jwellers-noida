<?php

namespace Tests\Feature\Commerce;

use App\Models\StockTray;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrayDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tray_with_no_bills_can_be_removed(): void
    {
        $owner = $this->shopUser();

        $this->actingAs($owner)->post(route('trays.store'), [
            'name' => 'Empty tray',
            'code' => 'EMPTY',
            'grams' => '100',
            'pieces' => '10',
            'sort_order' => 0,
            'is_active' => '1',
        ])->assertRedirect(route('trays.index'));

        $tray = StockTray::query()->where('code', 'EMPTY')->firstOrFail();

        $this->actingAs($owner)
            ->from(route('trays.edit', $tray))
            ->delete(route('trays.destroy', $tray))
            ->assertRedirect(route('trays.index'));

        $this->assertSoftDeleted($tray);
    }
}
