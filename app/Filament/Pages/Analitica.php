<?php

namespace App\Filament\Pages;

use App\Enums\PatronCruce;
use App\Models\Cruce;
use App\Models\Repartidor;
use Filament\Pages\Page;

class Analitica extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'Pinit';

    protected static ?int $navigationSort = 25;

    protected static ?string $title = 'Analitica de cumplimiento';

    protected static ?string $navigationLabel = 'Analitica';

    protected string $view = 'filament.pages.analitica';

    public ?int $repartidorId = null;

    public int $diasMirar = 30;

    public function mount(): void
    {
        $this->repartidorId = request()->integer('repartidor_id') ?: null;
    }

    public function getRepartidoresProperty()
    {
        return Repartidor::query()
            ->activos()
            ->whereHas('cruces')
            ->orderBy('nombre')
            ->get();
    }

    public function getRepartidorSeleccionadoProperty(): ?Repartidor
    {
        if (! $this->repartidorId) {
            return null;
        }

        return Repartidor::find($this->repartidorId);
    }

    public function getCrucesRepartidorProperty()
    {
        if (! $this->repartidorId) {
            return collect();
        }

        $hoy = today('America/Bogota');
        $desde = $hoy->copy()->subDays($this->diasMirar);

        return Cruce::query()
            ->where('repartidor_id', $this->repartidorId)
            ->whereBetween('fecha_operacion', [$desde, $hoy])
            ->orderBy('fecha_operacion', 'desc')
            ->get();
    }

    public function getResumenProperty(): array
    {
        $cruces = $this->crucesRepartidor;

        if ($cruces->isEmpty()) {
            return [
                'total_dias' => 0,
                'cumplimiento_promedio' => null,
                'reservado_total' => 0,
                'asignado_total' => 0,
                'entregado_total' => 0,
                'distribucion' => [],
            ];
        }

        return [
            'total_dias' => $cruces->count(),
            'cumplimiento_promedio' => round($cruces->whereNotNull('cumplimiento_pct')->avg('cumplimiento_pct') ?? 0, 1),
            'reservado_total' => $cruces->sum('reservado'),
            'asignado_total' => $cruces->sum('asignado'),
            'entregado_total' => $cruces->sum('entregado'),
            'distribucion' => [
                'consistente' => $cruces->where('patron', PatronCruce::Consistente)->count(),
                'sub_reserva' => $cruces->where('patron', PatronCruce::SubReserva)->count(),
                'sobre_reserva' => $cruces->where('patron', PatronCruce::SobreReserva)->count(),
                'sin_patron' => $cruces->whereNull('patron')->count(),
            ],
        ];
    }

    public function getDatosGraficoProperty(): array
    {
        $cruces = $this->crucesRepartidor->sortBy('fecha_operacion');

        return [
            'labels' => $cruces->pluck('fecha_operacion')->map(fn ($f) => $f->locale('es')->isoFormat('D MMM'))->values()->all(),
            'cumplimiento' => $cruces->pluck('cumplimiento_pct')->values()->all(),
            'entregados' => $cruces->pluck('entregado')->values()->all(),
            'reservados' => $cruces->pluck('reservado')->values()->all(),
        ];
    }
}
