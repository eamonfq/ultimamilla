<?php

use App\Enums\EstadoReserva;
use App\Livewire\Repartidor\Home;
use App\Models\Repartidor;
use App\Models\Reserva;
use App\Settings\UltimamillaSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 5, 15, 9, 0, 0, 'America/Bogota')); // viernes 9 AM
    $this->repartidor = Repartidor::factory()->create([
        'cedula' => '12345678',
        'pin' => '1234',
        'cupo_personalizado' => null,
    ]);
    $this->actingAs($this->repartidor, 'repartidor');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('shows 7 cards filtered by dias_operativos', function () {
    // dias_operativos = [1,2,3,4,5,6] (Lun-Sáb, sin domingo)
    // Hoy: viernes 15 mayo 2026
    // Esperado: sáb 16, lun 18, mar 19, mié 20, jue 21, vie 22, sáb 23
    $component = Livewire::test(Home::class);
    $cards = $component->viewData('cards');

    expect($cards)->toHaveCount(7);

    $fechas = collect($cards)->pluck('fecha_iso')->all();
    expect($fechas)->toBe([
        '2026-05-16', // sáb
        '2026-05-18', // lun
        '2026-05-19', // mar
        '2026-05-20', // mié
        '2026-05-21', // jue
        '2026-05-22', // vie
        '2026-05-23', // sáb
    ]);

    // Ninguna es domingo (dayOfWeek = 0)
    foreach ($cards as $card) {
        expect($card['fecha']->dayOfWeek)->not->toBe(0);
    }
});

it('highlights tomorrow card', function () {
    $component = Livewire::test(Home::class);
    $cards = $component->viewData('cards');

    // La primera card (sábado 16) es mañana
    expect($cards[0]['es_mañana'])->toBeTrue();
    expect($cards[1]['es_mañana'])->toBeFalse();
    expect($cards[2]['es_mañana'])->toBeFalse();
});

it('shows existing reserva on the corresponding card', function () {
    Reserva::create([
        'repartidor_id' => $this->repartidor->id,
        'fecha_operacion' => '2026-05-18',
        'paquetes' => 25,
        'rutas' => 2,
        'estado' => EstadoReserva::Activa,
    ]);

    $component = Livewire::test(Home::class);
    $cards = $component->viewData('cards');

    $lunCard = collect($cards)->firstWhere('fecha_iso', '2026-05-18');
    expect($lunCard['reserva'])->not->toBeNull()
        ->and($lunCard['reserva']['paquetes'])->toBe(25)
        ->and($lunCard['reserva']['rutas'])->toBe(2);

    $component->assertSee('Reservado: 25 paq.');
});

it('locks cards past deadline', function () {
    // hora_cierre_reservas = 12:00, now = viernes 15 a las 13:00
    Carbon::setTestNow(Carbon::create(2026, 5, 15, 13, 0, 0, 'America/Bogota'));

    $component = Livewire::test(Home::class);
    $cards = $component->viewData('cards');

    // Sábado 16: deadline fue viernes 15 a las 12:00 → ya pasó → bloqueada
    $sabCard = collect($cards)->firstWhere('fecha_iso', '2026-05-16');
    expect($sabCard['bloqueada'])->toBeTrue();

    // Lunes 18: deadline es domingo 17 a las 12:00 → aún no → desbloqueada
    $lunCard = collect($cards)->firstWhere('fecha_iso', '2026-05-18');
    expect($lunCard['bloqueada'])->toBeFalse();
});

it('allows creating a reserva via abrirModal + guardarReserva', function () {
    // Lunes 18 mayo 2026
    $timestamp = Carbon::create(2026, 5, 18, 0, 0, 0, 'America/Bogota')->timestamp;

    Livewire::test(Home::class)
        ->call('abrirModal', $timestamp)
        ->assertSet('modalAbierto', true)
        ->set('paquetes', 30)
        ->set('rutas', 2)
        ->call('guardarReserva')
        ->assertSet('modalAbierto', false)
        ->assertDispatched('notify', tipo: 'success', mensaje: '¡Reserva confirmada!');

    expect(Reserva::where('repartidor_id', $this->repartidor->id)
        ->where('fecha_operacion', '2026-05-18')
        ->where('paquetes', 30)
        ->where('rutas', 2)
        ->exists()
    )->toBeTrue();
});

it('edits existing reserva instead of duplicating', function () {
    $reserva = Reserva::create([
        'repartidor_id' => $this->repartidor->id,
        'fecha_operacion' => '2026-05-18',
        'paquetes' => 20,
        'rutas' => 1,
        'estado' => EstadoReserva::Activa,
    ]);

    $timestamp = Carbon::create(2026, 5, 18, 0, 0, 0, 'America/Bogota')->timestamp;

    Livewire::test(Home::class)
        ->call('abrirModal', $timestamp)
        ->assertSet('reservaIdEditando', $reserva->id)
        ->assertSet('paquetes', 20)
        ->set('paquetes', 35)
        ->call('guardarReserva')
        ->assertDispatched('notify', tipo: 'success', mensaje: 'Reserva actualizada.');

    expect(Reserva::where('repartidor_id', $this->repartidor->id)
        ->where('fecha_operacion', '2026-05-18')
        ->count()
    )->toBe(1);

    expect($reserva->fresh()->paquetes)->toBe(35);
});

it('cancels reserva', function () {
    $reserva = Reserva::create([
        'repartidor_id' => $this->repartidor->id,
        'fecha_operacion' => '2026-05-18',
        'paquetes' => 20,
        'rutas' => 1,
        'estado' => EstadoReserva::Activa,
    ]);

    $timestamp = Carbon::create(2026, 5, 18, 0, 0, 0, 'America/Bogota')->timestamp;

    Livewire::test(Home::class)
        ->call('abrirModal', $timestamp)
        ->call('cancelarReserva')
        ->assertSet('modalAbierto', false)
        ->assertDispatched('notify', tipo: 'info', mensaje: 'Reserva cancelada.');

    expect($reserva->fresh()->estado)->toBe(EstadoReserva::Cancelada);
});

it('rejects guardar past deadline', function () {
    // hora_cierre = 12:00, now = viernes 15 a las 13:00
    Carbon::setTestNow(Carbon::create(2026, 5, 15, 13, 0, 0, 'America/Bogota'));

    // Sábado 16: deadline fue viernes 15 a 12:00 → ya pasó
    $timestamp = Carbon::create(2026, 5, 16, 0, 0, 0, 'America/Bogota')->timestamp;

    Livewire::test(Home::class)
        ->call('abrirModal', $timestamp)
        ->assertSet('modalAbierto', false)
        ->assertDispatched('notify', tipo: 'error', mensaje: 'Ya pasó la hora de cierre para este día.');
});

it('warns when paquetes exceeds cupo but allows save', function () {
    // cupo_global_default = 30
    $timestamp = Carbon::create(2026, 5, 18, 0, 0, 0, 'America/Bogota')->timestamp;

    Livewire::test(Home::class)
        ->call('abrirModal', $timestamp)
        ->set('paquetes', 50)
        ->set('rutas', 1)
        ->call('guardarReserva')
        ->assertDispatched('notify', tipo: 'success', mensaje: '¡Reserva confirmada!');

    expect(Reserva::where('repartidor_id', $this->repartidor->id)
        ->where('paquetes', 50)
        ->exists()
    )->toBeTrue();
});

it('respects cupo personalizado over global', function () {
    $this->repartidor->update(['cupo_personalizado' => 50]);

    $component = Livewire::test(Home::class);
    $cupo = $component->viewData('cupoEfectivo');

    expect($cupo)->toBe(50);

    // Abrir modal — paquetes defaults to cupoEfectivo
    $timestamp = Carbon::create(2026, 5, 18, 0, 0, 0, 'America/Bogota')->timestamp;
    $component->call('abrirModal', $timestamp)
        ->assertSet('paquetes', 50);
});

it('logout redirects and clears session', function () {
    Livewire::test(Home::class)
        ->call('logout')
        ->assertRedirect(route('repartidor.login'));

    expect(Auth::guard('repartidor')->check())->toBeFalse();
});

it('never allows reservar on non-operational day', function () {
    // Cambiar settings: sin sábado (solo Lun-Vie = [1,2,3,4,5])
    $settings = app(UltimamillaSettings::class);
    $settings->dias_operativos = [1, 2, 3, 4, 5];
    $settings->save();

    // Intentar reservar un sábado (16 mayo 2026)
    $timestamp = Carbon::create(2026, 5, 16, 0, 0, 0, 'America/Bogota')->timestamp;

    Livewire::test(Home::class)
        ->call('abrirModal', $timestamp)
        ->assertSet('modalAbierto', false)
        ->assertDispatched('notify', tipo: 'error', mensaje: 'Ese día no es operativo.');
});

it('handles deadline boundary exactly at HH:MM:00', function () {
    // hora_cierre = 12:00, now = viernes 15 a las 12:00:00 exacto
    Carbon::setTestNow(Carbon::create(2026, 5, 15, 12, 0, 0, 'America/Bogota'));

    $component = Livewire::test(Home::class);
    $cards = $component->viewData('cards');

    // Sábado 16: deadline es viernes 15 a 12:00 → now >= deadline → bloqueada
    $sabCard = collect($cards)->firstWhere('fecha_iso', '2026-05-16');
    expect($sabCard['bloqueada'])->toBeTrue();
});

it('recomputes cards after settings change', function () {
    // hora_cierre = 12:00, now = viernes 15 a las 11:00 → sáb 16 desbloqueada
    Carbon::setTestNow(Carbon::create(2026, 5, 15, 11, 0, 0, 'America/Bogota'));

    $component = Livewire::test(Home::class);
    $cards = $component->viewData('cards');
    $sabCard = collect($cards)->firstWhere('fecha_iso', '2026-05-16');
    expect($sabCard['bloqueada'])->toBeFalse();

    // Cambiar settings a cierre 08:00 → deadline sáb 16 fue jueves 14 a 08:00 → ya pasó
    // Wait no: deadline for sáb 16 is viernes 15 a las 08:00 (subDay = 15, setTime 08:00)
    // now = 11:00 > 08:00 → bloqueada
    $settings = app(UltimamillaSettings::class);
    $settings->hora_cierre_reservas = '08:00';
    $settings->save();

    // Re-render
    $component2 = Livewire::test(Home::class);
    $cards2 = $component2->viewData('cards');
    $sabCard2 = collect($cards2)->firstWhere('fecha_iso', '2026-05-16');
    expect($sabCard2['bloqueada'])->toBeTrue();
});
