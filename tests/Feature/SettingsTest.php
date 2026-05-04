<?php

use App\Settings\UltimamillaSettings;

it('loads default settings from migration', function () {
    $settings = app(UltimamillaSettings::class);

    expect($settings->cupo_global_default)->toBe(30);
    expect($settings->dias_operativos)->toBe([1, 2, 3, 4, 5, 6]);
    expect($settings->hora_cierre_reservas)->toBe('12:00');
    expect($settings->umbral_minimo_diario)->toBe(0);
    expect($settings->devolucion_threshold)->toBe(3);
});
