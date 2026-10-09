<?php

namespace App\Http\Controllers\Web\Masters;

use App\Models\StoneType;

class StoneTypeController extends SimpleCatalogController
{
    public static function modelClass(): string
    {
        return StoneType::class;
    }

    protected function singular(): string
    {
        return 'Stone type';
    }

    protected function routeName(): string
    {
        return 'stone-types';
    }

    public function routeKey(): string
    {
        return 'stone_type';
    }

    protected function intro(): string
    {
        return 'Stones offered when you add a piece or a bill line.';
    }

    protected function redirectTo(): string
    {
        return route('stones.index');
    }
}
