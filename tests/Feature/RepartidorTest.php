<?php

use App\Models\Repartidor;
use App\Settings\UltimamillaSettings;
use Illuminate\Support\Facades\Hash;

it('hashes the PIN automatically when creating', function () {
    $repartidor = Repartidor::factory()->create(['pin' => '1234']);

    expect($repartidor->pin)->not->toBe('1234');
    expect(Hash::check('1234', $repartidor->pin))->toBeTrue();
});

it('returns cupo personalizado when set', function () {
    $repartidor = Repartidor::factory()->create(['cupo_personalizado' => 50]);
    $settings = app(UltimamillaSettings::class);

    expect($repartidor->cupoEfectivo($settings))->toBe(50);
});

it('falls back to global cupo when personalizado is null', function () {
    $repartidor = Repartidor::factory()->create(['cupo_personalizado' => null]);
    $settings = app(UltimamillaSettings::class);

    expect($repartidor->cupoEfectivo($settings))->toBe($settings->cupo_global_default);
});
