<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;
use App\Policies\Concerns\ManagesCompanyRecords;

class BranchPolicy
{
    use ManagesCompanyRecords;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'branches.view');
    }

    public function view(User $user, Branch $branch): bool
    {
        return $this->allowsBranch($user, $branch, 'branches.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'branches.create');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $this->allowsBranch($user, $branch, 'branches.update');
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $this->allowsBranch($user, $branch, 'branches.delete');
    }
}
