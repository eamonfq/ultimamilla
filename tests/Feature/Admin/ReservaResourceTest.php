<?php

use App\Enums\EstadoReserva;
use App\Filament\Pages\RecepcionHoy;
use App\Filament\Resources\ReservaResource\Pages\CreateReserva;
use App\Filament\Resources\ReservaResource\Pages\ListReservas;
use App\Models\Repartidor;
use App\Models\Reserva;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 5, 15, 9, 0, 0, 'America/Bogota'));
    $this->admin = User::factory()->create();
    $this->actingAs($this->admin);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('admin can create reserva on behalf of repartidor', function () {
    $repartidor = Repartidor::factory()->create();
    $manana = Carbon::create(2026, 5, 16)->toDateString();

    Livewire::test(CreateReserva::class)
        ->fillForm([
            'repartidor_id' => $repartidor->id,
            'fecha_operacion' => $manana,
            'paquetes' => 30,
            'rutas' => 2,
            'estado' => EstadoReserva::Activa->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Reserva::query()
        ->where('repartidor_id', $repartidor->id)
        ->where('fecha_operacion', $manana)
        ->where('paquetes', 30)
        ->where('rutas', 2)
        ->exists()
    )->toBeTrue();
});

it('cannot create duplicate active reserva for same repartidor and fecha', function () {
    $repartidor = Repartidor::factory()->create();
    $manana = Carbon::create(2026, 5, 16)->toDateString();

    Reserva::factory()->create([
        'repartidor_id' => $repartidor->id,
        'fecha_operacion' => $manana,
        'estado' => EstadoReserva::Activa,
    ]);

    Livewire::test(CreateReserva::class)
        ->fillForm([
            'repartidor_id' => $repartidor->id,
            'fecha_operacion' => $manana,
            'paquetes' => 20,
            'rutas' => 1,
            'estado' => EstadoReserva::Activa->value,
        ])
        ->call('create')
        ->assertNotified('Reserva duplicada');
});

it('admin can cancel reserva via action', function () {
    $reserva = Reserva::factory()->create([
        'estado' => EstadoReserva::Activa,
        'fecha_operacion' => Carbon::create(2026, 5, 16),
    ]);

    Livewire::test(ListReservas::class)
        ->callTableAction('cancelar', $reserva)
        ->assertSuccessful();

    $reserva->refresh();
    expect($reserva->estado)->toBe(EstadoReserva::Cancelada);
});

it('cancelled reservas dont appear in recepcion-hoy', function () {
    $hoy = Carbon::create(2026, 5, 15)->toDateString();
    $repartidor = Repartidor::factory()->create();

    $reserva = Reserva::factory()->create([
        'repartidor_id' => $repartidor->id,
        'fecha_operacion' => $hoy,
        'estado' => EstadoReserva::Activa,
    ]);

    $reserva->update(['estado' => EstadoReserva::Cancelada]);

    Livewire::test(RecepcionHoy::class)
        ->assertSet('filas', []);
});

it('locked reservas show lock icon in table', function () {
    $reserva = Reserva::factory()->locked()->create([
        'fecha_operacion' => Carbon::create(2026, 5, 16),
    ]);

    Livewire::test(ListReservas::class)
        ->assertCanSeeTableRecords([$reserva]);
});
