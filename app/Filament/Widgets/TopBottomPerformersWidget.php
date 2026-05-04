<?php

namespace App\Filament\Widgets;

use App\Models\Cruce;
use App\Models\Repartidor;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

class TopBottomPerformersWidget extends Widget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 1];

    protected string $view = 'filament.widgets.top-bottom-performers';

    public function getViewData(): array
    {
        return Cache::remember('top_bottom_performers', now()->addMinutes(5), function () {
            $hoy = today('America/Bogota');
            $desde = $hoy->copy()->subDays(14);

            $stats = Cruce::query()
                ->whereBetween('fecha_operacion', [$desde, $hoy])
                ->whereNotNull('repartidor_id')
                ->whereNotNull('cumplimiento_pct')
                ->selectRaw('repartidor_id,
                             COUNT(*) as dias,
                             AVG(cumplimiento_pct) as promedio,
                             SUM(entregado) as total_entregado')
                ->groupBy('repartidor_id')
                ->having('dias', '>=', 3)
                ->get();

            $repartidores = Repartidor::whereIn('id', $stats->pluck('repartidor_id'))
                ->get()
                ->keyBy('id');

            $stats = $stats->map(fn ($s) => [
                'repartidor' => $repartidores[$s->repartidor_id] ?? null,
                'dias' => $s->dias,
                'promedio' => round($s->promedio, 1),
                'total_entregado' => $s->total_entregado,
            ])->filter(fn ($s) => $s['repartidor'] !== null);

            return [
                'top' => $stats->sortByDesc('promedio')->take(5)->values(),
                'bottom' => $stats->sortBy('promedio')->take(5)->values(),
                'tieneData' => $stats->isNotEmpty(),
            ];
        });
    }
}
