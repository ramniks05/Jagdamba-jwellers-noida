<?php

namespace App\Enums;

enum StoneGradeKind: string
{
    case Cut = 'cut';
    case Color = 'color';
    case Clarity = 'clarity';
    case Certification = 'certification';

    public function label(): string
    {
        return match ($this) {
            self::Cut => 'Cut',
            self::Color => 'Color',
            self::Clarity => 'Clarity',
            self::Certification => 'Certification',
        };
    }
}
