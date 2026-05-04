<?php

use App\Enums\PatronCruce;
use App\Enums\StatusImport;
use App\Filament\Pages\Analitica;
use App\Models\Cruce;
use App\Models\PinitImport;
use App\Models\Repartidor;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 5, 15, 10, 0, 0, 'America/Bogota'));
    $this->admin = User::factory()->create();
    $this->actingAs($this->admin);
});

afterEach(function () {
    Carbon::setTestNow();
});

function crearImportAnalitica(): PinitImport
{
    return PinitImport::create([
        'archivo_path' => 'test.xlsx',
        'fecha_archivo' => '2026-05-10',
        'total_filas' => 5,
        'status' => StatusImport::Done,
        'importado_por' => User::factory()->create()->id,
    ]);
}

it('analitica page loads', function () {
    Livewire::test(Analitica::class)->assertOk();
});

it('lists only repartidores with cruces', function () {
    $conCruces = Repartidor::factory()->create(['nombre' => 'Con Cruces']);
    $sinCruces = Repartidor::factory()->create(['nombre' => 'Sin Cruces']);

    $import = crearImportAnalitica();
    Cruce::create([
        'fecha_operacion' => '2026-05-10',
        'repartidor_id' => $conCruces->id,
        'reservado' => 30,
        'asignado' => 30,
        'entregado' => 28,
        'cumplimiento_pct' => 93.33,
        'patron' => PatronCruce::Consistente,
        'pinit_import_id' => $import->id,
    ]);

    $component = Livewire::test(Analitica::class);
    $repartidores = $component->instance()->repartidores;

    expect($repartidores->pluck('id')->toArray())->toContain($conCruces->id);
    expect($repartidores->pluck('id')->toArray())->not->toContain($sinCruces->id);
});

it('filters cruces by selected repartidor and rango', function () {
    $rep = Repartidor::factory()->create();
    $otroRep = Repartidor::factory()->create();
    $import = crearImportAnalitica();

    // Cruce dentro de rango (7 dias)
    Cruce::create([
        'fecha_operacion' => '2026-05-12',
        'repartidor_id' => $rep->id,
        'reservado' => 30,
        'asignado' => 30,
        'entregado' => 28,
        'cumplimiento_pct' => 93.33,
        'patron' => PatronCruce::Consistente,
        'pinit_import_id' => $import->id,
    ]);

    // Cruce fuera de rango (mas de 7 dias)
    Cruce::create([
        'fecha_operacion' => '2026-05-01',
        'repartidor_id' => $rep->id,
        'reservado' => 30,
        'asignado' => 30,
        'entregado' => 20,
        'cumplimiento_pct' => 66.67,
        'patron' => PatronCruce::SobreReserva,
        'pinit_import_id' => $import->id,
    ]);

    // Cruce de otro repartidor
    Cruce::create([
        'fecha_operacion' => '2026-05-12',
        'repartidor_id' => $otroRep->id,
        'reservado' => 30,
        'asignado' => 30,
        'entregado' => 15,
        'cumplimiento_pct' => 50.0,
        'patron' => PatronCruce::SobreReserva,
        'pinit_import_id' => $import->id,
    ]);

    $component = Livewire::test(Analitica::class)
        ->set('repartidorId', $rep->id)
        ->set('diasMirar', 7);

    $cruces = $component->instance()->crucesRepartidor;
    expect($cruces)->toHaveCount(1);
    expect($cruces->first()->repartidor_id)->toBe($rep->id);
    expect($cruces->first()->fecha_operacion->format('Y-m-d'))->toBe('2026-05-12');
});

it('resumen calcula correctamente', function () {
    $rep = Repartidor::factory()->create();
    $import = crearImportAnalitica();

    $cumplimientos = [80.0, 90.0, 100.0, 70.0, 95.0];
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

    $component = Livewire::test(Analitica::class)
        ->set('repartidorId', $rep->id)
        ->set('diasMirar', 30);

    $resumen = $component->instance()->resumen;
    expect($resumen['total_dias'])->toBe(5);
    // Promedio: (80+90+100+70+95)/5 = 87.0
    expect($resumen['cumplimiento_promedio'])->toBe(87.0);
});
