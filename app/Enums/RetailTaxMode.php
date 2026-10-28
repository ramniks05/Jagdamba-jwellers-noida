<?php

namespace App\Enums;

enum RetailTaxMode: string
{
    case None = 'none';
    case Inclusive = 'inclusive';
    case Exclusive = 'exclusive';

    public function label(): string
    {
        return match ($this) {
            self::None => 'No GST',
            self::Inclusive => 'GST included in price',
            self::Exclusive => 'GST extra on price',
        };
    }
}
