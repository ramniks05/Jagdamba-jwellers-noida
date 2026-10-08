<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AdvanceOrderPolicy extends ShopRecordPolicy
{
    protected function ability(): string
    {
        return 'sales';
    }

    public function update(User $user, Model $record): bool
    {
        $companyId = (int) $record->getAttribute('company_id');

        return $this->allowsCompany($user, $companyId, 'sales.create')
            || $this->allowsCompany($user, $companyId, 'sales.update');
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }
}
