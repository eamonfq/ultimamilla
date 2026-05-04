<?php

namespace App\Filament\Widgets;

use App\Enums\EstadoReserva;
use App\Models\Repartidor;
use App\Models\Reserva;
use App\Settings\UltimamillaSettings;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CapacidadMananaWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $manana = today('America/Bogota')->addDay();
        $settings = app(UltimamillaSettings::class);

        $totalPaquetes = (int) Reserva::query()
            ->where('estado', EstadoReserva::Activa)
            ->whereDate('fecha_operacion', $manana)
            ->sum('paquetes');

        $repartidoresQueReservaron = Reserva::query()
            ->where('estado', EstadoReserva::Activa)
            ->whereDate('fecha_operacion', $manana)
            ->distinct('repartidor_id')
            ->count('repartidor_id');

        $repartidoresActivos = Repartidor::activos()->count();

        $stats = [
            Stat::make('Paquetes reservados para mañana', number_format($totalPaquetes))
                ->description($manana->locale('es')->isoFormat('dddd D MMM'))
                ->descriptionIcon('heroicon-o-calendar')
                ->color('primary'),

            Stat::make('Repartidores que reservaron', "{$repartidoresQueReservaron} de {$repartidoresActivos}")
                ->description($repartidoresQueReservaron < $repartidoresActivos
                    ? ($repartidoresActivos - $repartidoresQueReservaron).' faltan'
                    : 'Todos confirmaron')
                ->descriptionIcon($repartidoresQueReservaron < $repartidoresActivos
                    ? 'heroicon-o-exclamation-circle'
                    : 'heroicon-o-check-circle')
                ->color($repartidoresQueReservaron < $repartidoresActivos ? 'warning' : 'success'),
        ];

        if ($settings->umbral_minimo_diario > 0) {
            $faltante = $settings->umbral_minimo_diario - $totalPaquetes;
            $cumple = $faltante <= 0;

            $stats[] = Stat::make(
                'Umbral mínimo diario',
                $cumple ? 'Cubierto' : 'Faltan '.number_format(abs($faltante))
            )
                ->description('Mínimo: '.number_format($settings->umbral_minimo_diario).' paq.')
                ->descriptionIcon($cumple ? 'heroicon-o-check-badge' : 'heroicon-o-exclamation-triangle')
                ->color($cumple ? 'success' : 'danger');
        }

        return $stats;
    }
}
