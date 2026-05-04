<?php

use App\Enums\PatronCruce;
use App\Enums\StatusImport;
use App\Models\Asignacion;
use App\Models\Cruce;
use App\Models\PinitImport;
use App\Models\PinitRuta;
use App\Models\Repartidor;
use App\Models\Reserva;
use App\Models\User;
use App\Services\CruceCalculator;
use App\Settings\UltimamillaSettings;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 5, 15, 9, 0, 0, 'America/Bogota'));
    $this->admin = User::factory()->create();
    $this->settings = app(UltimamillaSettings::class);
    $this->calculator = app(CruceCalculator::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

function createImport(string $fecha = '2026-05-02'): PinitImport
{
    return PinitImport::create([
        'archivo_path' => 'test.xlsx',
        'fecha_archivo' => $fecha,
        'total_filas' => 0,
        'status' => StatusImport::Done,
        'importado_por' => User::factory()->create()->id,
    ]);
}

function createRuta(PinitImport $import, Repartidor $repartidor, array $attrs = []): PinitRuta
{
    return PinitRuta::create(array_merge([
        'pinit_import_id' => $import->id,
        'id_ruta_pinit' => 'RT-'.fake()->unique()->randomNumber(4),
        'cedula' => $repartidor->cedula,
        'nombre_operador' => $repartidor->nombre,
        'ciudad_parseada' => $repartidor->ciudad?->value,
        'placa' => null,
        'repartidor_id' => $repartidor->id,
        'status' => 'Completada',
        'total' => 30,
        'entregados' => 28,
        'porcentaje' => 93.33,
        'excepciones' => [],
        'tiempos' => [],
        'performance' => [],
        'es_devolucion' => false,
    ], $attrs));
}

it('creates cruce when reserva and asignacion exist', function () {
    $rep = Repartidor::factory()->create(['cupo_personalizado' => 30]);
    $import = createImport();

    createRuta($import, $rep, ['total' => 28, 'entregados' => 26]);

    $reserva = Reserva::factory()->create([
        'repartidor_id' => $rep->id,
        'fecha_operacion' => '2026-05-02',
        'paquetes' => 30,
    ]);
    Asignacion::create([
        'reserva_id' => $reserva->id,
        'paquetes_asignados' => 28,
        'ajustado_por' => $this->admin->id,
    ]);

    $count = $this->calculator->calcularParaImport($import);

    expect($count)->toBe(1);
    $cruce = Cruce::first();
    expect($cruce->reservado)->toBe(30);
    expect($cruce->asignado)->toBe(28);
    expect($cruce->entregado)->toBe(26);
    expect((float) $cruce->cumplimiento_pct)->toBeGreaterThan(92.0)->toBeLessThan(93.0);
    expect($cruce->patron)->toBe(PatronCruce::Consistente);
});

it('sums multiple rutas for same repartidor', function () {
    $rep = Repartidor::factory()->create(['cupo_personalizado' => 50]);
    $import = createImport();

    createRuta($import, $rep, ['total' => 20, 'entregados' => 18]);
    createRuta($import, $rep, ['total' => 30, 'entregados' => 25]);

    Reserva::factory()->create([
        'repartidor_id' => $rep->id,
        'fecha_operacion' => '2026-05-02',
        'paquetes' => 50,
    ]);

    $this->calculator->calcularParaImport($import);

    $cruce = Cruce::first();
    expect($cruce->entregado)->toBe(43);
    expect((float) $cruce->cumplimiento_pct)->toBe(86.0);
});

it('excludes devolucion rutas from cumplimiento', function () {
    $rep = Repartidor::factory()->create(['cupo_personalizado' => 30]);
    $import = createImport();

    createRuta($import, $rep, ['total' => 20, 'entregados' => 18, 'es_devolucion' => false]);
    createRuta($import, $rep, ['total' => 2, 'entregados' => 2, 'es_devolucion' => true]);

    Reserva::factory()->create([
        'repartidor_id' => $rep->id,
        'fecha_operacion' => '2026-05-02',
        'paquetes' => 20,
    ]);

    $this->calculator->calcularParaImport($import);

    $cruce = Cruce::first();
    // Devolucion no cuenta: entregado = 18 (no 20)
    expect($cruce->entregado)->toBe(18);
});

it('sets patron sub_reserva', function () {
    // cupo 30, reserva 15 (50% del cupo), entregado 15 sobre asignado 15 (100%)
    $rep = Repartidor::factory()->create(['cupo_personalizado' => 30]);
    $import = createImport();

    createRuta($import, $rep, ['total' => 15, 'entregados' => 15]);

    Reserva::factory()->create([
        'repartidor_id' => $rep->id,
        'fecha_operacion' => '2026-05-02',
        'paquetes' => 15,
    ]);

    $this->calculator->calcularParaImport($import);

    $cruce = Cruce::first();
    // cumplimiento = 15/15 = 100% >= consistente_min (0.90)
    // reservadoVsCupo = 15/30 = 50% < sub_reserva_cupo_max (0.70)
    expect($cruce->patron)->toBe(PatronCruce::SubReserva);
});

it('sets patron sobre_reserva', function () {
    // reserva 30, asignado 30, entregado 20 (66%)
    $rep = Repartidor::factory()->create(['cupo_personalizado' => 30]);
    $import = createImport();

    createRuta($import, $rep, ['total' => 30, 'entregados' => 20]);

    Reserva::factory()->create([
        'repartidor_id' => $rep->id,
        'fecha_operacion' => '2026-05-02',
        'paquetes' => 30,
    ]);

    $this->calculator->calcularParaImport($import);

    $cruce = Cruce::first();
    // cumplimiento = 20/30 = 0.66 < sobre_reserva_max (0.80)
    expect($cruce->patron)->toBe(PatronCruce::SobreReserva);
});

it('sets patron consistente', function () {
    // cupo 30, reserva 25 (83%), entregado 24 sobre asignado 25 (96%)
    $rep = Repartidor::factory()->create(['cupo_personalizado' => 30]);
    $import = createImport();

    createRuta($import, $rep, ['total' => 25, 'entregados' => 24]);

    Reserva::factory()->create([
        'repartidor_id' => $rep->id,
        'fecha_operacion' => '2026-05-02',
        'paquetes' => 25,
    ]);

    $this->calculator->calcularParaImport($import);

    $cruce = Cruce::first();
    // cumplimiento = 24/25 = 0.96 >= consistente_min (0.90)
    // reservadoVsCupo = 25/30 = 0.83 >= sub_reserva_cupo_max (0.70)
    expect($cruce->patron)->toBe(PatronCruce::Consistente);
});

it('returns null patron when no reserva', function () {
    $rep = Repartidor::factory()->create(['cupo_personalizado' => 30]);
    $import = createImport();

    createRuta($import, $rep, ['total' => 30, 'entregados' => 28]);
    // NO reserva for this repartidor on this date

    $this->calculator->calcularParaImport($import);

    $cruce = Cruce::first();
    expect($cruce->reservado)->toBe(0);
    expect($cruce->patron)->toBeNull();
});

it('ignores rutas with null repartidor_id', function () {
    $import = createImport();

    PinitRuta::create([
        'pinit_import_id' => $import->id,
        'id_ruta_pinit' => 'RT-UNKNOWN',
        'cedula' => '999999',
        'nombre_operador' => 'Desconocido',
        'ciudad_parseada' => null,
        'placa' => null,
        'repartidor_id' => null,
        'status' => 'Completada',
        'total' => 30,
        'entregados' => 28,
        'porcentaje' => 93.33,
        'excepciones' => [],
        'tiempos' => [],
        'performance' => [],
        'es_devolucion' => false,
    ]);

    $count = $this->calculator->calcularParaImport($import);

    expect($count)->toBe(0);
    expect(Cruce::count())->toBe(0);
});

it('deletes previous cruces on recalculation (idempotent)', function () {
    $rep = Repartidor::factory()->create(['cupo_personalizado' => 30]);
    $import = createImport();

    createRuta($import, $rep, ['total' => 30, 'entregados' => 25]);

    Reserva::factory()->create([
        'repartidor_id' => $rep->id,
        'fecha_operacion' => '2026-05-02',
        'paquetes' => 30,
    ]);

    $this->calculator->calcularParaImport($import);
    expect(Cruce::count())->toBe(1);

    // Run again
    $this->calculator->calcularParaImport($import);
    expect(Cruce::count())->toBe(1);
});
