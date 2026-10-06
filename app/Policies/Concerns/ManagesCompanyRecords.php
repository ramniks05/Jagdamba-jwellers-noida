<?php

namespace App\Policies\Concerns;

use App\Models\Branch;
use App\Models\User;

trait ManagesCompanyRecords
{
    protected function managesShop(User $user): bool
    {
        $user->loadMissing('company');

        return $user->is_active
            && $user->company_id !== null
            && (bool) $user->company?->isOperational();
    }

    protected function managesCompany(User $user, int $companyId): bool
    {
        return $this->managesShop($user) && (int) $user->company_id === $companyId;
    }

    protected function allows(User $user, string $permission): bool
    {
        return $this->managesShop($user) && $user->hasPermission($permission);
    }

    protected function allowsCompany(User $user, int $companyId, string $permission): bool
    {
        return $this->managesCompany($user, $companyId) && $user->hasPermission($permission);
    }

    protected function allowsBranch(User $user, Branch $branch, string $permission): bool
    {
        if (! $this->allowsCompany($user, (int) $branch->company_id, $permission)) {
            return false;
        }

        $restricted = $user->restrictedBranchIds();

        return $restricted === null || in_array((int) $branch->id, $restricted, true);
    }
}
