<?php

namespace App\Enums;

enum CustomerType: string
{
    case Retail = 'retail';
    case Wholesale = 'wholesale';

    public function label(): string
    {
        return match ($this) {
            self::Retail => 'Retail',
            self::Wholesale => 'Wholesale',
        };
    }
}
