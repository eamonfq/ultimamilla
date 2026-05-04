<?php

namespace App\Services;

use App\Enums\EstadoReserva;
use App\Enums\PatronCruce;
use App\Models\Asignacion;
use App\Models\Cruce;
use App\Models\PinitImport;
use App\Models\Repartidor;
use App\Models\Reserva;
use App\Settings\UltimamillaSettings;
use Illuminate\Support\Facades\DB;

class CruceCalculator
{
    public function __construct(private UltimamillaSettings $settings) {}

    /**
     * Calcula y persiste todos los cruces para un import dado.
     * Reemplaza cruces existentes para la misma fecha_operacion.
     */
    public function calcularParaImport(PinitImport $import): int
    {
        $fechaOperacion = $import->fecha_archivo;

        return DB::transaction(function () use ($import, $fechaOperacion) {
            // Borrar cruces previos de esta fecha (idempotencia ante reimportacion)
            Cruce::where('fecha_operacion', $fechaOperacion)->delete();

            // Agrupar rutas del import por repartidor_id (excluyendo null)
            $rutasPorRepartidor = $import->rutas()
                ->whereNotNull('repartidor_id')
                ->get()
                ->groupBy('repartidor_id');

            $cantidadCreados = 0;

            foreach ($rutasPorRepartidor as $repartidorId => $rutas) {
                $cruce = $this->crearCruce($import, (int) $repartidorId, $rutas, $fechaOperacion);
                if ($cruce) {
                    $cantidadCreados++;
                }
            }

            return $cantidadCreados;
        });
    }

    private function crearCruce(PinitImport $import, int $repartidorId, $rutas, $fechaOperacion): ?Cruce
    {
        // Sumar entregados y total SOLO de rutas que NO son devolucion
        $rutasNoDevolucion = $rutas->where('es_devolucion', false);

        $totalEntregadosArchivo = (int) $rutasNoDevolucion->sum('entregados');
        $totalDelArchivo = (int) $rutasNoDevolucion->sum('total');

        // Buscar reserva activa de ese repartidor para esa fecha
        $reserva = Reserva::query()
            ->where('repartidor_id', $repartidorId)
            ->whereDate('fecha_operacion', $fechaOperacion)
            ->where('estado', EstadoReserva::Activa)
            ->first();

        $reservado = $reserva?->paquetes ?? 0;

        // Asignacion (si existe)
        $asignacion = $reserva
            ? Asignacion::where('reserva_id', $reserva->id)->first()
            : null;
        $asignado = $asignacion?->paquetes_asignados ?? $reservado;

        // Calcular cumplimiento (sobre lo asignado, no lo reservado)
        $cumplimientoPct = $asignado > 0
            ? round(($totalEntregadosArchivo / $asignado) * 100, 2)
            : null;

        // Calcular patron
        $patron = $this->calcularPatron($repartidorId, $reservado, $totalEntregadosArchivo, $asignado);

        return Cruce::create([
            'fecha_operacion' => $fechaOperacion,
            'repartidor_id' => $repartidorId,
            'reservado' => $reservado,
            'asignado' => $asignado,
            'entregado' => $totalEntregadosArchivo,
            'cumplimiento_pct' => $cumplimientoPct,
            'patron' => $patron,
            'pinit_import_id' => $import->id,
        ]);
    }

    private function calcularPatron(int $repartidorId, int $reservado, int $entregado, int $asignado): ?PatronCruce
    {
        if ($asignado === 0 || $reservado === 0) {
            return null;
        }

        $cumplimientoRatio = $entregado / $asignado;

        // sobre_reserva: entrego menos del threshold
        if ($cumplimientoRatio < $this->settings->cumplimiento_sobre_reserva_max) {
            return PatronCruce::SobreReserva;
        }

        // Para los otros dos patrones necesitamos el cupo del repartidor
        $repartidor = Repartidor::find($repartidorId);
        if (! $repartidor) {
            return null;
        }

        $cupoEfectivo = $repartidor->cupoEfectivo($this->settings);
        if ($cupoEfectivo === 0) {
            return null;
        }

        $reservadoVsCupoRatio = $reservado / $cupoEfectivo;

        if (
            $cumplimientoRatio >= $this->settings->cumplimiento_consistente_min
            && $reservadoVsCupoRatio < $this->settings->cumplimiento_sub_reserva_cupo_max
        ) {
            return PatronCruce::SubReserva;
        }

        if (
            $cumplimientoRatio >= $this->settings->cumplimiento_consistente_min
            && $reservadoVsCupoRatio >= $this->settings->cumplimiento_sub_reserva_cupo_max
        ) {
            return PatronCruce::Consistente;
        }

        return null;
    }
}
