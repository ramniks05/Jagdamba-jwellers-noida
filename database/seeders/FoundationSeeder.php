<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use App\Services\Access\AccessProvisioner;
use App\Services\Foundation\ShopProvisioner;
use Illuminate\Database\Seeder;
use RuntimeException;

class FoundationSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Demo shop seeding is disabled in production.');
        }

        $shop = config('foundation.demo_shop');

        if (Company::withTrashed()->where('code', $shop['code'])->exists()) {
            return;
        }

        $company = app(ShopProvisioner::class)->provision($shop);

        $owner = User::query()->create([
            'company_id' => $company->id,
            'name' => config('foundation.seed.owner_name'),
            'email' => config('foundation.seed.owner_email'),
            'password' => config('foundation.seed.owner_password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        app(AccessProvisioner::class)->grant($owner, 'owner');
    }
}
