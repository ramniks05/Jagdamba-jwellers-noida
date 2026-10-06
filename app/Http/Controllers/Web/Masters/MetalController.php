<?php

namespace App\Http\Controllers\Web\Masters;

use App\Models\MetalType;
use Illuminate\Http\Request;

class MetalController extends SimpleCatalogController
{
    public static function modelClass(): string
    {
        return MetalType::class;
    }

    protected function singular(): string
    {
        return 'Metal';
    }

    protected function title(): string
    {
        return 'Metals';
    }

    protected function routeName(): string
    {
        return 'metals';
    }

    public function routeKey(): string
    {
        return 'metal';
    }

    protected function newQuery(Request $request)
    {
        return parent::newQuery($request)->withCount('purities');
    }
}
