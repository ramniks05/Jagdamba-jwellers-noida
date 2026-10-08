<?php

namespace Tests\Feature\Commerce;

use App\Models\Customer;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\Sale;
use App\Models\StockLocation;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_bill_page_can_load_a_customers_details(): void
    {
        $owner = $this->shopUser();
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
            'mobile' => '9876500000',
            'customer_type' => 'retail',
            'kyc_status' => 'pending',
            'is_active' => true,
        ])->assertOk()->json();

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
        $sale = Sale::query()->latest('id')->firstOrFail();
        $record = Customer::query()->where('uuid', $customer['uuid'])->firstOrFail();

        $this->actingAs($owner)
            ->getJson(route('customers.show', $record))
            ->assertOk()
            ->assertJsonPath('name', 'Meera Shah')
            ->assertJsonPath('mobile', '9876500000')
            ->assertJsonPath('bills_count', 1)
            ->assertJsonPath('balance_sign', 1)
            ->assertJsonPath('recent.0.number', $sale->number)
            ->assertJsonPath('url', route('customers.show', $record));

        $this->actingAs($owner)->get(route('sales.create'))->assertOk()->assertSee('customer-details-modal', false);
    }
}
