<?php

namespace App\Policies;

class OldGoldPolicy extends ShopRecordPolicy
{
    protected function ability(): string
    {
        return 'sales';
    }
}
