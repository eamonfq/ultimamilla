<?php

use App\Enums\StatusImport;
use App\Jobs\ProcesarPinitImport;
use App\Models\Cruce;
use App\Models\PinitImport;
use App\Models\PinitRuta;
use App\Models\Repartidor;
use App\Models\Reserva;
use App\Models\User;
use App\Services\CruceCalculator;
use App\Services\PinitParser;
use App\Settings\UltimamillaSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 5, 15, 9, 0, 0, 'America/Bogota'));
    $this->admin = User::factory()->create();
    Storage::fake('pinit_imports');
});

afterEach(function () {
    Carbon::setTestNow();
});

function createTestExcelFile(array $rows, string $filename = 'report-2026-05-02-to-2026-05-02.xlsx'): string
{
    $path = 'uploads/'.$filename;

    // Create a real xlsx using Maatwebsite export
    $collection = collect($rows);

    $tempPath = Storage::disk('pinit_imports')->path($path);
    $dir = dirname($tempPath);
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // Use Maatwebsite to write
    (new class($collection) implements FromCollection
    {
        public function __construct(private $data) {}

        public function collection()
        {
            return $this->data;
        }
    });

    Excel::store(
        new class($collection) implements FromCollection
        {
            public function __construct(private $data) {}

            public function collection()
            {
                return $this->data;
            }
        },
        $path,
        'pinit_imports'
    );

    return $path;
}

function makeHeaderRow(): array
{
    $row = array_fill(0, 38, '');
    $row[0] = 'ID';
    $row[1] = 'Carrier';
    $row[2] = 'ID Operador';
    $row[3] = 'Operador';
    $row[8] = 'Status';
    $row[9] = 'Total';
    $row[10] = 'Entregados';

    return $row;
}

function makeDataRow(string $cedula, string $nombre, int $total, int $entregados, string $status = 'Completada'): array
{
    $row = array_fill(0, 38, '');
    $row[0] = 'RT-'.random_int(1000, 9999);
    $row[1] = 'TestCarrier';
    $row[2] = $cedula;
    $row[3] = $nombre;
    $row[8] = $status;
    $row[9] = $total;
    $row[10] = $entregados;

    return $row;
}

it('processes a valid file end-to-end', function () {
    $rep = Repartidor::factory()->create(['cedula' => '3951329', 'nombre' => 'Luis Prada']);
    Reserva::factory()->create([
        'repartidor_id' => $rep->id,
        'fecha_operacion' => '2026-05-02',
        'paquetes' => 50,
    ]);

    $rows = [
        makeHeaderRow(),
        makeDataRow('3951329', '3951329 MED Luis Prada WEGROUP', 50, 45),
    ];

    $path = createTestExcelFile($rows);

    $import = PinitImport::create([
        'archivo_path' => $path,
        'fecha_archivo' => '2026-05-02',
        'total_filas' => 0,
        'status' => StatusImport::Pending,
        'importado_por' => $this->admin->id,
    ]);

    (new ProcesarPinitImport($import->id))->handle(
        app(PinitParser::class),
        app(CruceCalculator::class),
        app(UltimamillaSettings::class)
    );

    $import->refresh();
    expect($import->status)->toBe(StatusImport::Done);
    expect($import->total_filas)->toBe(1);
    expect(PinitRuta::where('pinit_import_id', $import->id)->count())->toBe(1);
    expect(Cruce::count())->toBe(1);

    $cruce = Cruce::first();
    expect($cruce->repartidor_id)->toBe($rep->id);
    expect($cruce->entregado)->toBe(45);
});

it('adds cedulas no encontradas to warnings', function () {
    $rows = [
        makeHeaderRow(),
        makeDataRow('99999999', '99999999 MED Desconocido WEGROUP', 30, 25),
    ];

    $path = createTestExcelFile($rows);

    $import = PinitImport::create([
        'archivo_path' => $path,
        'fecha_archivo' => '2026-05-02',
        'total_filas' => 0,
        'status' => StatusImport::Pending,
        'importado_por' => $this->admin->id,
    ]);

    (new ProcesarPinitImport($import->id))->handle(
        app(PinitParser::class),
        app(CruceCalculator::class),
        app(UltimamillaSettings::class)
    );

    $import->refresh();
    expect($import->status)->toBe(StatusImport::Done);
    expect($import->warnings['cedulas_no_encontradas'])->toContain('99999999');

    // Ruta persiste con repartidor_id = null
    $ruta = PinitRuta::first();
    expect($ruta->repartidor_id)->toBeNull();
});

it('marks failed when file is corrupt', function () {
    // File with bad headers
    $rows = [
        ['Columna1', 'Columna2', 'Columna3'],
        ['data1', 'data2', 'data3'],
    ];

    $path = createTestExcelFile($rows, 'report-2026-05-02-to-2026-05-02.xlsx');

    $import = PinitImport::create([
        'archivo_path' => $path,
        'fecha_archivo' => '2026-05-02',
        'total_filas' => 0,
        'status' => StatusImport::Pending,
        'importado_por' => $this->admin->id,
    ]);

    try {
        (new ProcesarPinitImport($import->id))->handle(
            app(PinitParser::class),
            app(CruceCalculator::class),
            app(UltimamillaSettings::class)
        );
    } catch (Throwable $e) {
        // Expected
    }

    $import->refresh();
    expect($import->status)->toBe(StatusImport::Failed);
    expect(PinitRuta::where('pinit_import_id', $import->id)->count())->toBe(0);
});

it('skips empty rows silently', function () {
    $rep = Repartidor::factory()->create(['cedula' => '3951329']);

    $emptyRow = array_fill(0, 38, '');
    $rows = [
        makeHeaderRow(),
        makeDataRow('3951329', '3951329 MED Luis Prada WEGROUP', 50, 45),
        $emptyRow,
        makeDataRow('3951329', '3951329 MED Luis Prada WEGROUP', 30, 28),
    ];

    $path = createTestExcelFile($rows);

    $import = PinitImport::create([
        'archivo_path' => $path,
        'fecha_archivo' => '2026-05-02',
        'total_filas' => 0,
        'status' => StatusImport::Pending,
        'importado_por' => $this->admin->id,
    ]);

    (new ProcesarPinitImport($import->id))->handle(
        app(PinitParser::class),
        app(CruceCalculator::class),
        app(UltimamillaSettings::class)
    );

    $import->refresh();
    expect($import->status)->toBe(StatusImport::Done);
    // 2 data rows parsed (empty skipped)
    expect(PinitRuta::where('pinit_import_id', $import->id)->count())->toBe(2);
});
