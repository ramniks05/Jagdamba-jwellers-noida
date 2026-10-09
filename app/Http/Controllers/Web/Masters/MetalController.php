<?php

namespace App\Http\Controllers\Web\Masters;

use App\Models\MetalType;

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

    protected function intro(): string
    {
        return 'Each metal has its purities. Rates, pieces and girvi use the purity.';
    }

    protected function routeName(): string
    {
        return 'metals';
    }

    public function routeKey(): string
    {
        return 'metal';
    }

    protected function counts(): array
    {
        return ['purities' => 'Purities'];
    }
}
