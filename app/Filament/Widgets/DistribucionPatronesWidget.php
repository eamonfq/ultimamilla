<?php

namespace App\Filament\Widgets;

use App\Enums\PatronCruce;
use App\Models\Cruce;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class DistribucionPatronesWidget extends ChartWidget
{
    protected ?string $heading = 'Distribucion de patrones (ultimos 7 dias)';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 1];

    protected function getData(): array
    {
        return Cache::remember('distribucion_patrones', now()->addMinutes(5), function () {
            $hoy = today('America/Bogota');
            $desde = $hoy->copy()->subDays(7);

            $cruces = Cruce::query()
                ->whereBetween('fecha_operacion', [$desde, $hoy])
                ->whereNotNull('patron')
                ->get();

            $consistente = $cruces->where('patron', PatronCruce::Consistente)->count();
            $subReserva = $cruces->where('patron', PatronCruce::SubReserva)->count();
            $sobreReserva = $cruces->where('patron', PatronCruce::SobreReserva)->count();
            $sinPatron = Cruce::query()
                ->whereBetween('fecha_operacion', [$desde, $hoy])
                ->whereNull('patron')
                ->count();

            return [
                'datasets' => [
                    [
                        'data' => [$consistente, $subReserva, $sobreReserva, $sinPatron],
                        'backgroundColor' => [
                            'rgb(16, 185, 129)',
                            'rgb(245, 158, 11)',
                            'rgb(225, 29, 72)',
                            'rgb(148, 163, 184)',
                        ],
                        'borderWidth' => 0,
                    ],
                ],
                'labels' => ['Consistente', 'Sub-reserva', 'Sobre-reserva', 'Sin patron'],
            ];
        });
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['position' => 'bottom'],
            ],
            'cutout' => '65%',
        ];
    }
}
