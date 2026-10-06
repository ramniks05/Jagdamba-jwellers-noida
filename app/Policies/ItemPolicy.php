<?php

namespace App\Policies;

class ItemPolicy extends ShopRecordPolicy
{
    protected function ability(): string
    {
        return 'items';
    }
}
