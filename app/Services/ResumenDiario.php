<?php

namespace App\Services;

use App\Enums\EstadoReserva;
use App\Enums\PatronCruce;
use App\Models\Cruce;
use App\Models\Reserva;
use Carbon\Carbon;

class ResumenDiario
{
    /**
     * Calcula los datos del dia de operacion dado.
     * Si no hay cruces para esa fecha, devuelve null.
     */
    public function calcular(Carbon $fechaOperacion): ?array
    {
        $cruces = Cruce::query()
            ->with('repartidor')
            ->whereDate('fecha_operacion', $fechaOperacion)
            ->whereNotNull('repartidor_id')
            ->get();

        if ($cruces->isEmpty()) {
            return null;
        }

        $reservas = Reserva::query()
            ->whereDate('fecha_operacion', $fechaOperacion)
            ->where('estado', EstadoReserva::Activa)
            ->get();

        $totalReservado = $reservas->sum('paquetes');
        $totalAsignado = $cruces->sum('asignado');
        $totalEntregado = $cruces->sum('entregado');
        $cumplimientoGlobal = $totalAsignado > 0
            ? round(($totalEntregado / $totalAsignado) * 100, 1)
            : 0;

        $cumplimientoPromedio = round(
            $cruces->whereNotNull('cumplimiento_pct')->avg('cumplimiento_pct') ?? 0,
            1
        );

        $cumplientes = $cruces->whereNotNull('cumplimiento_pct')->sortByDesc('cumplimiento_pct');
        $top3 = $cumplientes->take(3)->map(fn ($c) => [
            'nombre' => $c->repartidor->nombre,
            'cumplimiento' => round((float) $c->cumplimiento_pct, 1),
            'entregado' => $c->entregado,
        ])->values();

        $bottom3 = $cumplientes->reverse()->take(3)->map(fn ($c) => [
            'nombre' => $c->repartidor->nombre,
            'cumplimiento' => round((float) $c->cumplimiento_pct, 1),
            'entregado' => $c->entregado,
        ])->values();

        $distribucion = [
            'consistente' => $cruces->where('patron', PatronCruce::Consistente)->count(),
            'sub_reserva' => $cruces->where('patron', PatronCruce::SubReserva)->count(),
            'sobre_reserva' => $cruces->where('patron', PatronCruce::SobreReserva)->count(),
            'sin_patron' => $cruces->whereNull('patron')->count(),
        ];

        $alertasActivas = app(AlertaPatrones::class)->detectar();

        return [
            'fecha' => $fechaOperacion,
            'total_repartidores' => $cruces->count(),
            'total_reservado' => $totalReservado,
            'total_asignado' => $totalAsignado,
            'total_entregado' => $totalEntregado,
            'cumplimiento_global' => $cumplimientoGlobal,
            'cumplimiento_promedio' => $cumplimientoPromedio,
            'distribucion' => $distribucion,
            'top3' => $top3,
            'bottom3' => $bottom3,
            'alertas_count' => $alertasActivas->count(),
            'alertas' => $alertasActivas->take(5),
        ];
    }
}
