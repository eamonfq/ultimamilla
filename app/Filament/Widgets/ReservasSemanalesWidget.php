<?php

namespace App\Filament\Widgets;

use App\Enums\EstadoReserva;
use App\Models\Reserva;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ReservasSemanalesWidget extends ChartWidget
{
    protected ?string $heading = 'Capacidad próximos 7 días';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $hoy = today('America/Bogota');
        $hasta = $hoy->copy()->addDays(7);

        $reservas = Reserva::query()
            ->where('estado', EstadoReserva::Activa)
            ->whereBetween('fecha_operacion', [$hoy, $hasta])
            ->select('fecha_operacion', DB::raw('SUM(paquetes) as total'))
            ->groupBy('fecha_operacion')
            ->orderBy('fecha_operacion')
            ->pluck('total', 'fecha_operacion');

        $labels = [];
        $data = [];

        for ($i = 0; $i < 8; $i++) {
            $fecha = $hoy->copy()->addDays($i);
            $labels[] = $fecha->locale('es')->isoFormat('ddd D');
            $data[] = (int) ($reservas[$fecha->toDateString()] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Paquetes reservados',
                    'data' => $data,
                    'backgroundColor' => 'rgba(79, 70, 229, 0.7)',
                    'borderColor' => 'rgb(79, 70, 229)',
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
