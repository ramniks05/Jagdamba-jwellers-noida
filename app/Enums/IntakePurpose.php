<?php

namespace App\Enums;

enum IntakePurpose: string
{
    case Create = 'create';
    case Update = 'update';

    public function label(): string
    {
        return match ($this) {
            self::Create => 'New customer',
            self::Update => 'Update details',
        };
    }
}
