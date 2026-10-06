<?php

namespace App\Policies;

class SchemePolicy extends ShopRecordPolicy
{
    protected function ability(): string
    {
        return 'schemes';
    }
}
