<?php

namespace Tests\Feature\Commerce;

use App\Models\MetalRate;
use App\Models\Purity;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_can_keep_or_change_the_market_price(): void
    {
        Cache::flush();
        Http::fake([
            '*' => Http::response([
                'statusCode' => 200,
                'data' => [
                    'gold' => ['buy' => 15262.44, 'currency' => 'INR', 'unit' => 'gram'],
                    'silver' => ['buy' => 228.28, 'currency' => 'INR', 'unit' => 'gram'],
                    'timestamp' => '2026-10-07T13:55:00Z',
                ],
            ]),
        ]);

        $owner = $this->shopUser();

        $this->actingAs($owner)->get(route('rates.index'))
            ->assertOk()
            ->assertSee('Gold and silver today')
            ->assertSee('999 fine')
            ->assertSee('value="15262.44"', false)
            ->assertSee('value="13994.39"', false);

        app(CompanyContext::class)->set($owner->company);
        $purity22 = Purity::query()->where('code', '22K')->firstOrFail();
        $purity24 = Purity::query()->where('code', '24K')->firstOrFail();

        $this->actingAs($owner)->post(route('rates.market.store'), [
            'lines' => [
                ['use' => '1', 'purity_uuid' => $purity22->uuid, 'rate_per_gram' => '14000'],
                ['purity_uuid' => $purity24->uuid, 'rate_per_gram' => '15262.44'],
            ],
        ])->assertRedirect(route('rates.index'));

        app(CompanyContext::class)->set($owner->company);
        $saved = MetalRate::query()->get();
        $this->assertCount(1, $saved);
        $this->assertSame($purity22->id, $saved->first()->purity_id);
        $this->assertSame('14000.00', (string) $saved->first()->rate_per_gram);
        $this->assertSame('market', $saved->first()->source);
    }

    public function test_the_rate_page_stays_open_when_the_market_feed_is_down(): void
    {
        Cache::flush();
        Http::fake([
            '*' => Http::response([], 503),
        ]);

        $owner = $this->shopUser();

        $this->actingAs($owner)->get(route('rates.index'))
            ->assertOk()
            ->assertSee('could not be fetched')
            ->assertSee('Save rate');
    }

    public function test_the_page_shows_only_the_last_rate_and_history_can_be_filtered(): void
    {
        Cache::flush();
        Http::fake(['*' => Http::response([], 503)]);
        $owner = $this->shopUser();
        app(CompanyContext::class)->set($owner->company);
        $purity22 = Purity::query()->where('code', '22K')->firstOrFail();
        $purity24 = Purity::query()->where('code', '24K')->firstOrFail();
        $save = fn (Purity $purity, string $rate, string $at) => $this->actingAs($owner)->post(route('rates.store'), [
            'metal_uuid' => $purity->metalType->uuid,
            'purity_uuid' => $purity->uuid,
            'rate_per_gram' => $rate,
            'effective_at' => $at,
            'source' => 'manual',
        ])->assertSessionHasNoErrors();

        $save($purity22, '6100', '2026-10-04T10:00');
        $save($purity22, '6200', '2026-10-05T10:00');
        $save($purity22, '6300', '2026-10-06T09:00');
        $save($purity24, '7400', '2026-10-05T11:00');

        $current = $this->actingAs($owner)->get(route('rates.index'))->assertOk()->viewData('current');
        $this->assertSame(['6300.00', '7400.00'], $current->map(fn (MetalRate $rate) => (string) $rate->rate_per_gram)->sort()->values()->all());
        $this->actingAs($owner)->get(route('rates.index'))->assertSee('6,300.00')->assertDontSee('6,100.00')->assertDontSee('6,200.00');

        $this->actingAs($owner)->get(route('rates.index', ['view' => 'history']))->assertOk()->assertSee('6,100.00')->assertSee('6,200.00')->assertSee('7,400.00');
        $this->actingAs($owner)->get(route('rates.index', ['view' => 'history', 'purity' => $purity24->uuid]))
            ->assertOk()->assertSee('7,400.00')->assertDontSee('6,100.00');
        $this->actingAs($owner)->get(route('rates.index', ['view' => 'history', 'from' => '2026-10-05', 'to' => '2026-10-05']))
            ->assertOk()->assertSee('6,200.00')->assertSee('7,400.00')->assertDontSee('6,100.00')->assertDontSee('6,300.00');
    }
}
