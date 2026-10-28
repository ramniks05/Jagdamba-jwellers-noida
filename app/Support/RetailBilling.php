<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

final class RetailBilling
{
    public static function enabled(): bool
    {
        return Schema::hasTable('retail_products') && Schema::hasTable('sale_retail_lines');
    }
}
