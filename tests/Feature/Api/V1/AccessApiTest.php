<?php

namespace Tests\Feature\Api\V1;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccessApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_user_can_manage_staff_and_a_cashier_cannot(): void
    {
        $owner = $this->shopUser();
        $cashierRole = Role::query()->where('code', 'cashier')->firstOrFail();

        $this->postJson('/api/v1/auth/token', [
            'email' => $owner->email,
            'password' => 'password',
            'device_name' => 'phpunit',
        ])->assertOk();

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.email', $owner->email)
            ->assertJsonPath('data.roles.0.code', 'owner');

        $this->postJson('/api/v1/users', [
            'name' => 'Counter',
            'email' => 'cashier@jagdamba.test',
            'password' => 'Cashier@123',
            'password_confirmation' => 'Cashier@123',
            'is_active' => true,
            'roles' => [$cashierRole->uuid],
            'branches' => [],
        ])->assertCreated()->assertJsonPath('data.roles.0.code', 'cashier');

        $cashier = User::query()->where('email', 'cashier@jagdamba.test')->firstOrFail();
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($cashier);

        $this->putJson('/api/v1/settings', [
            'settings' => [],
        ])->assertForbidden();

        $this->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_another_shops_user_is_not_found(): void
    {
        $first = $this->shopUser();
        $second = $this->shopUser([
            'name' => 'Other Jewellers',
            'code' => 'OTHER',
            'gstin' => '29ABCDE1234F1Z5',
            'pan' => 'ABCDE1234F',
            'email' => 'other@jagdamba.test',
        ], [
            'email' => 'owner-other@jagdamba.test',
        ]);

        Sanctum::actingAs($first);

        $this->getJson('/api/v1/users/'.$second->uuid)->assertNotFound();
    }
}
