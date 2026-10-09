<?php

namespace Tests\Feature\Foundation;

use App\Models\DocumentSequence;
use App\Models\FinancialYear;
use App\Models\Role;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_settings_page_opens_in_the_shop_layout(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $sequence = DocumentSequence::query()->firstOrFail();
        $role = Role::query()->firstOrFail();

        $pages = [
            route('settings.edit') => 'Billing and GST',
            route('company.edit') => 'On the bill',
            route('branches.index') => 'Head office',
            route('branches.create') => 'Make this the head office',
            route('financial-years.index') => 'Today is in this year',
            route('financial-years.create') => 'Add financial year',
            route('document-sequences.index') => 'Starts again',
            route('document-sequences.edit', $sequence) => 'Next number will look like',
            route('users.index') => '(you)',
            route('users.create') => 'Can log in',
            route('roles.index') => 'Locked',
            route('roles.edit', $role) => 'permissions ticked',
            route('profile.edit') => 'Your role',
            route('suppliers.index') => 'No suppliers yet',
            route('suppliers.create') => 'value="SUP001"',
            route('locations.index') => 'Stock locations',
            route('locations.create') => 'Show in lists',
        ];

        foreach ($pages as $url => $text) {
            $this->actingAs($owner)->get($url)->assertOk()->assertSee($text, false);
            $this->seeShop($owner);
        }
    }

    public function test_settings_put_billing_first_and_keep_saving_every_value(): void
    {
        $owner = $this->shopUser();

        $this->actingAs($owner)->get(route('settings.edit'))
            ->assertOk()
            ->assertSeeInOrder(['Billing and GST', 'Printed bill', 'Money', 'Date and time'])
            ->assertSee('role="switch"', false)
            ->assertSee('₹ 12,34,567.50');
    }

    public function test_adding_a_year_suggests_the_one_after_the_latest(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $latest = FinancialYear::query()->orderByDesc('end_date')->firstOrFail();
        $next = $latest->end_date->copy()->addDay();

        $this->actingAs($owner)->get(route('financial-years.create'))
            ->assertOk()
            ->assertSee('value="'.$next->toDateString().'"', false)
            ->assertSee('value="'.$next->format('Y').'-'.$next->copy()->addYear()->subDay()->format('y').'"', false);
    }

    public function test_users_can_be_filtered_by_whether_they_can_log_in(): void
    {
        $owner = $this->shopUser();
        User::factory()->create(['company_id' => $owner->company_id, 'name' => 'Old Salesman', 'is_active' => false]);

        $this->actingAs($owner)->get(route('users.index', ['show' => 'inactive']))
            ->assertOk()
            ->assertSee('Old Salesman')
            ->assertDontSee('(you)');
        $this->seeShop($owner);
        $this->actingAs($owner)->get(route('users.index', ['show' => 'active']))
            ->assertOk()
            ->assertSee('(you)')
            ->assertDontSee('Old Salesman');
    }

    private function seeShop(User $user): void
    {
        app(CompanyContext::class)->set($user->company);
    }
}
