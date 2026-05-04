<?php

use App\Enums\PatronCruce;
use App\Enums\StatusImport;
use App\Mail\ResumenDiarioMail;
use App\Models\Cruce;
use App\Models\PinitImport;
use App\Models\Repartidor;
use App\Models\User;
use App\Services\ResumenDiario;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 5, 15, 10, 0, 0, 'America/Bogota'));
    $this->admin = User::factory()->create();
    $this->servicio = app(ResumenDiario::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

function crearImportResumen(): PinitImport
{
    return PinitImport::create([
        'archivo_path' => 'test.xlsx',
        'fecha_archivo' => '2026-05-14',
        'total_filas' => 5,
        'status' => StatusImport::Done,
        'importado_por' => User::factory()->create()->id,
    ]);
}

it('returns null when no cruces for date', function () {
    $result = $this->servicio->calcular(Carbon::parse('2026-05-14'));
    expect($result)->toBeNull();
});

it('calculates totals correctly', function () {
    $import = crearImportResumen();
    $rep1 = Repartidor::factory()->create();
    $rep2 = Repartidor::factory()->create();
    $rep3 = Repartidor::factory()->create();

    Cruce::create([
        'fecha_operacion' => '2026-05-14',
        'repartidor_id' => $rep1->id,
        'reservado' => 30, 'asignado' => 28, 'entregado' => 25,
        'cumplimiento_pct' => 89.29, 'patron' => PatronCruce::SobreReserva,
        'pinit_import_id' => $import->id,
    ]);
    Cruce::create([
        'fecha_operacion' => '2026-05-14',
        'repartidor_id' => $rep2->id,
        'reservado' => 50, 'asignado' => 50, 'entregado' => 48,
        'cumplimiento_pct' => 96.0, 'patron' => PatronCruce::Consistente,
        'pinit_import_id' => $import->id,
    ]);
    Cruce::create([
        'fecha_operacion' => '2026-05-14',
        'repartidor_id' => $rep3->id,
        'reservado' => 20, 'asignado' => 20, 'entregado' => 15,
        'cumplimiento_pct' => 75.0, 'patron' => PatronCruce::SobreReserva,
        'pinit_import_id' => $import->id,
    ]);

    $result = $this->servicio->calcular(Carbon::parse('2026-05-14'));

    expect($result['total_repartidores'])->toBe(3);
    expect($result['total_asignado'])->toBe(98);
    expect($result['total_entregado'])->toBe(88);
    // cumplimiento_global = 88/98 * 100 = 89.8
    expect($result['cumplimiento_global'])->toBeGreaterThan(89.0)->toBeLessThan(90.0);
});

it('picks top 3 by cumplimiento_pct desc', function () {
    $import = crearImportResumen();
    $cumplimientos = [95.0, 90.0, 85.0, 80.0, 75.0];

    foreach ($cumplimientos as $pct) {
        $rep = Repartidor::factory()->create();
        Cruce::create([
            'fecha_operacion' => '2026-05-14',
            'repartidor_id' => $rep->id,
            'reservado' => 30, 'asignado' => 30, 'entregado' => (int) ($pct * 0.3),
            'cumplimiento_pct' => $pct, 'patron' => PatronCruce::Consistente,
            'pinit_import_id' => $import->id,
        ]);
    }

    $result = $this->servicio->calcular(Carbon::parse('2026-05-14'));

    expect($result['top3'])->toHaveCount(3);
    expect($result['top3'][0]['cumplimiento'])->toBe(95.0);
    expect($result['top3'][1]['cumplimiento'])->toBe(90.0);
    expect($result['top3'][2]['cumplimiento'])->toBe(85.0);
});

it('picks bottom 3 by cumplimiento_pct asc', function () {
    $import = crearImportResumen();
    $cumplimientos = [95.0, 90.0, 85.0, 80.0, 75.0];

    foreach ($cumplimientos as $pct) {
        $rep = Repartidor::factory()->create();
        Cruce::create([
            'fecha_operacion' => '2026-05-14',
            'repartidor_id' => $rep->id,
            'reservado' => 30, 'asignado' => 30, 'entregado' => (int) ($pct * 0.3),
            'cumplimiento_pct' => $pct, 'patron' => PatronCruce::Consistente,
            'pinit_import_id' => $import->id,
        ]);
    }

    $result = $this->servicio->calcular(Carbon::parse('2026-05-14'));

    expect($result['bottom3'])->toHaveCount(3);
    expect($result['bottom3'][0]['cumplimiento'])->toBe(75.0);
    expect($result['bottom3'][1]['cumplimiento'])->toBe(80.0);
    expect($result['bottom3'][2]['cumplimiento'])->toBe(85.0);
});

it('includes alertas count', function () {
    $import = crearImportResumen();
    $rep = Repartidor::factory()->create();

    // 5 dias consecutivos de sobre_reserva para disparar alerta
    for ($i = 0; $i < 5; $i++) {
        Cruce::create([
            'fecha_operacion' => Carbon::parse('2026-05-10')->addDays($i),
            'repartidor_id' => $rep->id,
            'reservado' => 30, 'asignado' => 30, 'entregado' => 18,
            'cumplimiento_pct' => 60.0, 'patron' => PatronCruce::SobreReserva,
            'pinit_import_id' => $import->id,
        ]);
    }

    $result = $this->servicio->calcular(Carbon::parse('2026-05-14'));

    expect($result['alertas_count'])->toBeGreaterThanOrEqual(1);
});

it('command sends email to admin', function () {
    Mail::fake();

    $import = crearImportResumen();
    $rep = Repartidor::factory()->create();
    Cruce::create([
        'fecha_operacion' => '2026-05-14',
        'repartidor_id' => $rep->id,
        'reservado' => 30, 'asignado' => 30, 'entregado' => 28,
        'cumplimiento_pct' => 93.33, 'patron' => PatronCruce::Consistente,
        'pinit_import_id' => $import->id,
    ]);

    $this->artisan('ultimamilla:resumen-diario', ['--fecha' => '2026-05-14'])
        ->assertSuccessful();

    Mail::assertSent(ResumenDiarioMail::class);
});

it('command skips when no data', function () {
    Mail::fake();

    $this->artisan('ultimamilla:resumen-diario', ['--fecha' => '2026-01-01'])
        ->assertSuccessful()
        ->expectsOutput('No hay cruces para esa fecha. Saltando envio.');

    Mail::assertNotSent(ResumenDiarioMail::class);
});

it('command accepts custom fecha option', function () {
    Mail::fake();

    $import = PinitImport::create([
        'archivo_path' => 'test.xlsx',
        'fecha_archivo' => '2026-01-15',
        'total_filas' => 1,
        'status' => StatusImport::Done,
        'importado_por' => $this->admin->id,
    ]);

    $rep = Repartidor::factory()->create();
    Cruce::create([
        'fecha_operacion' => '2026-01-15',
        'repartidor_id' => $rep->id,
        'reservado' => 30, 'asignado' => 30, 'entregado' => 25,
        'cumplimiento_pct' => 83.33, 'patron' => PatronCruce::SobreReserva,
        'pinit_import_id' => $import->id,
    ]);

    $this->artisan('ultimamilla:resumen-diario', ['--fecha' => '2026-01-15'])
        ->assertSuccessful();

    Mail::assertSent(ResumenDiarioMail::class, function ($mail) {
        return str_contains($mail->envelope()->subject, '15');
    });
});
