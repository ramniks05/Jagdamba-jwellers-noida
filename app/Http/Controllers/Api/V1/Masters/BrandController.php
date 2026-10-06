<?php

namespace App\Http\Controllers\Api\V1\Masters;

use App\Models\Brand;

class BrandController extends SimpleCatalogController
{
    public static function modelClass(): string
    {
        return Brand::class;
    }

    public function routeKey(): string
    {
        return 'brand';
    }
}
