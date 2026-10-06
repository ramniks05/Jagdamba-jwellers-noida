<?php

namespace App\Services\Foundation;

use App\Enums\BranchStatus;
use App\Enums\CompanyStatus;
use App\Models\Branch;
use App\Models\Company;
use App\Services\Access\AccessProvisioner;
use App\Services\Commerce\CommerceProvisioner;
use App\Services\Masters\MasterProvisioner;
use App\Support\CompanyContext;
use App\Support\IdentityRules;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ShopProvisioner
{
    public function __construct(
        private readonly SettingService $settings,
        private readonly FinancialYearService $financialYears,
        private readonly DocumentNumberService $documentNumbers,
        private readonly AccessProvisioner $access,
        private readonly MasterProvisioner $masters,
        private readonly CommerceProvisioner $commerce,
        private readonly CompanyContext $context,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function provision(array $attributes, ?CarbonInterface $on = null): Company
    {
        $attributes = array_merge([
            'country' => 'India',
            'timezone' => 'Asia/Kolkata',
            'currency_code' => 'INR',
            'fy_start_month' => 4,
            'branch_name' => 'Head Office',
            'branch_code' => 'HO',
        ], $attributes);

        foreach (['code', 'currency_code', 'gstin', 'pan', 'branch_code'] as $field) {
            if (isset($attributes[$field]) && is_string($attributes[$field])) {
                $attributes[$field] = strtoupper(trim($attributes[$field]));
            }
        }

        Validator::make($attributes, [
            'name' => ['required', 'string', 'max:160'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'code' => ['required', 'regex:'.IdentityRules::CODE, Rule::unique('companies', 'code')],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:20'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'website' => ['nullable', 'url', 'max:200'],
            'gstin' => ['nullable', 'regex:'.IdentityRules::GSTIN, Rule::unique('companies', 'gstin')],
            'pan' => ['nullable', 'regex:'.IdentityRules::PAN, Rule::unique('companies', 'pan')],
            'address_line1' => ['nullable', 'string', 'max:200'],
            'address_line2' => ['nullable', 'string', 'max:200'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:12'],
            'country' => ['required', 'string', 'max:100'],
            'timezone' => ['required', 'timezone'],
            'currency_code' => ['required', 'regex:/^[A-Z]{3}$/'],
            'fy_start_month' => ['required', 'integer', 'between:1,12'],
            'branch_name' => ['required', 'string', 'max:160'],
            'branch_code' => ['required', 'regex:'.IdentityRules::CODE],
        ])->validate();

        return DB::transaction(function () use ($attributes, $on) {
            $company = Company::query()->create([
                'code' => $attributes['code'],
                'name' => $attributes['name'],
                'legal_name' => $attributes['legal_name'] ?? null,
                'email' => $attributes['email'] ?? null,
                'phone' => $attributes['phone'] ?? null,
                'mobile' => $attributes['mobile'] ?? null,
                'website' => $attributes['website'] ?? null,
                'gstin' => $attributes['gstin'] ?? null,
                'pan' => $attributes['pan'] ?? null,
                'address_line1' => $attributes['address_line1'] ?? null,
                'address_line2' => $attributes['address_line2'] ?? null,
                'city' => $attributes['city'] ?? null,
                'state' => $attributes['state'] ?? null,
                'postal_code' => $attributes['postal_code'] ?? null,
                'country' => $attributes['country'],
                'status' => CompanyStatus::Active,
                'timezone' => $attributes['timezone'],
                'currency_code' => $attributes['currency_code'],
                'fy_start_month' => (int) $attributes['fy_start_month'],
            ]);

            $this->context->set($company);

            Branch::query()->create([
                'company_id' => $company->id,
                'code' => $attributes['branch_code'],
                'name' => $attributes['branch_name'],
                'is_head_office' => true,
                'email' => $attributes['email'] ?? null,
                'phone' => $attributes['phone'] ?? null,
                'mobile' => $attributes['mobile'] ?? null,
                'gstin' => $attributes['gstin'] ?? null,
                'address_line1' => $attributes['address_line1'] ?? null,
                'address_line2' => $attributes['address_line2'] ?? null,
                'city' => $attributes['city'] ?? null,
                'state' => $attributes['state'] ?? null,
                'postal_code' => $attributes['postal_code'] ?? null,
                'country' => $attributes['country'],
                'status' => BranchStatus::Active,
                'timezone' => $attributes['timezone'],
            ]);

            $this->settings->seedDefaults($company);
            $this->financialYears->openInitialYear($company, $on);
            $this->documentNumbers->seedDefaults($company);
            $this->access->syncSystemRoles($company);
            $this->masters->seed($company);
            $this->commerce->seed($company);

            $company = $company->fresh(['branches', 'financialYears', 'documentSequences']);
            $this->context->set($company);

            return $company;
        });
    }
}
