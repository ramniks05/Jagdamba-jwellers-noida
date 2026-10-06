<?php

namespace App\Http\Controllers\Web\Masters;

use App\Models\Brand;

class BrandController extends SimpleCatalogController
{
    public static function modelClass(): string
    {
        return Brand::class;
    }

    protected function singular(): string
    {
        return 'Brand';
    }

    protected function routeName(): string
    {
        return 'brands';
    }

    public function routeKey(): string
    {
        return 'brand';
    }
}
