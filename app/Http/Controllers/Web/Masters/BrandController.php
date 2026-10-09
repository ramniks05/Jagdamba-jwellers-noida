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

    protected function intro(): string
    {
        return 'Maker or label shown on a piece, such as your own house brand.';
    }

    protected function counts(): array
    {
        return ['items' => 'Pieces'];
    }
}
