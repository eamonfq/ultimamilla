<?php

use App\Enums\Ciudad;
use App\Filament\Resources\RepartidorResource\Pages\CreateRepartidor;
use App\Filament\Resources\RepartidorResource\Pages\EditRepartidor;
use App\Filament\Resources\RepartidorResource\Pages\ListRepartidores;
use App\Livewire\Repartidor\Login;
use App\Models\Repartidor;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->actingAs($this->admin);
});

it('admin can view repartidores list', function () {
    $repartidores = Repartidor::factory()->count(3)->create();

    Livewire::test(ListRepartidores::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords($repartidores);
});

it('admin can search repartidores by cedula', function () {
    $luis = Repartidor::factory()->create(['cedula' => '3951329', 'nombre' => 'Luis Prada']);
    $otro = Repartidor::factory()->create(['cedula' => '9999999', 'nombre' => 'Otro Repartidor']);

    Livewire::test(ListRepartidores::class)
        ->searchTable('3951329')
        ->assertCanSeeTableRecords([$luis])
        ->assertCanNotSeeTableRecords([$otro]);
});

it('admin can search repartidores by nombre', function () {
    $duvan = Repartidor::factory()->create(['nombre' => 'Duván Pantoja']);
    $otro = Repartidor::factory()->create(['nombre' => 'Maycol Henao']);

    Livewire::test(ListRepartidores::class)
        ->searchTable('Duván')
        ->assertCanSeeTableRecords([$duvan])
        ->assertCanNotSeeTableRecords([$otro]);
});

it('admin can filter by ciudad', function () {
    $med = Repartidor::factory()->create(['ciudad' => Ciudad::MED]);
    $itagui = Repartidor::factory()->create(['ciudad' => Ciudad::ITAGUI]);

    Livewire::test(ListRepartidores::class)
        ->filterTable('ciudad', Ciudad::ITAGUI->value)
        ->assertCanSeeTableRecords([$itagui])
        ->assertCanNotSeeTableRecords([$med]);
});

it('admin can filter by activo', function () {
    $activo = Repartidor::factory()->create(['activo' => true]);
    $inactivo = Repartidor::factory()->create(['activo' => false]);

    Livewire::test(ListRepartidores::class)
        ->filterTable('activo', false)
        ->assertCanSeeTableRecords([$inactivo])
        ->assertCanNotSeeTableRecords([$activo]);
});

it('admin can create a repartidor and pin is generated', function () {
    Livewire::test(CreateRepartidor::class)
        ->fillForm([
            'cedula' => 'ABC12345',
            'nombre' => 'Test Repartidor',
            'ciudad' => Ciudad::MED->value,
            'activo' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $repartidor = Repartidor::where('cedula', 'ABC12345')->first();

    expect($repartidor)->not->toBeNull()
        ->and($repartidor->nombre)->toBe('Test Repartidor')
        ->and($repartidor->pin)->toStartWith('$2y$')
        ->and($repartidor->activo)->toBeTrue();
});

it('pin is exactly 4 digits', function () {
    for ($i = 0; $i < 20; $i++) {
        $pin = (string) random_int(1000, 9999);
        expect($pin)->toMatch('/^\d{4}$/');
    }
});

it('admin cannot edit cedula of existing repartidor', function () {
    $repartidor = Repartidor::factory()->create(['cedula' => 'XYZ999']);

    Livewire::test(EditRepartidor::class, ['record' => $repartidor->getRouteKey()])
        ->assertFormFieldIsDisabled('cedula');
});

it('admin can regenerate pin', function () {
    $repartidor = Repartidor::factory()->create(['pin' => '5678']);
    $oldPin = $repartidor->fresh()->pin;

    Livewire::test(EditRepartidor::class, ['record' => $repartidor->getRouteKey()])
        ->callAction('regenerarPin');

    $repartidor->refresh();
    expect($repartidor->pin)->not->toBe($oldPin);

    $pinActivity = $repartidor->activities()
        ->where('description', 'pin_regenerado')
        ->first();
    expect($pinActivity)->not->toBeNull();
});

it('admin can deactivate repartidor and they cannot login anymore', function () {
    $repartidor = Repartidor::factory()->create([
        'cedula' => '11111111',
        'pin' => '9999',
        'activo' => true,
    ]);

    Livewire::test(ListRepartidores::class)
        ->callTableAction('toggleActivo', $repartidor);

    $repartidor->refresh();
    expect($repartidor->activo)->toBeFalse();

    Livewire::test(Login::class)
        ->set('cedula', '11111111')
        ->set('pin', '9999')
        ->call('submit')
        ->assertHasErrors('cedula');
});

it('cedula must be unique', function () {
    Repartidor::factory()->create(['cedula' => '3951329']);

    Livewire::test(CreateRepartidor::class)
        ->fillForm([
            'cedula' => '3951329',
            'nombre' => 'Otro Repartidor',
            'ciudad' => Ciudad::MED->value,
            'activo' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['cedula']);
});

it('cedula accepts alphanumeric', function () {
    Livewire::test(CreateRepartidor::class)
        ->fillForm([
            'cedula' => 'ABC123XYZ',
            'nombre' => 'Repartidor Alpha',
            'ciudad' => Ciudad::MED->value,
            'activo' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Repartidor::where('cedula', 'ABC123XYZ')->exists())->toBeTrue();
});

it('cedula rejects too short', function () {
    Livewire::test(CreateRepartidor::class)
        ->fillForm([
            'cedula' => 'ABC',
            'nombre' => 'Repartidor Corto',
            'ciudad' => Ciudad::MED->value,
            'activo' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['cedula']);
});

it('placa is uppercased on save', function () {
    Livewire::test(CreateRepartidor::class)
        ->fillForm([
            'cedula' => 'PLACA12345',
            'nombre' => 'Repartidor Placa',
            'ciudad' => Ciudad::MED->value,
            'placa' => 'abc123',
            'activo' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $repartidor = Repartidor::where('cedula', 'PLACA12345')->first();
    expect($repartidor->placa)->toBe('ABC123');
});
