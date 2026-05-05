<?php

use App\Enums\StatusImport;
use App\Jobs\ProcesarPinitImport;
use App\Models\PinitImport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 5, 15, 9, 0, 0, 'America/Bogota'));
    $this->admin = User::factory()->create();
    $this->actingAs($this->admin);
    Storage::fake('pinit_imports');
    Queue::fake();
});

afterEach(function () {
    Carbon::setTestNow();
});

function makeExcelFile(string $filename, ?array $rows = null): UploadedFile
{
    if ($rows === null) {
        $header = array_fill(0, 38, '');
        $header[0] = 'ID';
        $header[1] = 'Carrier';
        $header[2] = 'ID Operador';
        $header[3] = 'Operador';
        $header[9] = 'Total';

        $data = array_fill(0, 38, '');
        $data[0] = 'RT-001';
        $data[2] = '3951329';
        $data[3] = '3951329 MED Luis Prada WEGROUP';
        $data[8] = 'Completada';
        $data[9] = 50;
        $data[10] = 45;

        $rows = [$header, $data];
    }

    $collection = collect($rows);

    // Write Excel to a temp file
    $tmpPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.$filename;
    Excel::store(
        new class($collection) implements FromCollection
        {
            public function __construct(private $data) {}

            public function collection()
            {
                return $this->data;
            }
        },
        $filename,
        'pinit_imports'
    );

    $content = Storage::disk('pinit_imports')->get($filename);
    Storage::disk('pinit_imports')->delete($filename);
    file_put_contents($tmpPath, $content);

    return new UploadedFile(
        $tmpPath,
        $filename,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );
}

it('admin can upload valid file and import is queued', function () {
    $file = makeExcelFile('report-2026-05-02-to-2026-05-02.xlsx');

    $this->post(route('pinit-import.store'), ['archivo' => $file])
        ->assertRedirect()
        ->assertSessionHas('success');

    $import = PinitImport::first();
    expect($import)->not->toBeNull();
    expect($import->status)->toBe(StatusImport::Pending);
    expect($import->fecha_archivo->format('Y-m-d'))->toBe('2026-05-02');

    Queue::assertPushed(ProcesarPinitImport::class, fn ($job) => $job->importId === $import->id);
});

it('rejects file without YYYY-MM-DD in name', function () {
    $file = makeExcelFile('random-file.xlsx');

    $this->post(route('pinit-import.store'), ['archivo' => $file])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(PinitImport::count())->toBe(0);
});

it('rejects file with bad cabeceras', function () {
    $file = makeExcelFile('report-2026-05-02-to-2026-05-02.xlsx', [
        ['Foo', 'Bar', 'Baz'],
        ['data1', 'data2', 'data3'],
    ]);

    $this->post(route('pinit-import.store'), ['archivo' => $file])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(PinitImport::count())->toBe(0);
});

it('previous done import becomes superseded on reupload for same date', function () {
    $previo = PinitImport::create([
        'archivo_path' => 'old.xlsx',
        'fecha_archivo' => '2026-05-02',
        'total_filas' => 9,
        'status' => StatusImport::Done,
        'importado_por' => $this->admin->id,
    ]);

    $file = makeExcelFile('report-2026-05-02-to-2026-05-02.xlsx');

    $this->post(route('pinit-import.store'), ['archivo' => $file])
        ->assertRedirect()
        ->assertSessionHas('success');

    $previo->refresh();
    expect($previo->status)->toBe(StatusImport::Superseded);

    $nuevo = PinitImport::where('id', '!=', $previo->id)->first();
    expect($nuevo->status)->toBe(StatusImport::Pending);

    $log = Activity::where('description', 'pinit_import.replaced')->latest('id')->first();
    expect($log)->not->toBeNull();
});

it('cannot upload while another import is processing for same date', function () {
    PinitImport::create([
        'archivo_path' => 'processing.xlsx',
        'fecha_archivo' => '2026-05-02',
        'total_filas' => 0,
        'status' => StatusImport::Pending,
        'importado_por' => $this->admin->id,
    ]);

    $file = makeExcelFile('report-2026-05-02-to-2026-05-02.xlsx');

    $this->post(route('pinit-import.store'), ['archivo' => $file])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(PinitImport::count())->toBe(1);
});
