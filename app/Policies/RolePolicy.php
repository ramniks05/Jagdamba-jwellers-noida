<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Policies\Concerns\ManagesCompanyRecords;

class RolePolicy
{
    use ManagesCompanyRecords;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $this->allowsCompany($user, (int) $role->company_id, 'roles.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        return ! $role->is_system
            && $this->allowsCompany($user, (int) $role->company_id, 'roles.update');
    }

    public function delete(User $user, Role $role): bool
    {
        return ! $role->is_system
            && $this->allowsCompany($user, (int) $role->company_id, 'roles.delete');
    }
}
