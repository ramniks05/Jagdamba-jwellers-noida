<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ManagesCompanyRecords;

class UserPolicy
{
    use ManagesCompanyRecords;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'users.view');
    }

    public function view(User $user, User $subject): bool
    {
        return $this->allowsCompany($user, (int) $subject->company_id, 'users.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'users.create');
    }

    public function update(User $user, User $subject): bool
    {
        return $this->allowsCompany($user, (int) $subject->company_id, 'users.update');
    }

    public function delete(User $user, User $subject): bool
    {
        return ! $user->is($subject)
            && $this->allowsCompany($user, (int) $subject->company_id, 'users.delete');
    }
}
