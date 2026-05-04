<?php

use App\Enums\StatusImport;
use App\Models\PinitImport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 5, 15, 10, 0, 0, 'America/Bogota'));
    Storage::fake('pinit_imports');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('deletes physical files older than 90 days', function () {
    $admin = User::factory()->create();

    Storage::disk('pinit_imports')->put('uploads/old-file.xlsx', 'content');

    $import = PinitImport::create([
        'archivo_path' => 'uploads/old-file.xlsx',
        'fecha_archivo' => '2026-02-01',
        'total_filas' => 10,
        'status' => StatusImport::Done,
        'importado_por' => $admin->id,
    ]);

    // Force old created_at via query to bypass model timestamps
    DB::table('pinit_imports')
        ->where('id', $import->id)
        ->update(['created_at' => '2026-02-01 10:00:00']);

    $this->artisan('ultimamilla:limpiar-imports', ['--dias' => '90'])
        ->assertSuccessful();

    Storage::disk('pinit_imports')->assertMissing('uploads/old-file.xlsx');

    $import->refresh();
    expect($import->archivo_path)->toBe('');
    // DB record persists
    expect(PinitImport::find($import->id))->not->toBeNull();
});

it('does NOT delete recent files', function () {
    $admin = User::factory()->create();

    Storage::disk('pinit_imports')->put('uploads/recent-file.xlsx', 'content');

    $import = PinitImport::create([
        'archivo_path' => 'uploads/recent-file.xlsx',
        'fecha_archivo' => '2026-05-10',
        'total_filas' => 5,
        'status' => StatusImport::Done,
        'importado_por' => $admin->id,
    ]);

    $this->artisan('ultimamilla:limpiar-imports', ['--dias' => '90'])
        ->assertSuccessful();

    Storage::disk('pinit_imports')->assertExists('uploads/recent-file.xlsx');

    $import->refresh();
    expect($import->archivo_path)->toBe('uploads/recent-file.xlsx');
});
