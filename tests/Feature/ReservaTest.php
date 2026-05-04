<?php

use App\Models\Repartidor;
use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Database\QueryException;

it('enforces unique reserva per repartidor and fecha', function () {
    $repartidor = Repartidor::factory()->create();
    $fecha = Carbon::tomorrow('America/Bogota');

    Reserva::factory()->create([
        'repartidor_id' => $repartidor->id,
        'fecha_operacion' => $fecha,
    ]);

    Reserva::factory()->create([
        'repartidor_id' => $repartidor->id,
        'fecha_operacion' => $fecha,
    ]);
})->throws(QueryException::class);

it('considers reserva locked when locked_at is set', function () {
    $reserva = Reserva::factory()->create([
        'locked_at' => now('America/Bogota'),
    ]);

    expect($reserva->isLocked())->toBeTrue();
});

it('considers reserva locked when current time is past deadline', function () {
    // fecha_operacion = hoy => deadline fue ayer a las 12:00, ya paso
    $reserva = Reserva::factory()->create([
        'fecha_operacion' => Carbon::today('America/Bogota'),
    ]);

    expect($reserva->isLocked())->toBeTrue();
});

it('considers reserva NOT locked when before deadline', function () {
    // fecha_operacion = hoy + 5 dias => deadline es en 4 dias, aun no pasa
    $reserva = Reserva::factory()->create([
        'fecha_operacion' => Carbon::now('America/Bogota')->addDays(5),
    ]);

    expect($reserva->isLocked())->toBeFalse();
});
