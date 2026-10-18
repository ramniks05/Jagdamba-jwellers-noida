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

    public function test_social_links_are_saved_and_a_bad_link_is_rejected(): void
    {
        $user = $this->shopUser();

        $this->actingAs($user)
            ->get(route('company.edit'))
            ->assertOk()
            ->assertSee('Facebook')
            ->assertSee('Instagram')
            ->assertSee('YouTube');

        $this->actingAs($user)
            ->put(route('company.update'), $this->profile($user, [
                'website' => 'https://jagdamba.example',
                'facebook' => 'https://facebook.com/jagdamba',
                'instagram' => 'https://instagram.com/jagdamba',
                'youtube' => 'https://youtube.com/@jagdamba',
            ]))
            ->assertRedirect(route('company.edit'))
            ->assertSessionHasNoErrors();

        $company = $user->company->fresh();
        $this->assertSame('https://facebook.com/jagdamba', $company->facebook);
        $this->assertSame('https://instagram.com/jagdamba', $company->instagram);
        $this->assertSame('https://youtube.com/@jagdamba', $company->youtube);

        $this->actingAs($user)
            ->put(route('company.update'), $this->profile($user, ['facebook' => 'not-a-link']))
            ->assertSessionHasErrors('facebook');
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

        $this->actingAs($user)
            ->put(route('company.update'), $this->profile($user, [
                'signature' => $this->signaturePhoto(),
            ]))
            ->assertRedirect(route('company.edit'))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($user->company->fresh()->signature_path);
        Storage::disk('public')->assertExists($user->company->fresh()->signature_path);
    }

    public function test_a_bad_signature_stays_off_the_profile_and_the_reason_is_shown(): void
    {
        Storage::fake('public');
        $user = $this->shopUser();

        $this->actingAs($user)
            ->put(route('company.update'), $this->profile($user, [
                'signature' => UploadedFile::fake()->create('notes.txt', 20, 'text/plain'),
            ]))
            ->assertSessionHasErrors([
                'signature' => 'The signature must be a JPEG, PNG, or WebP photo.',
            ]);

        $this->followingRedirects()
            ->actingAs($user)
            ->from(route('company.edit'))
            ->put(route('company.update'), $this->profile($user, [
                'signature' => $this->signaturePhoto(false),
            ]))
            ->assertOk()
            ->assertSee('No signature was found in that photo, so it was not saved. Sign on white paper with a dark pen and take the photo again.');

        $this->assertNull($user->company->fresh()->signature_path);
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

    public function test_a_saved_logo_and_signature_open_without_the_public_storage_link(): void
    {
        Storage::fake('public');
        $user = $this->shopUser();

        $this->actingAs($user)
            ->put(route('company.update'), $this->profile($user, [
                'logo' => UploadedFile::fake()->image('logo.jpg', 80, 80),
                'signature' => $this->signaturePhoto(),
            ]))
            ->assertRedirect(route('company.edit'))
            ->assertSessionHasNoErrors();

        $company = $user->company->fresh();
        $this->assertNotNull($company->logo_path);
        $this->assertNotNull($company->signature_path);

        $this->actingAs($user)
            ->get(route('company.edit'))
            ->assertOk()
            ->assertSee('/shop-files/'.$company->logo_path, false)
            ->assertSee('/shop-files/'.$company->signature_path, false);

        $this->get('/shop-files/'.$company->logo_path)
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');
        $this->get('/shop-files/'.$company->signature_path)
            ->assertOk()
            ->assertHeader('content-type', 'image/png');

        $this->get('/shop-files/../.env')->assertNotFound();
        $this->get('/shop-files/companies/'.$company->uuid.'/notes.txt')->assertNotFound();
    }

    private function signaturePhoto(bool $withInk = true): UploadedFile
    {
        $image = imagecreatetruecolor(240, 80);
        $paper = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $paper);

        if ($withInk) {
            $ink = imagecolorallocate($image, 20, 16, 18);
            imagesetthickness($image, 4);
            imageline($image, 20, 50, 210, 28, $ink);
        }

        ob_start();
        imagepng($image);
        imagedestroy($image);
        $path = tempnam(sys_get_temp_dir(), 'sign');
        file_put_contents($path, ob_get_clean());

        return new UploadedFile($path, 'sign.png', 'image/png', null, true);
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
            'facebook' => $company->facebook,
            'instagram' => $company->instagram,
            'youtube' => $company->youtube,
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
