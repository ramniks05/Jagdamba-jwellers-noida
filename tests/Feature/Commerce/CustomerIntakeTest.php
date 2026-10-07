<?php

namespace Tests\Feature\Commerce;

use App\Enums\IntakeStatus;
use App\Models\Customer;
use App\Models\CustomerIntake;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerIntakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_form_waits_for_approval_and_a_known_mobile_does_not(): void
    {
        $owner = $this->shopUser();
        $company = $owner->company;
        $this->seeShop($owner);

        $this->actingAs($owner)->get(route('customers.qr'))->assertOk()->assertSee('Customer form');

        $this->post(route('customer-form.store', $company), [
            'intent' => 'new',
            'name' => 'Meera Shah',
            'mobile' => '9876543210',
            'address_line1' => '12 Market Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'postal_code' => '400001',
        ])->assertRedirect(route('customer-form.create', $company))->assertSessionHas('form_result', 'submitted');

        $this->seeShop($owner);
        $intake = CustomerIntake::query()->where('mobile_key', '9876543210')->firstOrFail();
        $this->assertSame(IntakeStatus::Pending, $intake->status);
        $this->assertNull(Customer::query()->where('mobile', '9876543210')->first());

        $this->actingAs($owner)->post(route('customer-intakes.approve', $intake))->assertRedirect();
        $this->seeShop($owner);
        $customer = Customer::query()->where('mobile', '9876543210')->firstOrFail();
        $intake->refresh();
        $this->assertSame(IntakeStatus::Approved, $intake->status);
        $this->assertSame($customer->id, $intake->customer_id);

        $this->post(route('customer-form.store', $company), [
            'intent' => 'known',
            'mobile' => '+91 9876543210',
        ])->assertRedirect()->assertSessionHas('form_result', 'known');

        $this->seeShop($owner);
        $this->assertSame(1, Customer::query()->where('name', 'Meera Shah')->count());
        $this->assertSame(1, CustomerIntake::query()->count());
    }

    public function test_an_unknown_mobile_cannot_use_the_existing_customer_option(): void
    {
        $owner = $this->shopUser();

        $this->from(route('customer-form.create', $owner->company))
            ->post(route('customer-form.store', $owner->company), [
                'intent' => 'known',
                'mobile' => '9876543210',
            ])->assertRedirect(route('customer-form.create', $owner->company))
            ->assertSessionHasErrors('mobile');
    }

    private function seeShop(User $user): void
    {
        app(CompanyContext::class)->set($user->company);
    }
}
