<?php

namespace App\Http\Controllers\Web\Masters;

use App\Models\Collection;

class CollectionController extends SimpleCatalogController
{
    public static function modelClass(): string
    {
        return Collection::class;
    }

    protected function singular(): string
    {
        return 'Collection';
    }

    protected function routeName(): string
    {
        return 'collections';
    }

    public function routeKey(): string
    {
        return 'collection';
    }

    protected function intro(): string
    {
        return 'Group designs and pieces into a range, such as Bridal or Daily wear.';
    }

    protected function counts(): array
    {
        return ['designs' => 'Designs', 'items' => 'Pieces'];
    }
}
