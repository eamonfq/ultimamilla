<?php

namespace App\Services;

use App\Models\Reserva;
use App\Settings\UltimamillaSettings;
use Carbon\Carbon;

class DeadlineChecker
{
    public function __construct(private UltimamillaSettings $settings) {}

    public function isReservaLocked(Reserva $reserva): bool
    {
        if ($reserva->locked_at !== null) {
            return true;
        }

        $reserva->loadMissing('asignacion');
        if ($reserva->asignacion?->entregado_a_repartidor_at !== null) {
            return true;
        }

        return now('America/Bogota')->gte($this->deadlineFor($reserva->fecha_operacion));
    }

    public function deadlineFor(Carbon $fechaOperacion): Carbon
    {
        [$h, $m] = explode(':', $this->settings->hora_cierre_reservas);

        return $fechaOperacion->copy()
            ->setTimezone('America/Bogota')
            ->subDay()
            ->setTime((int) $h, (int) $m, 0);
    }

    public function esDiaOperativo(Carbon $fecha): bool
    {
        return in_array($fecha->dayOfWeek, $this->settings->dias_operativos, true);
    }
}
