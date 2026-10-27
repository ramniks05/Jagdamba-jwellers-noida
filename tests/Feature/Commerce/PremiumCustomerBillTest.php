<?php

namespace Tests\Feature\Commerce;

use App\Models\Customer;
use App\Models\Society;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PremiumCustomerBillTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_bill_lists_premium_flag_for_the_gold_star_picker(): void
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
            'name' => 'Premium Ravi',
            'mobile' => '9876511111',
            'customer_type' => 'retail',
            'kyc_status' => 'pending',
            'society_id' => $society->uuid,
            'is_premium' => '1',
            'is_active' => '1',
        ])->assertRedirect();

        $this->actingAs($owner)->post(route('customers.store'), [
            'name' => 'Regular Meera',
            'mobile' => '9876522222',
            'customer_type' => 'retail',
            'kyc_status' => 'pending',
            'is_premium' => '0',
            'is_active' => '1',
        ])->assertRedirect();

        $premium = Customer::query()->where('mobile', '9876511111')->firstOrFail();
        $regular = Customer::query()->where('mobile', '9876522222')->firstOrFail();

        $html = $this->actingAs($owner)->get(route('sales.create'))->assertOk()->getContent();
        preg_match('/id="bill-config">(.*?)<\/script>/s', $html, $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $bill = json_decode(html_entity_decode($matches[1]), true, flags: JSON_THROW_ON_ERROR);

        $byUuid = collect($bill['customers'])->keyBy('uuid');
        $this->assertTrue($byUuid[$premium->uuid]['is_premium']);
        $this->assertFalse($byUuid[$regular->uuid]['is_premium']);
    }
}
