<?php

namespace App\Policies;

use App\Models\ChargeMethod;
use App\Models\User;
use App\Policies\Concerns\ManagesCompanyRecords;
use Illuminate\Database\Eloquent\Model;

class MasterDataPolicy
{
    use ManagesCompanyRecords;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'masters.view');
    }

    public function view(User $user, Model $record): bool
    {
        return $this->allowsCompany($user, (int) $record->getAttribute('company_id'), 'masters.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'masters.create');
    }

    public function update(User $user, Model $record): bool
    {
        return $this->allowsCompany($user, (int) $record->getAttribute('company_id'), 'masters.update');
    }

    public function delete(User $user, Model $record): bool
    {
        if ($record instanceof ChargeMethod && $record->is_system) {
            return false;
        }

        return $this->allowsCompany($user, (int) $record->getAttribute('company_id'), 'masters.delete');
    }
}
