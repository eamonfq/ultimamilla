<?php

namespace App\Enums;

enum EstadoReserva: string
{
    case Activa = 'activa';
    case Cancelada = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::Activa => 'Activa',
            self::Cancelada => 'Cancelada',
        };
    }
}
