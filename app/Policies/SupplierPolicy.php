<?php

namespace App\Policies;

class SupplierPolicy extends ShopRecordPolicy
{
    protected function ability(): string
    {
        return 'suppliers';
    }
}
