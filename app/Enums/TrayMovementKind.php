<?php

namespace App\Enums;

enum TrayMovementKind: string
{
    case Opening = 'opening';
    case Adjustment = 'adjustment';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Opening',
            self::Adjustment => 'Adjustment',
            self::Sale => 'Sale',
            self::SaleReturn => 'Sale return',
        };
    }
}
