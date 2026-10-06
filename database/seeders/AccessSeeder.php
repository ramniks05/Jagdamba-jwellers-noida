<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use App\Services\Access\AccessProvisioner;
use App\Support\CompanyContext;
use Illuminate\Database\Seeder;

class AccessSeeder extends Seeder
{
    public function run(): void
    {
        $access = app(AccessProvisioner::class);
        $context = app(CompanyContext::class);

        foreach (Company::query()->orderBy('id')->get() as $company) {
            $context->set($company);
            $access->syncSystemRoles($company);
        }

        if (app()->environment('production')) {
            return;
        }

        $owner = User::query()->where('email', config('foundation.seed.owner_email'))->first();

        if (! $owner) {
            return;
        }

        $context->set($owner->company);

        if ($owner->roles()->count() === 0) {
            $access->grant($owner, 'owner');
        }
    }
}
