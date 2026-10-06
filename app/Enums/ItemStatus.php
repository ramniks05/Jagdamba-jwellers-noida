<?php

namespace App\Enums;

enum ItemStatus: string
{
    case Available = 'available';
    case Sold = 'sold';
    case Reserved = 'reserved';
    case Repair = 'repair';
    case Damaged = 'damaged';
    case Lost = 'lost';
    case Transferred = 'transferred';
    case Returned = 'returned';
    case SentBack = 'sent_back';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Sold => 'Sold',
            self::Reserved => 'Reserved',
            self::Repair => 'Repair',
            self::Damaged => 'Damaged',
            self::Lost => 'Lost',
            self::Transferred => 'Transferred',
            self::Returned => 'Returned',
            self::SentBack => 'Sent back to supplier',
        };
    }
}
