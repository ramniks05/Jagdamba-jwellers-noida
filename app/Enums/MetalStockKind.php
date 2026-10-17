<?php

namespace App\Enums;

enum MetalStockKind: string
{
    case Opening = 'opening';
    case Purchase = 'purchase';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Opening',
            self::Purchase => 'Purchase',
            self::Sale => 'Sale',
            self::SaleReturn => 'Back from a bill',
        };
    }
}
