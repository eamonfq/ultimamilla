<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class UltimamillaSettings extends Settings
{
    public int $cupo_global_default;

    public string $hora_cierre_reservas;

    public int $umbral_minimo_diario;

    public array $dias_operativos;

    public int $devolucion_threshold;

    public float $cumplimiento_sobre_reserva_max;

    public float $cumplimiento_consistente_min;

    public float $cumplimiento_sub_reserva_cupo_max;

    public static function group(): string
    {
        return 'ultimamilla';
    }
}
