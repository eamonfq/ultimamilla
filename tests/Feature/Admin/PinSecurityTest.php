<?php

use App\Filament\Resources\RepartidorResource\Pages\EditRepartidor;
use App\Models\Repartidor;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->actingAs($this->admin);
});

it('pin is never stored in plain text', function () {
    $repartidor = Repartidor::factory()->create(['pin' => '4567']);

    $fromDb = Repartidor::find($repartidor->id);
    $rawPin = $fromDb->getRawOriginal('pin');

    expect($rawPin)->not->toBe('4567')
        ->and($rawPin)->toStartWith('$2y$');
});

it('pin is excluded from activity log', function () {
    Activity::query()->delete();

    Repartidor::factory()->create([
        'cedula' => 'ACTLOG123',
        'nombre' => 'Test Activity',
        'pin' => '1234',
    ]);

    $activity = Activity::latest()->first();

    expect($activity)->not->toBeNull();

    $properties = $activity->properties->toArray();
    $allValues = json_encode($properties);

    expect($allValues)->not->toContain('"pin"');
});

it('pin is excluded from model toArray', function () {
    $repartidor = Repartidor::factory()->create(['pin' => '7890']);

    $array = $repartidor->toArray();

    expect($array)->not->toHaveKey('pin');
});

it('whatsapp message format is correct', function () {
    $repartidor = Repartidor::factory()->create([
        'cedula' => '3951329',
        'nombre' => 'Luis Prada',
    ]);

    $page = Livewire::test(EditRepartidor::class, [
        'record' => $repartidor->getRouteKey(),
    ]);

    $component = $page->instance();
    $message = $component->armarMensajeWhatsApp('Luis Prada', '3951329', '5555');

    expect($message)
        ->toContain('Hola Luis')
        ->toContain('3951329')
        ->toContain('5555')
        ->toContain('/repartidor/login');
});
