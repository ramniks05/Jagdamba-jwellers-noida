<?php

namespace App\Enums;

enum ItemSource: string
{
    case Own = 'own';
    case Purchased = 'purchased';
    case OldGold = 'old_gold';

    public function label(): string
    {
        return match ($this) {
            self::Own => 'Own',
            self::Purchased => 'Purchased',
            self::OldGold => 'Old gold',
        };
    }
}
