<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RepairPolicy extends ShopRecordPolicy
{
    protected function ability(): string
    {
        return 'repairs';
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }
}
