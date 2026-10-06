<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ManagesCompanyRecords;
use Illuminate\Database\Eloquent\Model;

abstract class ShopRecordPolicy
{
    use ManagesCompanyRecords;

    abstract protected function ability(): string;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, $this->ability().'.view');
    }

    public function view(User $user, Model $record): bool
    {
        return $this->allowsCompany($user, (int) $record->getAttribute('company_id'), $this->ability().'.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, $this->ability().'.create');
    }

    public function update(User $user, Model $record): bool
    {
        return $this->allowsCompany($user, (int) $record->getAttribute('company_id'), $this->ability().'.update');
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->allowsCompany($user, (int) $record->getAttribute('company_id'), $this->ability().'.delete');
    }
}
