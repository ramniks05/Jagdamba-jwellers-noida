<?php

namespace App\Policies;

class StockTrayPolicy extends ShopRecordPolicy
{
    protected function ability(): string
    {
        return 'inventory';
    }
}
