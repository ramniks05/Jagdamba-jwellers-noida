<?php

namespace App\Http\Controllers\Api\V1\Masters;

use App\Models\StoneType;

class StoneTypeController extends SimpleCatalogController
{
    public static function modelClass(): string
    {
        return StoneType::class;
    }

    public function routeKey(): string
    {
        return 'stone_type';
    }
}
