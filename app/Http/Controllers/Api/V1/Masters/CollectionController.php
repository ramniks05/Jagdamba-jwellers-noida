<?php

namespace App\Http\Controllers\Api\V1\Masters;

use App\Models\Collection;

class CollectionController extends SimpleCatalogController
{
    public static function modelClass(): string
    {
        return Collection::class;
    }

    public function routeKey(): string
    {
        return 'collection';
    }
}
