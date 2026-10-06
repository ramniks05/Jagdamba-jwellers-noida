<?php

namespace App\Services\Commerce;

use App\Enums\CustomerType;
use App\Enums\KycStatus;
use App\Enums\LocationKind;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\StockLocation;
use App\Services\Foundation\DocumentNumberService;
use App\Support\CompanyContext;

class CommerceProvisioner
{
    public function __construct(
        private readonly CompanyContext $context,
        private readonly DocumentNumberService $numbers,
    ) {}

    public function seed(Company $company): void
    {
        $this->context->ensureId((int) $company->id);
        $this->numbers->seedDefaults($company);
        $branch = Branch::query()->where('is_head_office', true)->first();

        if ($branch) {
            StockLocation::query()->firstOrCreate(
                ['code' => 'MAIN'],
                [
                    'branch_id' => $branch->id,
                    'kind' => LocationKind::Warehouse,
                    'name' => 'Main',
                    'is_active' => true,
                ],
            );
        }

        Customer::query()->firstOrCreate(
            ['code' => 'WALKIN'],
            [
                'name' => 'Walk-in',
                'country' => $company->country ?: 'India',
                'customer_type' => CustomerType::Retail,
                'kyc_status' => KycStatus::Verified,
                'is_system' => true,
                'is_active' => true,
            ],
        );
    }
}
