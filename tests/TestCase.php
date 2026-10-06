<?php

namespace Tests;

use App\Models\User;
use App\Services\Access\AccessProvisioner;
use App\Services\Foundation\ShopProvisioner;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * @param  array<string, mixed>  $shop
     * @param  array<string, mixed>  $user
     */
    protected function shopUser(array $shop = [], array $user = [], string $role = 'owner'): User
    {
        $this->travelTo('2026-10-06 10:00:00');

        $company = app(ShopProvisioner::class)->provision(array_merge([
            'name' => 'Jagdamba Jewellers',
            'legal_name' => 'Jagdamba Jewellers',
            'code' => 'JAGDAMBA',
            'email' => 'shop@jagdamba.test',
            'phone' => '02240001234',
            'mobile' => '9876543210',
            'gstin' => '27AAPFU0939F1ZV',
            'pan' => 'AAPFU0939F',
            'address_line1' => '12 Market Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'postal_code' => '400001',
            'country' => 'India',
            'timezone' => 'Asia/Kolkata',
            'currency_code' => 'INR',
            'fy_start_month' => 4,
        ], $shop));

        $account = User::factory()->create(array_merge([
            'company_id' => $company->id,
            'name' => 'Shop Owner',
            'email' => 'owner-'.strtolower($company->code).'@jagdamba.test',
        ], $user));

        app(AccessProvisioner::class)->grant($account, $role);

        return $account;
    }
}
