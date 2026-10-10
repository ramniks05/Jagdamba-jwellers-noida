<?php

namespace Tests\Feature\Foundation;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\MetalRate;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\Sale;
use App\Models\StockLocation;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClearTrialDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_bills_go_and_the_shop_setup_stays(): void
    {
        $owner = $this->shopUser();
        $first = $this->bill($owner, '9876500001');
        $this->bill($owner, '9876500002');

        $this->artisan('shop:clear-trial-data')
            ->expectsQuestion('Type DELETE to remove this data for good', 'DELETE')
            ->assertSuccessful();

        app(CompanyContext::class)->set($owner->company);
        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(0, Item::query()->withTrashed()->count());
        $this->assertSame(0, MetalRate::query()->count());
        $this->assertSame(['WALKIN'], Customer::query()->withTrashed()->pluck('code')->all());
        $this->assertTrue(User::query()->whereKey($owner->id)->exists());
        $this->assertTrue(Branch::query()->exists());
        $this->assertTrue(StockLocation::query()->where('code', 'MAIN')->exists());
        $this->assertTrue(MetalType::query()->where('code', 'GOLD')->exists());

        $this->assertSame($first->number, $this->bill($owner, '9876500003')->number);
    }

    public function test_nothing_is_deleted_without_typing_delete(): void
    {
        $owner = $this->shopUser();
        $this->bill($owner, '9876500001');

        $this->artisan('shop:clear-trial-data')
            ->expectsQuestion('Type DELETE to remove this data for good', 'no')
            ->assertFailed();

        app(CompanyContext::class)->set($owner->company);
        $this->assertSame(1, Sale::query()->count());
    }

    private function bill(User $owner, string $mobile): Sale
    {
        app(CompanyContext::class)->set($owner->company);
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '22K')->firstOrFail();
        $location = StockLocation::query()->where('code', 'MAIN')->firstOrFail();

        $this->actingAs($owner)->post(route('rates.store'), [
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'rate_per_gram' => '6000',
            'source' => 'manual',
        ])->assertRedirect();

        $customer = $this->actingAs($owner)->postJson(route('customers.store'), [
            'name' => 'Meera Shah',
            'mobile' => $mobile,
            'customer_type' => 'retail',
            'kyc_status' => 'pending',
            'is_active' => true,
        ])->json();

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $customer['uuid'],
            'discount' => '0',
            'making_mode' => 'inside',
            'new_pieces' => [[
                'name' => 'Ring',
                'metal_uuid' => $gold->uuid,
                'purity_uuid' => $purity->uuid,
                'location_uuid' => $location->uuid,
                'gross_weight' => '10',
                'other_weight' => '0',
                'making_value' => '0',
                'wastage_value' => '0',
            ]],
            'payments' => [['method' => 'cash', 'amount' => '50000']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        app(CompanyContext::class)->set($owner->company);

        return Sale::query()->latest('id')->firstOrFail();
    }
}
