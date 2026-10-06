<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Services\Masters\MasterProvisioner;
use App\Support\CompanyContext;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $masters = app(MasterProvisioner::class);
        $context = app(CompanyContext::class);

        foreach (Company::query()->orderBy('id')->get() as $company) {
            $context->set($company);
            $masters->seed($company);
        }
    }
}
