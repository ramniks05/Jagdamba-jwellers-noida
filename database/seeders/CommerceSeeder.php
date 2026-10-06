<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Services\Commerce\CommerceProvisioner;
use App\Support\CompanyContext;
use Illuminate\Database\Seeder;

class CommerceSeeder extends Seeder
{
    public function run(): void
    {
        $commerce = app(CommerceProvisioner::class);
        $context = app(CompanyContext::class);

        foreach (Company::query()->orderBy('id')->get() as $company) {
            $context->set($company);
            $commerce->seed($company);
        }
    }
}
