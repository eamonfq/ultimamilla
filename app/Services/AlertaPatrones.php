<?php

namespace App\Services;

use App\Enums\PatronCruce;
use App\Models\Cruce;
use Illuminate\Support\Collection;

class AlertaPatrones
{
    /**
     * Devuelve los repartidores con patron preocupante en los ultimos N dias.
     * Tipos:
     * - sobre_reserva_consecutivo: 3+ dias seguidos con patron sobre_reserva
     * - cumplimiento_sostenido_bajo: 5+ cruces en ultimos 14 dias con cumplimiento < 70%
     * - regresion: era consistente la semana pasada, ahora es sobre_reserva
     */
    public function detectar(int $diasMirar = 14): Collection
    {
        $hoy = today('America/Bogota');
        $desde = $hoy->copy()->subDays($diasMirar);

        $cruces = Cruce::query()
            ->whereBetween('fecha_operacion', [$desde, $hoy])
            ->whereNotNull('repartidor_id')
            ->whereHas('repartidor', fn ($q) => $q->activos())
            ->with('repartidor')
            ->orderBy('repartidor_id')
            ->orderBy('fecha_operacion')
            ->get();

        $porRepartidor = $cruces->groupBy('repartidor_id');

        return $porRepartidor->map(function ($grupos, $repartidorId) {
            $alertas = [];

            $alerta = $this->detectarSobreReservaConsecutivo($grupos);
            if ($alerta) {
                $alertas[] = $alerta;
            }

            $alerta = $this->detectarCumplimientoSostenidoBajo($grupos);
            if ($alerta) {
                $alertas[] = $alerta;
            }

            $alerta = $this->detectarRegresion($grupos);
            if ($alerta) {
                $alertas[] = $alerta;
            }

            if (empty($alertas)) {
                return null;
            }

            return [
                'repartidor' => $grupos->first()->repartidor,
                'alertas' => $alertas,
                'severidad' => $this->maxSeveridad($alertas),
            ];
        })->filter()->sortByDesc('severidad')->values();
    }

    private function detectarSobreReservaConsecutivo(Collection $cruces): ?array
    {
        $consecutivos = 0;
        $maxConsecutivos = 0;

        foreach ($cruces as $cruce) {
            if ($cruce->patron === PatronCruce::SobreReserva) {
                $consecutivos++;
                if ($consecutivos > $maxConsecutivos) {
                    $maxConsecutivos = $consecutivos;
                }
            } else {
                $consecutivos = 0;
            }
        }

        if ($maxConsecutivos < 3) {
            return null;
        }

        return [
            'tipo' => 'sobre_reserva_consecutivo',
            'titulo' => "{$maxConsecutivos} dias seguidos sobre-reservando",
            'detalle' => 'El repartidor reservo mas paquetes de los que pudo entregar varios dias seguidos.',
            'severidad' => $maxConsecutivos >= 5 ? 3 : 2,
        ];
    }

    private function detectarCumplimientoSostenidoBajo(Collection $cruces): ?array
    {
        $crucesValidos = $cruces->filter(fn ($c) => $c->cumplimiento_pct !== null);
        if ($crucesValidos->count() < 5) {
            return null;
        }

        $bajoUmbral = $crucesValidos->filter(fn ($c) => (float) $c->cumplimiento_pct < 70);
        if ($bajoUmbral->count() < 5) {
            return null;
        }

        $promedio = round($crucesValidos->avg('cumplimiento_pct'), 1);

        return [
            'tipo' => 'cumplimiento_sostenido_bajo',
            'titulo' => "Cumplimiento promedio {$promedio}% en ultimos {$crucesValidos->count()} dias",
            'detalle' => '5+ dias con cumplimiento bajo 70%. Posible necesidad de revision operativa.',
            'severidad' => $promedio < 60 ? 3 : 2,
        ];
    }

    private function detectarRegresion(Collection $cruces): ?array
    {
        if ($cruces->count() < 5) {
            return null;
        }

        $hoy = today('America/Bogota');
        $hace7 = $hoy->copy()->subDays(7);

        $semanaPasada = $cruces->filter(fn ($c) => $c->fecha_operacion->lt($hace7));
        $estaSemana = $cruces->filter(fn ($c) => $c->fecha_operacion->gte($hace7));

        if ($semanaPasada->count() < 2 || $estaSemana->count() < 2) {
            return null;
        }

        $consistentesAntes = $semanaPasada->where('patron', PatronCruce::Consistente)->count();
        $sobreReservaAhora = $estaSemana->where('patron', PatronCruce::SobreReserva)->count();

        $eraConsistente = ($consistentesAntes / $semanaPasada->count()) >= 0.6;
        $ahoraEnProblemas = ($sobreReservaAhora / $estaSemana->count()) >= 0.5;

        if (! ($eraConsistente && $ahoraEnProblemas)) {
            return null;
        }

        return [
            'tipo' => 'regresion',
            'titulo' => 'Cambio de patron reciente',
            'detalle' => 'Paso de consistente la semana pasada a sobre-reserva esta semana.',
            'severidad' => 2,
        ];
    }

    private function maxSeveridad(array $alertas): int
    {
        return max(array_column($alertas, 'severidad'));
    }
}
