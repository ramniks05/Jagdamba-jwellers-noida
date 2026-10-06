<?php

namespace Tests\Feature\Access;

use App\Events\Access\UserAccessChanged;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Services\Access\AccessProvisioner;
use App\Services\Foundation\BranchService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_add_a_cashier_and_the_cashier_cannot_open_settings(): void
    {
        Event::fake([UserAccessChanged::class]);
        $owner = $this->shopUser();
        $cashierRole = Role::query()->where('code', 'cashier')->firstOrFail();

        $this->actingAs($owner)->post(route('users.store'), [
            'name' => 'Counter',
            'email' => 'cashier@jagdamba.test',
            'password' => 'Cashier@123',
            'password_confirmation' => 'Cashier@123',
            'is_active' => '1',
            'roles' => [$cashierRole->uuid],
            'branches' => [],
        ])->assertRedirect(route('users.index'));

        Event::assertDispatched(UserAccessChanged::class, fn (UserAccessChanged $event) => $event->action === 'created');

        $cashier = User::query()->where('email', 'cashier@jagdamba.test')->firstOrFail();

        $this->actingAs($cashier)
            ->get(route('settings.edit'))
            ->assertForbidden();

        $this->actingAs($cashier)
            ->get(route('overview'))
            ->assertOk()
            ->assertSee('Cashier');

        $this->actingAs($owner)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('cashier@jagdamba.test');
    }

    public function test_branch_limit_hides_other_branches_from_a_manager(): void
    {
        $owner = $this->shopUser();
        $headOffice = Branch::query()->where('is_head_office', true)->firstOrFail();
        $pune = app(BranchService::class)->create($owner->company, [
            'name' => 'Pune',
            'code' => 'PUNE',
            'status' => 'active',
            'address_line1' => '2 Camp',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'postal_code' => '411001',
            'country' => 'India',
            'is_head_office' => false,
        ]);
        $manager = User::factory()->create([
            'company_id' => $owner->company_id,
            'email' => 'manager@jagdamba.test',
        ]);
        app(AccessProvisioner::class)->grant($manager, 'manager');
        $manager->branches()->sync([$headOffice->id]);

        $this->actingAs($manager)
            ->get(route('branches.edit', $headOffice))
            ->assertOk();

        $this->actingAs($manager)
            ->get(route('branches.edit', $pune))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('branches.index'))
            ->assertOk()
            ->assertSee('Head Office')
            ->assertDontSee('Pune');
    }

    public function test_the_shop_keeps_an_owner_and_locked_roles_stay_locked(): void
    {
        $owner = $this->shopUser();
        $cashierRole = Role::query()->where('code', 'cashier')->firstOrFail();
        $ownerRole = Role::query()->where('code', 'owner')->firstOrFail();

        $this->actingAs($owner)->put(route('users.update', $owner), [
            'name' => $owner->name,
            'email' => $owner->email,
            'is_active' => '1',
            'roles' => [$cashierRole->uuid],
        ])->assertSessionHasErrors('roles');

        $this->actingAs($owner)->put(route('users.update', $owner), [
            'name' => $owner->name,
            'email' => $owner->email,
            'is_active' => '0',
            'roles' => [$ownerRole->uuid],
        ])->assertSessionHasErrors('is_active');

        $this->actingAs($owner)
            ->delete(route('users.destroy', $owner))
            ->assertForbidden();

        $this->actingAs($owner)
            ->delete(route('roles.destroy', $ownerRole))
            ->assertForbidden();

        $this->actingAs($owner)->post(route('roles.store'), [
            'name' => 'Floor Helper',
            'description' => 'Opens the shop profile only.',
            'permissions' => ['company.view'],
        ])->assertRedirect(route('roles.index'));

        $helper = Role::query()->where('code', 'floor_helper')->firstOrFail();

        $this->actingAs($owner)
            ->delete(route('roles.destroy', $helper))
            ->assertRedirect(route('roles.index'));
    }

    public function test_a_manager_cannot_assign_the_owner_role(): void
    {
        $owner = $this->shopUser();
        $manager = User::factory()->create([
            'company_id' => $owner->company_id,
            'email' => 'manager@jagdamba.test',
        ]);
        app(AccessProvisioner::class)->grant($manager, 'manager');
        $ownerRole = Role::query()->where('code', 'owner')->firstOrFail();

        $this->actingAs($manager)->post(route('users.store'), [
            'name' => 'Second Owner',
            'email' => 'second@jagdamba.test',
            'password' => 'Second@1234',
            'password_confirmation' => 'Second@1234',
            'is_active' => '1',
            'roles' => [$ownerRole->uuid],
        ])->assertSessionHasErrors('roles');

        $this->assertNull(User::query()->where('email', 'second@jagdamba.test')->first());
    }

    public function test_password_can_be_changed_and_reset_without_revealing_unknown_emails(): void
    {
        Notification::fake();
        $owner = $this->shopUser();

        $this->actingAs($owner)->put(route('profile.password'), [
            'current_password' => 'wrong-password',
            'password' => 'NewPass@123',
            'password_confirmation' => 'NewPass@123',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($owner)->put(route('profile.password'), [
            'current_password' => 'password',
            'password' => 'NewPass@123',
            'password_confirmation' => 'NewPass@123',
        ])->assertRedirect(route('profile.edit'));

        $this->assertTrue(Hash::check('NewPass@123', $owner->fresh()->password));

        $this->post(route('logout'));
        $this->post(route('login.store'), [
            'email' => $owner->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->post(route('password.email'), [
            'email' => 'missing@jagdamba.test',
        ])->assertSessionHas('status');
        Notification::assertNothingSent();

        $this->post(route('password.email'), [
            'email' => $owner->email,
        ])->assertSessionHas('status');

        $token = null;
        Notification::assertSentTo($owner, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $owner->email,
            'password' => 'ResetPass@123',
            'password_confirmation' => 'ResetPass@123',
        ])->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'email' => $owner->email,
            'password' => 'ResetPass@123',
        ])->assertRedirect(route('overview'));
    }

    public function test_users_from_another_shop_are_not_visible(): void
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

        $this->actingAs($second)
            ->get(route('users.edit', $first))
            ->assertNotFound();
    }
}
