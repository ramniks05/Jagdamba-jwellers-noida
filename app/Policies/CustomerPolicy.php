<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CustomerPolicy extends ShopRecordPolicy
{
    protected function ability(): string
    {
        return 'customers';
    }

    public function delete(User $user, Model $record): bool
    {
        if ($record instanceof Customer && $record->is_system) {
            return false;
        }

        return parent::delete($user, $record);
    }
}
