<?php

namespace App\Policies;

use App\Models\FinancialYear;
use App\Models\User;
use App\Policies\Concerns\ManagesCompanyRecords;

class FinancialYearPolicy
{
    use ManagesCompanyRecords;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'financial_years.view');
    }

    public function view(User $user, FinancialYear $financialYear): bool
    {
        return $this->allowsCompany($user, (int) $financialYear->company_id, 'financial_years.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'financial_years.create');
    }

    public function update(User $user, FinancialYear $financialYear): bool
    {
        return $this->allowsCompany($user, (int) $financialYear->company_id, 'financial_years.update');
    }

    public function delete(User $user, FinancialYear $financialYear): bool
    {
        return $this->allowsCompany($user, (int) $financialYear->company_id, 'financial_years.delete');
    }

    public function close(User $user, FinancialYear $financialYear): bool
    {
        return $this->allowsCompany($user, (int) $financialYear->company_id, 'financial_years.close');
    }
}
