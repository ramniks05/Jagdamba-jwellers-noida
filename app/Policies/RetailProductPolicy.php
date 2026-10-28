<?php

namespace App\Policies;

class RetailProductPolicy extends ShopRecordPolicy
{
    protected function ability(): string
    {
        return 'inventory';
    }
}
