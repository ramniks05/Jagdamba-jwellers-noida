<?php

namespace Tests\Feature\Commerce;

use App\Enums\IntakePurpose;
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
            'dob' => '1990-05-12',
            'anniversary' => '2015-11-02',
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
        $this->assertSame('1990-05-12', $customer->dob?->toDateString());
        $this->assertSame('2015-11-02', $customer->anniversary?->toDateString());
        $this->actingAs($owner)->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('12 Market Road')
            ->assertSee('Mumbai')
            ->assertSee('12 May 1990')
            ->assertSee('02 Nov 2015');

        $this->post(route('customer-form.store', $company), [
            'intent' => 'known',
            'mobile' => '+91 9876543210',
        ])->assertRedirect()->assertSessionHas('form_result', 'known');

        $this->seeShop($owner);
        $this->assertSame(1, Customer::query()->where('name', 'Meera Shah')->count());
        $this->assertSame(1, CustomerIntake::query()->count());
    }

    public function test_a_known_customer_can_keep_details_or_send_a_change_for_approval(): void
    {
        $owner = $this->shopUser();
        $company = $owner->company;
        $this->seeShop($owner);

        $this->post(route('customer-form.store', $company), [
            'intent' => 'new',
            'name' => 'Meera Shah',
            'mobile' => '9876543210',
            'address_line1' => '12 Market Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'postal_code' => '400001',
        ]);
        $this->seeShop($owner);
        $this->actingAs($owner)->post(route('customer-intakes.approve', CustomerIntake::query()->firstOrFail()));

        $this->post(route('customer-form.store', $company), [
            'intent' => 'known',
            'mobile' => '9876543210',
        ])->assertSessionHas('form_result', 'known');

        $this->get(route('customer-form.create', $company))
            ->assertOk()
            ->assertSee('Meera Shah')
            ->assertSee('12 Market Road')
            ->assertSee('These are correct')
            ->assertSee('Send changes')
            ->assertSee('Date of birth')
            ->assertDontSee('New customer')
            ->assertDontSee('Already a customer');

        $this->post(route('customer-form.store', $company), [
            'intent' => 'confirm',
            'mobile' => '9876543210',
        ])->assertSessionHas('form_result', 'confirmed');

        $this->seeShop($owner);
        $this->assertSame(1, CustomerIntake::query()->count());
        $this->assertSame('12 Market Road', Customer::query()->where('mobile', '9876543210')->firstOrFail()->address_line1);

        $this->post(route('customer-form.store', $company), [
            'intent' => 'update',
            'name' => 'Meera Shah',
            'mobile' => '9876543210',
            'address_line1' => '44 New Lane',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'postal_code' => '400002',
        ])->assertSessionHas('form_result', 'updated');

        $this->seeShop($owner);
        $customer = Customer::query()->where('mobile', '9876543210')->firstOrFail();
        $this->assertSame('12 Market Road', $customer->address_line1);
        $change = CustomerIntake::query()->where('status', IntakeStatus::Pending)->firstOrFail();
        $this->assertSame(IntakePurpose::Update, $change->purpose);
        $this->assertSame($customer->id, $change->customer_id);

        $this->actingAs($owner)->post(route('customer-intakes.approve', $change))->assertRedirect();
        $this->seeShop($owner);
        $customer->refresh();
        $this->assertSame('44 New Lane', $customer->address_line1);
        $this->assertSame('400002', $customer->postal_code);
        $this->assertSame(1, Customer::query()->where('name', 'Meera Shah')->count());
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
