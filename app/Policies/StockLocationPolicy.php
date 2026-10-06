<?php

namespace App\Policies;

class StockLocationPolicy extends ShopRecordPolicy
{
    protected function ability(): string
    {
        return 'inventory';
    }
}
