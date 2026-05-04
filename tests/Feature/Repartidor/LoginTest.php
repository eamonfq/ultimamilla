<?php

use App\Livewire\Repartidor\Login;
use App\Models\Repartidor;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

it('can render the login page', function () {
    $this->get('/repartidor/login')
        ->assertStatus(200)
        ->assertSee('Ingresar');
});

it('logs in with valid credentials', function () {
    Repartidor::factory()->create([
        'cedula' => '12345678',
        'pin' => '1234',
        'activo' => true,
    ]);

    Livewire::test(Login::class)
        ->set('cedula', '12345678')
        ->set('pin', '1234')
        ->call('submit')
        ->assertRedirect(route('repartidor.home'));

    expect(Auth::guard('repartidor')->check())->toBeTrue();
});

it('rejects invalid PIN', function () {
    Repartidor::factory()->create([
        'cedula' => '12345678',
        'pin' => '1234',
        'activo' => true,
    ]);

    Livewire::test(Login::class)
        ->set('cedula', '12345678')
        ->set('pin', '9999')
        ->call('submit')
        ->assertHasErrors(['cedula']);

    expect(Auth::guard('repartidor')->check())->toBeFalse();
});

it('rejects inactive repartidor', function () {
    Repartidor::factory()->create([
        'cedula' => '12345678',
        'pin' => '1234',
        'activo' => false,
    ]);

    Livewire::test(Login::class)
        ->set('cedula', '12345678')
        ->set('pin', '1234')
        ->call('submit')
        ->assertHasErrors(['cedula']);

    expect(Auth::guard('repartidor')->check())->toBeFalse();
});

it('rate limits after 5 failed attempts', function () {
    Repartidor::factory()->create([
        'cedula' => '12345678',
        'pin' => '1234',
        'activo' => true,
    ]);

    // Acumular 5 intentos fallidos
    for ($i = 0; $i < 5; $i++) {
        $this->post('/repartidor/login', [
            'cedula' => '12345678',
            'pin' => '0000',
        ]);
    }

    // El 6to intento debe ser bloqueado por rate limiter
    Livewire::test(Login::class)
        ->set('cedula', '12345678')
        ->set('pin', '0000')
        ->call('submit')
        ->assertHasErrors('cedula');
});

it('updates last_login_at on success', function () {
    $repartidor = Repartidor::factory()->create([
        'cedula' => '12345678',
        'pin' => '1234',
        'activo' => true,
    ]);

    expect($repartidor->last_login_at)->toBeNull();

    Livewire::test(Login::class)
        ->set('cedula', '12345678')
        ->set('pin', '1234')
        ->call('submit');

    $repartidor->refresh();
    expect($repartidor->last_login_at)->not->toBeNull();
});
