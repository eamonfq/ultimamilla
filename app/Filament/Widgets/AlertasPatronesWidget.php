<?php

namespace App\Filament\Widgets;

use App\Services\AlertaPatrones;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

class AlertasPatronesWidget extends Widget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.alertas-patrones';

    public static function canView(): bool
    {
        return static::cargarAlertas()->isNotEmpty();
    }

    public function getViewData(): array
    {
        return ['alertas' => static::cargarAlertas()];
    }

    private static function cargarAlertas()
    {
        return Cache::remember('alertas_patrones', now()->addMinutes(5), function () {
            return app(AlertaPatrones::class)->detectar();
        });
    }
}
