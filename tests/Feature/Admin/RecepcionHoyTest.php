<?php

use App\Enums\EstadoReserva;
use App\Filament\Pages\RecepcionHoy;
use App\Models\Asignacion;
use App\Models\Repartidor;
use App\Models\Reserva;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 5, 15, 9, 0, 0, 'America/Bogota'));
    $this->admin = User::factory()->create();
    $this->actingAs($this->admin);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('loads only today active reservas', function () {
    $hoy = Carbon::create(2026, 5, 15)->toDateString();
    $manana = Carbon::create(2026, 5, 16)->toDateString();
    $repartidor = Repartidor::factory()->create();

    Reserva::factory()->create([
        'repartidor_id' => $repartidor->id,
        'fecha_operacion' => $hoy,
        'estado' => EstadoReserva::Activa,
    ]);
    Reserva::factory()->create([
        'fecha_operacion' => $hoy,
        'estado' => EstadoReserva::Cancelada,
    ]);
    Reserva::factory()->create([
        'fecha_operacion' => $manana,
        'estado' => EstadoReserva::Activa,
    ]);

    $component = Livewire::test(RecepcionHoy::class);
    expect($component->get('filas'))->toHaveCount(1);
});

it('requires motivo when ajuste differs from reservado', function () {
    $hoy = Carbon::create(2026, 5, 15)->toDateString();
    $repartidor = Repartidor::factory()->create();

    Reserva::factory()->create([
        'repartidor_id' => $repartidor->id,
        'fecha_operacion' => $hoy,
        'paquetes' => 30,
        'estado' => EstadoReserva::Activa,
    ]);

    $component = Livewire::test(RecepcionHoy::class);
    $component->set('filas.0.asignado', 25);
    $component->set('filas.0.motivo', '');
    $component->call('guardarFila', 0);

    expect(Asignacion::count())->toBe(0);
});

it('creates asignacion with motivo when there is ajuste', function () {
    $hoy = Carbon::create(2026, 5, 15)->toDateString();
    $repartidor = Repartidor::factory()->create();

    $reserva = Reserva::factory()->create([
        'repartidor_id' => $repartidor->id,
        'fecha_operacion' => $hoy,
        'paquetes' => 30,
        'estado' => EstadoReserva::Activa,
    ]);

    $component = Livewire::test(RecepcionHoy::class);
    $component->set('filas.0.asignado', 25);
    $component->set('filas.0.motivo', 'Llegó menos mercancía');
    $component->call('guardarFila', 0);

    $asig = Asignacion::where('reserva_id', $reserva->id)->first();
    expect($asig)->not->toBeNull();
    expect($asig->paquetes_asignados)->toBe(25);
    expect($asig->ajuste_motivo)->toBe('Llegó menos mercancía');
    expect($asig->ajustado_por)->toBe($this->admin->id);
});

it('creates asignacion without motivo when no ajuste', function () {
    $hoy = Carbon::create(2026, 5, 15)->toDateString();
    $repartidor = Repartidor::factory()->create();

    $reserva = Reserva::factory()->create([
        'repartidor_id' => $repartidor->id,
        'fecha_operacion' => $hoy,
        'paquetes' => 30,
        'estado' => EstadoReserva::Activa,
    ]);

    $component = Livewire::test(RecepcionHoy::class);
    $component->set('filas.0.asignado', 30);
    $component->set('filas.0.motivo', '');
    $component->call('guardarFila', 0);

    $asig = Asignacion::where('reserva_id', $reserva->id)->first();
    expect($asig)->not->toBeNull();
    expect($asig->paquetes_asignados)->toBe(30);
    expect($asig->ajuste_motivo)->toBeNull();
});

it('sets entregado_a_repartidor_at when toggle is on', function () {
    $hoy = Carbon::create(2026, 5, 15)->toDateString();
    $repartidor = Repartidor::factory()->create();

    $reserva = Reserva::factory()->create([
        'repartidor_id' => $repartidor->id,
        'fecha_operacion' => $hoy,
        'paquetes' => 30,
        'estado' => EstadoReserva::Activa,
    ]);

    $component = Livewire::test(RecepcionHoy::class);
    $component->set('filas.0.entregado', true);
    $component->call('guardarFila', 0);

    $asig = Asignacion::where('reserva_id', $reserva->id)->first();
    expect($asig->entregado_a_repartidor_at)->not->toBeNull();
});

it('locks reserva when entregado is set', function () {
    $hoy = Carbon::create(2026, 5, 15)->toDateString();
    $repartidor = Repartidor::factory()->create();

    $reserva = Reserva::factory()->create([
        'repartidor_id' => $repartidor->id,
        'fecha_operacion' => $hoy,
        'paquetes' => 30,
        'estado' => EstadoReserva::Activa,
    ]);

    $component = Livewire::test(RecepcionHoy::class);
    $component->set('filas.0.entregado', true);
    $component->call('guardarFila', 0);

    $reserva->refresh();
    $reserva->load('asignacion');
    expect($reserva->isLocked())->toBeTrue();
});

it('logs activity for each save', function () {
    $hoy = Carbon::create(2026, 5, 15)->toDateString();
    $repartidor = Repartidor::factory()->create(['nombre' => 'Test Driver']);

    Reserva::factory()->create([
        'repartidor_id' => $repartidor->id,
        'fecha_operacion' => $hoy,
        'paquetes' => 30,
        'estado' => EstadoReserva::Activa,
    ]);

    $component = Livewire::test(RecepcionHoy::class);
    $component->set('filas.0.asignado', 25);
    $component->set('filas.0.motivo', 'Motivo test');
    $component->call('guardarFila', 0);

    $log = Activity::where('description', 'asignacion_guardada')->latest('id')->first();
    expect($log)->not->toBeNull();
    expect($log->properties['reservado'])->toBe(30);
    expect($log->properties['asignado'])->toBe(25);
    expect($log->properties['motivo'])->toBe('Motivo test');
    expect($log->properties['repartidor'])->toBe('Test Driver');
});

it('guardar todo skips rows with missing motivo', function () {
    $hoy = Carbon::create(2026, 5, 15)->toDateString();

    $r1 = Repartidor::factory()->create(['nombre' => 'Alpha']);
    $r2 = Repartidor::factory()->create(['nombre' => 'Bravo']);
    $r3 = Repartidor::factory()->create(['nombre' => 'Charlie']);

    $res1 = Reserva::factory()->create([
        'repartidor_id' => $r1->id, 'fecha_operacion' => $hoy, 'paquetes' => 30,
    ]);
    $res2 = Reserva::factory()->create([
        'repartidor_id' => $r2->id, 'fecha_operacion' => $hoy, 'paquetes' => 30,
    ]);
    $res3 = Reserva::factory()->create([
        'repartidor_id' => $r3->id, 'fecha_operacion' => $hoy, 'paquetes' => 30,
    ]);

    $component = Livewire::test(RecepcionHoy::class);

    // Fila 0 (Alpha) - con ajuste Y motivo
    $component->set('filas.0.asignado', 25);
    $component->set('filas.0.motivo', 'Ajuste OK');
    // Fila 1 (Bravo) - con ajuste SIN motivo
    $component->set('filas.1.asignado', 20);
    $component->set('filas.1.motivo', '');
    // Fila 2 (Charlie) - sin ajuste (mismo valor)
    $component->set('filas.2.asignado', 30);

    $component->call('guardarTodo');

    expect(Asignacion::count())->toBe(2);
    expect(Asignacion::where('reserva_id', $res1->id)->exists())->toBeTrue();
    expect(Asignacion::where('reserva_id', $res2->id)->exists())->toBeFalse();
    expect(Asignacion::where('reserva_id', $res3->id)->exists())->toBeTrue();
});
