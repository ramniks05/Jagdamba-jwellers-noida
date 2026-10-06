<?php

namespace App\Enums;

enum ChargeAppliesTo: string
{
    case Making = 'making';
    case Wastage = 'wastage';

    public function label(): string
    {
        return match ($this) {
            self::Making => 'Making charges',
            self::Wastage => 'Wastage',
        };
    }
}
