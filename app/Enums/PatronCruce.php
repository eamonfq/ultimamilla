<?php

namespace App\Enums;

enum PatronCruce: string
{
    case SubReserva = 'sub_reserva';
    case SobreReserva = 'sobre_reserva';
    case Consistente = 'consistente';

    public function label(): string
    {
        return match ($this) {
            self::SubReserva => 'Sub-reserva',
            self::SobreReserva => 'Sobre-reserva',
            self::Consistente => 'Consistente',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SubReserva => 'warning',
            self::SobreReserva => 'danger',
            self::Consistente => 'success',
        };
    }
}
