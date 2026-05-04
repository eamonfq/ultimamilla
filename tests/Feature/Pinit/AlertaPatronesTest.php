<?php

use App\Enums\PatronCruce;
use App\Enums\StatusImport;
use App\Models\Cruce;
use App\Models\PinitImport;
use App\Models\Repartidor;
use App\Models\User;
use App\Services\AlertaPatrones;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 5, 15, 10, 0, 0, 'America/Bogota'));
    $this->repartidor = Repartidor::factory()->create(['cedula' => '12345678']);
    $this->servicio = app(AlertaPatrones::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

function crearImportParaTest(): PinitImport
{
    return PinitImport::create([
        'archivo_path' => 'test.xlsx',
        'fecha_archivo' => '2026-05-10',
        'total_filas' => 0,
        'status' => StatusImport::Done,
        'importado_por' => User::factory()->create()->id,
    ]);
}

function crearCruce($repartidor, string $fecha, ?PatronCruce $patron, float $cumplimiento = 95.0): Cruce
{
    $import = PinitImport::first() ?? crearImportParaTest();

    return Cruce::create([
        'fecha_operacion' => $fecha,
        'repartidor_id' => $repartidor->id,
        'reservado' => 30,
        'asignado' => 30,
        'entregado' => (int) ($cumplimiento * 0.3),
        'cumplimiento_pct' => $cumplimiento,
        'patron' => $patron,
        'pinit_import_id' => $import->id,
    ]);
}

it('detects 3 consecutive sobre_reserva days', function () {
    crearCruce($this->repartidor, '2026-05-10', PatronCruce::SobreReserva, 65.0);
    crearCruce($this->repartidor, '2026-05-11', PatronCruce::SobreReserva, 60.0);
    crearCruce($this->repartidor, '2026-05-12', PatronCruce::SobreReserva, 55.0);

    $alertas = $this->servicio->detectar();

    expect($alertas)->toHaveCount(1);
    $caso = $alertas->first();
    $tipos = collect($caso['alertas'])->pluck('tipo');
    expect($tipos)->toContain('sobre_reserva_consecutivo');

    $alerta = collect($caso['alertas'])->firstWhere('tipo', 'sobre_reserva_consecutivo');
    expect($alerta['severidad'])->toBe(2);
});

it('detects 5 consecutive as severidad 3', function () {
    crearCruce($this->repartidor, '2026-05-08', PatronCruce::SobreReserva, 65.0);
    crearCruce($this->repartidor, '2026-05-09', PatronCruce::SobreReserva, 60.0);
    crearCruce($this->repartidor, '2026-05-10', PatronCruce::SobreReserva, 55.0);
    crearCruce($this->repartidor, '2026-05-11', PatronCruce::SobreReserva, 50.0);
    crearCruce($this->repartidor, '2026-05-12', PatronCruce::SobreReserva, 45.0);

    $alertas = $this->servicio->detectar();

    $caso = $alertas->first();
    $alerta = collect($caso['alertas'])->firstWhere('tipo', 'sobre_reserva_consecutivo');
    expect($alerta['severidad'])->toBe(3);
});

it('does NOT alert with only 2 consecutive sobre_reserva', function () {
    crearCruce($this->repartidor, '2026-05-11', PatronCruce::SobreReserva, 65.0);
    crearCruce($this->repartidor, '2026-05-12', PatronCruce::SobreReserva, 60.0);

    $alertas = $this->servicio->detectar();

    $alertasConsecutivo = $alertas->flatMap(fn ($caso) => collect($caso['alertas']))
        ->where('tipo', 'sobre_reserva_consecutivo');
    expect($alertasConsecutivo)->toHaveCount(0);
});

it('resets counter when there is a non-sobre_reserva day in between', function () {
    crearCruce($this->repartidor, '2026-05-08', PatronCruce::SobreReserva, 65.0);
    crearCruce($this->repartidor, '2026-05-09', PatronCruce::SobreReserva, 60.0);
    crearCruce($this->repartidor, '2026-05-10', PatronCruce::Consistente, 95.0);
    crearCruce($this->repartidor, '2026-05-11', PatronCruce::SobreReserva, 55.0);
    crearCruce($this->repartidor, '2026-05-12', PatronCruce::SobreReserva, 50.0);

    $alertas = $this->servicio->detectar();

    $alertasConsecutivo = $alertas->flatMap(fn ($caso) => collect($caso['alertas']))
        ->where('tipo', 'sobre_reserva_consecutivo');
    expect($alertasConsecutivo)->toHaveCount(0);
});

it('detects sustained low cumplimiento', function () {
    crearCruce($this->repartidor, '2026-05-06', PatronCruce::SobreReserva, 55.0);
    crearCruce($this->repartidor, '2026-05-07', PatronCruce::SobreReserva, 60.0);
    crearCruce($this->repartidor, '2026-05-08', PatronCruce::SobreReserva, 65.0);
    crearCruce($this->repartidor, '2026-05-09', PatronCruce::SobreReserva, 50.0);
    crearCruce($this->repartidor, '2026-05-10', PatronCruce::SobreReserva, 58.0);

    $alertas = $this->servicio->detectar();

    $caso = $alertas->first();
    $alerta = collect($caso['alertas'])->firstWhere('tipo', 'cumplimiento_sostenido_bajo');
    expect($alerta)->not->toBeNull();
    // promedio = (55+60+65+50+58)/5 = 57.6 < 60, severidad 3
    expect($alerta['severidad'])->toBe(3);
});

it('does NOT alert with only 4 low cruces', function () {
    crearCruce($this->repartidor, '2026-05-08', PatronCruce::SobreReserva, 55.0);
    crearCruce($this->repartidor, '2026-05-09', PatronCruce::SobreReserva, 60.0);
    crearCruce($this->repartidor, '2026-05-10', PatronCruce::SobreReserva, 65.0);
    crearCruce($this->repartidor, '2026-05-11', PatronCruce::SobreReserva, 50.0);

    $alertas = $this->servicio->detectar();

    $alertasBajo = $alertas->flatMap(fn ($caso) => collect($caso['alertas']))
        ->where('tipo', 'cumplimiento_sostenido_bajo');
    expect($alertasBajo)->toHaveCount(0);
});

it('detects regresion: consistente last week, sobre_reserva this week', function () {
    // Semana pasada (hace 8-12 dias): consistente
    crearCruce($this->repartidor, '2026-05-03', PatronCruce::Consistente, 95.0);
    crearCruce($this->repartidor, '2026-05-04', PatronCruce::Consistente, 92.0);
    crearCruce($this->repartidor, '2026-05-05', PatronCruce::Consistente, 94.0);
    crearCruce($this->repartidor, '2026-05-06', PatronCruce::Consistente, 91.0);

    // Esta semana (ultimos 7 dias): sobre_reserva
    crearCruce($this->repartidor, '2026-05-09', PatronCruce::SobreReserva, 65.0);
    crearCruce($this->repartidor, '2026-05-10', PatronCruce::SobreReserva, 60.0);
    crearCruce($this->repartidor, '2026-05-11', PatronCruce::SobreReserva, 55.0);

    $alertas = $this->servicio->detectar();

    $caso = $alertas->first();
    $alerta = collect($caso['alertas'])->firstWhere('tipo', 'regresion');
    expect($alerta)->not->toBeNull();
    expect($alerta['severidad'])->toBe(2);
});

it('does NOT alert when both weeks are consistente', function () {
    crearCruce($this->repartidor, '2026-05-03', PatronCruce::Consistente, 95.0);
    crearCruce($this->repartidor, '2026-05-04', PatronCruce::Consistente, 92.0);
    crearCruce($this->repartidor, '2026-05-05', PatronCruce::Consistente, 94.0);
    crearCruce($this->repartidor, '2026-05-06', PatronCruce::Consistente, 91.0);
    crearCruce($this->repartidor, '2026-05-09', PatronCruce::Consistente, 93.0);
    crearCruce($this->repartidor, '2026-05-10', PatronCruce::Consistente, 96.0);
    crearCruce($this->repartidor, '2026-05-11', PatronCruce::Consistente, 90.0);

    $alertas = $this->servicio->detectar();

    $alertasRegresion = $alertas->flatMap(fn ($caso) => collect($caso['alertas']))
        ->where('tipo', 'regresion');
    expect($alertasRegresion)->toHaveCount(0);
});

it('ignores inactive repartidores', function () {
    $inactivo = Repartidor::factory()->inactivo()->create();

    crearCruce($inactivo, '2026-05-10', PatronCruce::SobreReserva, 65.0);
    crearCruce($inactivo, '2026-05-11', PatronCruce::SobreReserva, 60.0);
    crearCruce($inactivo, '2026-05-12', PatronCruce::SobreReserva, 55.0);

    $alertas = $this->servicio->detectar();

    expect($alertas)->toHaveCount(0);
});

it('returns multiple alerts for same repartidor', function () {
    // 5 dias consecutivos sobre_reserva con cumplimiento < 70
    crearCruce($this->repartidor, '2026-05-08', PatronCruce::SobreReserva, 55.0);
    crearCruce($this->repartidor, '2026-05-09', PatronCruce::SobreReserva, 60.0);
    crearCruce($this->repartidor, '2026-05-10', PatronCruce::SobreReserva, 58.0);
    crearCruce($this->repartidor, '2026-05-11', PatronCruce::SobreReserva, 52.0);
    crearCruce($this->repartidor, '2026-05-12', PatronCruce::SobreReserva, 50.0);

    $alertas = $this->servicio->detectar();

    $caso = $alertas->first();
    // Debe tener al menos 2 alertas: sobre_reserva_consecutivo y cumplimiento_sostenido_bajo
    expect(count($caso['alertas']))->toBeGreaterThanOrEqual(2);

    $tipos = collect($caso['alertas'])->pluck('tipo');
    expect($tipos)->toContain('sobre_reserva_consecutivo');
    expect($tipos)->toContain('cumplimiento_sostenido_bajo');

    // Severidad max = 3 (consecutivo >= 5 dias = 3, cumplimiento promedio < 60 = 3)
    expect($caso['severidad'])->toBe(3);
});
