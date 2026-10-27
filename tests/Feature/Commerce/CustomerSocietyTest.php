<?php

namespace Tests\Feature\Commerce;

use App\Models\Customer;
use App\Models\Society;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerSocietyTest extends TestCase
{
    use RefreshDatabase;

    public function test_society_master_and_customer_filters(): void
    {
        $owner = $this->shopUser();

        $this->actingAs($owner)->post(route('societies.store'), [
            'name' => 'Green Valley',
            'code' => 'GV1',
            'sort_order' => 0,
            'is_active' => '1',
        ])->assertRedirect(route('societies.index'));

        $society = Society::query()->where('code', 'GV1')->firstOrFail();

        $this->actingAs($owner)->post(route('customers.store'), [
            'name' => 'Ravi Kumar',
            'mobile' => '9876512345',
            'customer_type' => 'retail',
            'kyc_status' => 'pending',
            'society_id' => $society->uuid,
            'is_premium' => '1',
            'is_active' => '1',
        ])->assertRedirect();

        $this->actingAs($owner)->post(route('customers.store'), [
            'name' => 'Plain Customer',
            'mobile' => '9876512346',
            'customer_type' => 'retail',
            'kyc_status' => 'pending',
            'is_premium' => '0',
            'is_active' => '1',
        ])->assertRedirect();

        $premium = Customer::query()->where('name', 'Ravi Kumar')->firstOrFail();
        $this->assertTrue($premium->is_premium);
        $this->assertSame($society->id, $premium->society_id);

        $this->actingAs($owner)
            ->get(route('customers.index', ['society' => $society->uuid]))
            ->assertOk()
            ->assertSee('Ravi Kumar')
            ->assertDontSee('Plain Customer');

        $this->actingAs($owner)
            ->get(route('customers.index', ['premium' => 'yes']))
            ->assertOk()
            ->assertSee('Ravi Kumar')
            ->assertDontSee('Plain Customer');
    }
}
