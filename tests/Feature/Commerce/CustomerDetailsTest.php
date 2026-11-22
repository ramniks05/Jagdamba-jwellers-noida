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
            ->assertJsonPath('url', route('customers.show', $record))
            ->assertJsonPath('details_url', route('customers.details', $record))
            ->assertJsonPath('fields.name', 'Meera Shah');

        $this->actingAs($owner)->putJson(route('customers.details', $record), [
            'name' => 'Meera Shah Updated',
            'mobile' => '9876500000',
            'email' => 'meera@example.com',
            'customer_type' => 'retail',
            'kyc_status' => 'pending',
            'address_line1' => '12 Market Road',
            'address_line2' => 'Near temple',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'postal_code' => '400001',
            'pan' => 'ABCDE1234F',
            'notes' => 'Prefers evening pickup',
            'is_premium' => true,
            'is_active' => true,
        ])->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('customer.name', 'Meera Shah Updated')
            ->assertJsonPath('details.fields.city', 'Mumbai')
            ->assertJsonPath('details.fields.email', 'meera@example.com');

        $record->refresh();
        $this->assertSame('Meera Shah Updated', $record->name);
        $this->assertSame('12 Market Road', $record->address_line1);
        $this->assertSame('meera@example.com', $record->email);
        $this->assertSame('ABCDE1234F', $record->pan);
        $this->assertTrue((bool) $record->is_premium);
        $this->assertSame('Prefers evening pickup', $record->notes);
        $this->actingAs($owner)->get(route('sales.create'))->assertOk()->assertSee('customer-details-modal', false);
    }
}
