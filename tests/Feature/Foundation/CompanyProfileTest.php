<?php

namespace Tests\Feature\Foundation;

use App\Enums\CompanyStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('company.edit'))->assertRedirect(route('login'));
    }

    public function test_owner_can_sign_in_and_update_the_shop_profile(): void
    {
        $user = $this->shopUser();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('overview'));

        $this->get(route('company.edit'))
            ->assertOk()
            ->assertSee('27AAPFU0939F1ZV')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->put(route('company.update'), $this->profile($user, [
            'name' => 'Jagdamba Gold',
            'phone' => '02211112222',
        ]))->assertRedirect(route('company.edit'));

        $this->assertSame('Jagdamba Gold', $user->company->fresh()->name);
        $this->assertSame('INR', $user->company->fresh()->currency_code);
    }

    public function test_invalid_gstin_and_logo_are_rejected(): void
    {
        Storage::fake('public');
        $user = $this->shopUser();

        $this->actingAs($user)
            ->put(route('company.update'), $this->profile($user, ['gstin' => 'NOT-A-GSTIN']))
            ->assertSessionHasErrors('gstin');

        $this->actingAs($user)
            ->put(route('company.update'), $this->profile($user, [
                'logo' => UploadedFile::fake()->create('logo.php', 20, 'application/x-php'),
            ]))
            ->assertSessionHasErrors('logo');

        $this->actingAs($user)
            ->put(route('company.update'), $this->profile($user, [
                'logo' => UploadedFile::fake()->image('logo.jpg', 80, 80),
            ]))
            ->assertRedirect(route('company.edit'));

        $this->assertNotNull($user->company->fresh()->logo_path);
        Storage::disk('public')->assertExists($user->company->fresh()->logo_path);
    }

    public function test_inactive_user_and_suspended_shop_cannot_sign_in(): void
    {
        $inactive = $this->shopUser(['code' => 'INACTIVE', 'gstin' => null, 'pan' => null], [
            'email' => 'inactive@jagdamba.test',
            'is_active' => false,
        ]);

        $this->post(route('login.store'), [
            'email' => $inactive->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $suspended = $this->shopUser(['code' => 'SUSPENDED', 'gstin' => '24ABCDE1234F1Z6', 'pan' => 'ABCDE1234F'], [
            'email' => 'suspended@jagdamba.test',
        ]);
        $suspended->company->update(['status' => CompanyStatus::Suspended]);

        $this->post(route('login.store'), [
            'email' => $suspended->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        $user = $this->shopUser();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function profile(User $user, array $overrides = []): array
    {
        $company = $user->company->fresh();

        return array_merge([
            'name' => $company->name,
            'legal_name' => $company->legal_name,
            'code' => $company->code,
            'email' => $company->email,
            'phone' => $company->phone,
            'mobile' => $company->mobile,
            'website' => $company->website,
            'gstin' => $company->gstin,
            'pan' => $company->pan,
            'address_line1' => $company->address_line1,
            'address_line2' => $company->address_line2,
            'city' => $company->city,
            'state' => $company->state,
            'postal_code' => $company->postal_code,
            'country' => $company->country,
            'timezone' => $company->timezone,
            'currency_code' => $company->currency_code,
            'fy_start_month' => $company->fy_start_month,
        ], $overrides);
    }
}
