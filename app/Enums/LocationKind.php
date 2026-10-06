<?php

namespace App\Enums;

enum LocationKind: string
{
    case Warehouse = 'warehouse';
    case Room = 'room';
    case Rack = 'rack';
    case Shelf = 'shelf';
    case Box = 'box';

    public function label(): string
    {
        return match ($this) {
            self::Warehouse => 'Warehouse',
            self::Room => 'Room',
            self::Rack => 'Rack',
            self::Shelf => 'Shelf',
            self::Box => 'Box',
        };
    }
}
