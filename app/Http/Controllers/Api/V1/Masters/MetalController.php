<?php

namespace App\Http\Controllers\Api\V1\Masters;

use App\Models\MetalType;

class MetalController extends SimpleCatalogController
{
    public static function modelClass(): string
    {
        return MetalType::class;
    }

    public function routeKey(): string
    {
        return 'metal';
    }
}
