<?php

namespace App\Enums;

enum IntakeStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for approval',
            self::Approved => 'Added as customer',
            self::Rejected => 'Not added',
        };
    }
}
