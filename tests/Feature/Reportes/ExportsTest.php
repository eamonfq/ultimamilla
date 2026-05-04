<?php

use App\Enums\PatronCruce;
use App\Enums\StatusImport;
use App\Exports\CrucesExport;
use App\Exports\RepartidoresExport;
use App\Exports\ReporteMensualExport;
use App\Exports\ReporteMensualRepartidorSheet;
use App\Exports\ReservasExport;
use App\Models\Cruce;
use App\Models\PinitImport;
use App\Models\Repartidor;
use App\Models\Reserva;
use App\Models\User;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 5, 15, 10, 0, 0, 'America/Bogota'));
    $this->admin = User::factory()->create();
});

afterEach(function () {
    Carbon::setTestNow();
});

it('exports repartidores to xlsx with correct headings', function () {
    Repartidor::factory()->count(3)->create();

    $export = new RepartidoresExport(soloActivos: true);
    $data = Excel::raw($export, Maatwebsite\Excel\Excel::XLSX);

    expect($data)->not->toBeEmpty();
    expect($export->headings())->toHaveCount(10);
    expect($export->headings()[0])->toBe('Cedula');
    expect($export->headings()[9])->toBe('Creado');
});

it('filters reservas export by date range', function () {
    $rep = Repartidor::factory()->create();
    Reserva::factory()->create([
        'repartidor_id' => $rep->id,
        'fecha_operacion' => '2026-05-10',
    ]);
    Reserva::factory()->create([
        'repartidor_id' => $rep->id,
        'fecha_operacion' => '2026-05-20',
    ]);

    $export = new ReservasExport(
        desde: Carbon::parse('2026-05-08'),
        hasta: Carbon::parse('2026-05-12'),
    );

    $rows = $export->query()->get();
    expect($rows)->toHaveCount(1);
    expect($rows->first()->fecha_operacion->format('Y-m-d'))->toBe('2026-05-10');
});

it('exports cruces with correct format', function () {
    $rep = Repartidor::factory()->create();
    $import = PinitImport::create([
        'archivo_path' => 'test.xlsx',
        'fecha_archivo' => '2026-05-10',
        'total_filas' => 1,
        'status' => StatusImport::Done,
        'importado_por' => $this->admin->id,
    ]);

    Cruce::create([
        'fecha_operacion' => '2026-05-10',
        'repartidor_id' => $rep->id,
        'reservado' => 30,
        'asignado' => 30,
        'entregado' => 28,
        'cumplimiento_pct' => 93.33,
        'patron' => PatronCruce::Consistente,
        'pinit_import_id' => $import->id,
    ]);

    $export = new CrucesExport;
    $headings = $export->headings();
    expect($headings)->toHaveCount(8);
    expect($headings[7])->toBe('Patron');

    $rows = $export->query()->get();
    expect($rows)->toHaveCount(1);

    $mapped = $export->map($rows->first());
    expect($mapped[7])->toBe('Consistente');
});

it('generates monthly report with summary sheet plus one sheet per repartidor', function () {
    $import = PinitImport::create([
        'archivo_path' => 'test.xlsx',
        'fecha_archivo' => '2026-05-10',
        'total_filas' => 3,
        'status' => StatusImport::Done,
        'importado_por' => $this->admin->id,
    ]);

    $reps = Repartidor::factory()->count(3)->create();
    foreach ($reps as $rep) {
        Cruce::create([
            'fecha_operacion' => '2026-05-10',
            'repartidor_id' => $rep->id,
            'reservado' => 30,
            'asignado' => 30,
            'entregado' => 25,
            'cumplimiento_pct' => 83.33,
            'patron' => PatronCruce::SobreReserva,
            'pinit_import_id' => $import->id,
        ]);
    }

    $export = new ReporteMensualExport(Carbon::create(2026, 5, 1));
    $sheets = $export->sheets();

    // 1 resumen + 3 repartidores = 4 hojas
    expect($sheets)->toHaveCount(4);
});

it('truncates sheet name to 31 chars and removes invalid chars', function () {
    $import = PinitImport::create([
        'archivo_path' => 'test.xlsx',
        'fecha_archivo' => '2026-05-10',
        'total_filas' => 1,
        'status' => StatusImport::Done,
        'importado_por' => $this->admin->id,
    ]);

    $rep = Repartidor::factory()->create([
        'nombre' => 'Juan Carlos Hernandez/Martinez [Especial?]',
    ]);

    Cruce::create([
        'fecha_operacion' => '2026-05-10',
        'repartidor_id' => $rep->id,
        'reservado' => 30,
        'asignado' => 30,
        'entregado' => 25,
        'cumplimiento_pct' => 83.33,
        'patron' => PatronCruce::SobreReserva,
        'pinit_import_id' => $import->id,
    ]);

    $sheet = new ReporteMensualRepartidorSheet(
        $rep,
        Carbon::create(2026, 5, 1),
        Carbon::create(2026, 5, 31),
    );

    $title = $sheet->title();
    expect(mb_strlen($title))->toBeLessThanOrEqual(31);
    expect($title)->not->toContain('/');
    expect($title)->not->toContain('?');
    expect($title)->not->toContain('[');
    expect($title)->not->toContain(']');
});
