<?php

namespace App\Enums;

enum Ciudad: string
{
    case MED = 'MED';
    case ITAGUI = 'ITAGUI';

    public function label(): string
    {
        return match ($this) {
            self::MED => 'Medellín',
            self::ITAGUI => 'Itagüí',
        };
    }
}
