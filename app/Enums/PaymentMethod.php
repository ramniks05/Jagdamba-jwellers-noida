<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Upi = 'upi';
    case Card = 'card';
    case Bank = 'bank';
    case Cheque = 'cheque';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Upi => 'UPI',
            self::Card => 'Card',
            self::Bank => 'Bank transfer',
            self::Cheque => 'Cheque',
        };
    }
}
