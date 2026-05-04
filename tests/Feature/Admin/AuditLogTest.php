<?php

use App\Filament\Resources\AuditoriaResource;
use App\Filament\Resources\AuditoriaResource\Pages\ListAuditoria;
use App\Models\Repartidor;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 5, 15, 10, 0, 0, 'America/Bogota'));
    $this->admin = User::factory()->create();
    $this->actingAs($this->admin);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('auditoria page lists existing activities', function () {
    $rep = Repartidor::factory()->create();
    activity()->performedOn($rep)->causedBy($this->admin)->log('created');

    Livewire::test(ListAuditoria::class)
        ->assertOk()
        ->assertCanSeeTableRecords(Activity::all());
});

it('filters by description', function () {
    $rep = Repartidor::factory()->create();
    activity()->performedOn($rep)->causedBy($this->admin)->log('pin_regenerado');
    activity()->performedOn($rep)->causedBy($this->admin)->log('activado');

    Livewire::test(ListAuditoria::class)
        ->filterTable('description', 'pin_regenerado')
        ->assertCanSeeTableRecords(Activity::where('description', 'pin_regenerado')->get())
        ->assertCanNotSeeTableRecords(Activity::where('description', 'activado')->get());
});

it('filters by causer', function () {
    $otroAdmin = User::factory()->create();
    $rep = Repartidor::factory()->create();

    activity()->performedOn($rep)->causedBy($this->admin)->log('accion_admin1');
    activity()->performedOn($rep)->causedBy($otroAdmin)->log('accion_admin2');

    Livewire::test(ListAuditoria::class)
        ->filterTable('causer_id', $this->admin->id)
        ->assertCanSeeTableRecords(Activity::where('causer_id', $this->admin->id)->get())
        ->assertCanNotSeeTableRecords(Activity::where('causer_id', $otroAdmin->id)->get());
});

it('filters by date range', function () {
    $rep = Repartidor::factory()->create();
    activity()->performedOn($rep)->causedBy($this->admin)->log('hoy');

    // Create an old activity manually
    $oldActivity = Activity::create([
        'log_name' => 'default',
        'description' => 'antigua',
        'subject_type' => Repartidor::class,
        'subject_id' => $rep->id,
        'causer_type' => User::class,
        'causer_id' => $this->admin->id,
        'properties' => [],
        'created_at' => '2026-05-01 10:00:00',
        'updated_at' => '2026-05-01 10:00:00',
    ]);

    Livewire::test(ListAuditoria::class)
        ->filterTable('fecha', [
            'desde' => '2026-05-14',
            'hasta' => '2026-05-16',
        ])
        ->assertCanSeeTableRecords(Activity::where('description', 'hoy')->get())
        ->assertCanNotSeeTableRecords(Activity::where('description', 'antigua')->get());
});

it('view page shows activity detail', function () {
    $rep = Repartidor::factory()->create();
    activity()
        ->performedOn($rep)
        ->causedBy($this->admin)
        ->withProperties(['attributes' => ['nombre' => 'Nuevo'], 'old' => ['nombre' => 'Viejo']])
        ->log('updated');

    $activity = Activity::latest('id')->first();

    Livewire::test(AuditoriaResource\Pages\ViewAuditoria::class, ['record' => $activity->id])
        ->assertOk();
});
