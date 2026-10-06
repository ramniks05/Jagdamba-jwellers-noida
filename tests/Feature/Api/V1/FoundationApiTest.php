<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class FoundationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_can_read_the_shop_and_cannot_see_another_shop(): void
    {
        $user = $this->shopUser();
        $branch = Branch::query()->firstOrFail();

        $this->getJson('/api/v1/company')->assertUnauthorized();

        $token = $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'phpunit',
        ])->assertOk()->json('token');

        $this->withToken($token)
            ->getJson('/api/v1/company')
            ->assertOk()
            ->assertJsonPath('data.code', 'JAGDAMBA')
            ->assertJsonPath('data.gstin', '27AAPFU0939F1ZV');

        $this->withToken($token)
            ->getJson('/api/v1/branches/'.$branch->uuid)
            ->assertOk()
            ->assertJsonPath('data.code', 'HO');

        $other = $this->shopUser([
            'name' => 'Other Jewellers',
            'code' => 'OTHER',
            'gstin' => '29ABCDE1234F1Z5',
            'pan' => 'ABCDE1234F',
            'email' => 'other@jagdamba.test',
        ], [
            'email' => 'owner-other@jagdamba.test',
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/branches/'.Branch::query()->where('company_id', $other->company_id)->firstOrFail()->uuid)
            ->assertNotFound();

        $this->withToken($token)->deleteJson('/api/v1/auth/token')->assertOk();
        $this->assertSame(0, PersonalAccessToken::query()->count());

        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/company')->assertUnauthorized();
    }
}
