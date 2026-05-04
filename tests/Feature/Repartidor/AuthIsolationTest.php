<?php

use App\Models\Repartidor;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

it('admin login does not authenticate as repartidor', function () {
    $admin = User::factory()->create();

    Auth::guard('web')->login($admin);

    expect(Auth::guard('web')->check())->toBeTrue();
    expect(Auth::guard('repartidor')->check())->toBeFalse();
});

it('repartidor login does not authenticate as admin', function () {
    $repartidor = Repartidor::factory()->create([
        'pin' => '1234',
        'activo' => true,
    ]);

    Auth::guard('repartidor')->login($repartidor);

    expect(Auth::guard('repartidor')->check())->toBeTrue();
    expect(Auth::guard('web')->check())->toBeFalse();
});

it('repartidor logout does not affect admin session', function () {
    $admin = User::factory()->create();
    $repartidor = Repartidor::factory()->create([
        'pin' => '1234',
        'activo' => true,
    ]);

    Auth::guard('web')->login($admin);
    Auth::guard('repartidor')->login($repartidor);

    expect(Auth::guard('web')->check())->toBeTrue();
    expect(Auth::guard('repartidor')->check())->toBeTrue();

    Auth::guard('repartidor')->logout();

    expect(Auth::guard('repartidor')->check())->toBeFalse();
    expect(Auth::guard('web')->check())->toBeTrue();
});
