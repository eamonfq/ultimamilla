<?php

use App\Filament\Widgets\CapacidadMananaWidget;
use App\Filament\Widgets\RepartidoresFaltanReservarWidget;
use App\Models\Repartidor;
use App\Models\Reserva;
use App\Models\User;
use App\Settings\UltimamillaSettings;
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

it('shows total paquetes reservados for tomorrow', function () {
    $manana = Carbon::create(2026, 5, 16, 0, 0, 0, 'America/Bogota');

    Reserva::factory()->create(['paquetes' => 30, 'fecha_operacion' => $manana]);
    Reserva::factory()->create(['paquetes' => 25, 'fecha_operacion' => $manana]);
    Reserva::factory()->create(['paquetes' => 20, 'fecha_operacion' => $manana]);

    Livewire::test(CapacidadMananaWidget::class)
        ->assertSee('75');
});

it('counts repartidores who reserved for tomorrow vs total active', function () {
    $manana = Carbon::create(2026, 5, 16, 0, 0, 0, 'America/Bogota');

    $r1 = Repartidor::factory()->create();
    $r2 = Repartidor::factory()->create();
    $r3 = Repartidor::factory()->create();

    Reserva::factory()->create(['repartidor_id' => $r1->id, 'fecha_operacion' => $manana]);
    Reserva::factory()->create(['repartidor_id' => $r2->id, 'fecha_operacion' => $manana]);

    Livewire::test(CapacidadMananaWidget::class)
        ->assertSee('2 de 3');
});

it('shows green when umbral met', function () {
    $manana = Carbon::create(2026, 5, 16, 0, 0, 0, 'America/Bogota');
    $settings = app(UltimamillaSettings::class);
    $settings->umbral_minimo_diario = 50;
    $settings->save();

    Reserva::factory()->create(['paquetes' => 75, 'fecha_operacion' => $manana]);

    Livewire::test(CapacidadMananaWidget::class)
        ->assertSee('Cubierto');
});

it('shows red when umbral not met', function () {
    $manana = Carbon::create(2026, 5, 16, 0, 0, 0, 'America/Bogota');
    $settings = app(UltimamillaSettings::class);
    $settings->umbral_minimo_diario = 100;
    $settings->save();

    Reserva::factory()->create(['paquetes' => 75, 'fecha_operacion' => $manana]);

    Livewire::test(CapacidadMananaWidget::class)
        ->assertSee('Faltan 25');
});

it('hides umbral widget when umbral is zero', function () {
    $settings = app(UltimamillaSettings::class);
    $settings->umbral_minimo_diario = 0;
    $settings->save();

    Livewire::test(CapacidadMananaWidget::class)
        ->assertDontSee('Umbral');
});

it('repartidores faltan widget lists those without reservations', function () {
    $manana = Carbon::create(2026, 5, 16, 0, 0, 0, 'America/Bogota');

    $r1 = Repartidor::factory()->create(['nombre' => 'Alpha']);
    $r2 = Repartidor::factory()->create(['nombre' => 'Bravo']);
    $r3 = Repartidor::factory()->create(['nombre' => 'Charlie']);

    Reserva::factory()->create(['repartidor_id' => $r1->id, 'fecha_operacion' => $manana]);

    Livewire::test(RepartidoresFaltanReservarWidget::class)
        ->assertCanSeeTableRecords([$r2, $r3])
        ->assertCanNotSeeTableRecords([$r1]);
});
