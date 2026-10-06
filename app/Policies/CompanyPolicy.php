<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Policies\Concerns\ManagesCompanyRecords;

class CompanyPolicy
{
    use ManagesCompanyRecords;

    public function dashboard(User $user, Company $company): bool
    {
        return $this->managesCompany($user, (int) $company->id);
    }

    public function view(User $user, Company $company): bool
    {
        return $this->allowsCompany($user, (int) $company->id, 'company.view');
    }

    public function update(User $user, Company $company): bool
    {
        return $this->allowsCompany($user, (int) $company->id, 'company.update');
    }
}
