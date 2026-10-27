<?php

namespace App\Http\Controllers\Web\Masters;

use App\Models\Society;

class SocietyController extends SimpleCatalogController
{
    public static function modelClass(): string
    {
        return Society::class;
    }

    protected function singular(): string
    {
        return 'Society';
    }

    protected function routeName(): string
    {
        return 'societies';
    }

    public function routeKey(): string
    {
        return 'society';
    }

    protected function intro(): string
    {
        return 'Nearby housing societies. Staff pick one when saving a customer so you can filter the customer list later.';
    }

    protected function counts(): array
    {
        return ['customers' => 'Customers'];
    }
}
