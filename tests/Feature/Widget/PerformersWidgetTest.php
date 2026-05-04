<?php

use App\Enums\PatronCruce;
use App\Enums\StatusImport;
use App\Filament\Widgets\TopBottomPerformersWidget;
use App\Models\Cruce;
use App\Models\PinitImport;
use App\Models\Repartidor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 5, 15, 10, 0, 0, 'America/Bogota'));
    $this->admin = User::factory()->create();
    $this->actingAs($this->admin);
    Cache::flush();
});

afterEach(function () {
    Carbon::setTestNow();
});

function crearImportPerformers(): PinitImport
{
    return PinitImport::create([
        'archivo_path' => 'test.xlsx',
        'fecha_archivo' => '2026-05-10',
        'total_filas' => 5,
        'status' => StatusImport::Done,
        'importado_por' => User::factory()->create()->id,
    ]);
}

function crearCrucesParaRepartidor(Repartidor $rep, PinitImport $import, array $cumplimientos): void
{
    foreach ($cumplimientos as $i => $pct) {
        Cruce::create([
            'fecha_operacion' => Carbon::parse('2026-05-10')->addDays($i),
            'repartidor_id' => $rep->id,
            'reservado' => 30,
            'asignado' => 30,
            'entregado' => (int) ($pct * 0.3),
            'cumplimiento_pct' => $pct,
            'patron' => $pct >= 90 ? PatronCruce::Consistente : PatronCruce::SobreReserva,
            'pinit_import_id' => $import->id,
        ]);
    }
}

it('requires minimum 3 cruces to enter ranking', function () {
    $rep = Repartidor::factory()->create();
    $import = crearImportPerformers();

    // Solo 2 cruces con 100% - no debe aparecer
    crearCrucesParaRepartidor($rep, $import, [100.0, 100.0]);

    $widget = Livewire::test(TopBottomPerformersWidget::class);
    $data = $widget->instance()->getViewData();

    expect($data['tieneData'])->toBeFalse();
});

it('ranks by avg cumplimiento desc for top', function () {
    $import = crearImportPerformers();

    $rep1 = Repartidor::factory()->create(['nombre' => 'Top']);
    crearCrucesParaRepartidor($rep1, $import, [95.0, 96.0, 94.0]);

    $rep2 = Repartidor::factory()->create(['nombre' => 'Mid']);
    crearCrucesParaRepartidor($rep2, $import, [80.0, 82.0, 78.0]);

    $rep3 = Repartidor::factory()->create(['nombre' => 'Low']);
    crearCrucesParaRepartidor($rep3, $import, [70.0, 72.0, 68.0]);

    $widget = Livewire::test(TopBottomPerformersWidget::class);
    $data = $widget->instance()->getViewData();

    expect($data['tieneData'])->toBeTrue();
    expect($data['top']->first()['repartidor']->nombre)->toBe('Top');
    expect($data['top'][1]['repartidor']->nombre)->toBe('Mid');
});

it('ranks by avg cumplimiento asc for bottom', function () {
    $import = crearImportPerformers();

    $rep1 = Repartidor::factory()->create(['nombre' => 'Top']);
    crearCrucesParaRepartidor($rep1, $import, [95.0, 96.0, 94.0]);

    $rep2 = Repartidor::factory()->create(['nombre' => 'Mid']);
    crearCrucesParaRepartidor($rep2, $import, [80.0, 82.0, 78.0]);

    $rep3 = Repartidor::factory()->create(['nombre' => 'Low']);
    crearCrucesParaRepartidor($rep3, $import, [70.0, 72.0, 68.0]);

    $widget = Livewire::test(TopBottomPerformersWidget::class);
    $data = $widget->instance()->getViewData();

    expect($data['bottom']->first()['repartidor']->nombre)->toBe('Low');
    expect($data['bottom'][1]['repartidor']->nombre)->toBe('Mid');
});

it('returns empty when no data', function () {
    $widget = Livewire::test(TopBottomPerformersWidget::class);
    $data = $widget->instance()->getViewData();

    expect($data['tieneData'])->toBeFalse();
    expect($data['top'])->toBeEmpty();
    expect($data['bottom'])->toBeEmpty();
});
