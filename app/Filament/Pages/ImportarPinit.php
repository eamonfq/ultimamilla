<?php

namespace App\Filament\Pages;

use App\Enums\StatusImport;
use App\Jobs\ProcesarPinitImport;
use App\Models\PinitImport;
use App\Services\PinitParser;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

class ImportarPinit extends Page
{
    use WithFileUploads;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static string|\UnitEnum|null $navigationGroup = 'Pinit';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Importar archivo Pinit';

    protected static ?string $navigationLabel = 'Importar Pinit';

    protected string $view = 'filament.pages.importar-pinit';

    public $archivo;

    public ?int $importIdEnProceso = null;

    public function mount(): void
    {
        $procesando = PinitImport::query()
            ->whereIn('status', [StatusImport::Pending, StatusImport::Processing])
            ->latest()
            ->first();

        if ($procesando) {
            $this->importIdEnProceso = $procesando->id;
        }
    }

    public function importar(): void
    {
        $this->validate([
            'archivo' => ['required', 'file', 'max:25600'],
        ]);

        $nombreOriginal = $this->archivo->getClientOriginalName();
        $archivoPath = 'uploads/'.$nombreOriginal;

        // Guardar archivo en disco pinit_imports
        Storage::disk('pinit_imports')->put($archivoPath, file_get_contents($this->archivo->getRealPath()));

        $this->archivo = null;

        $this->procesarArchivo($archivoPath, $nombreOriginal);
    }

    public function procesarArchivo(string $archivoPath, string $nombreOriginal): void
    {
        $parser = app(PinitParser::class);

        // Extraer fecha del nombre
        $fechaArchivo = $parser->extraerFechaDelNombre($nombreOriginal);

        if (! $fechaArchivo) {
            Notification::make()
                ->danger()
                ->title('Nombre de archivo invalido')
                ->body('El archivo debe tener formato YYYY-MM-DD en el nombre, como "report-ds-...-2026-05-02-to-2026-05-02.xlsx".')
                ->send();
            Storage::disk('pinit_imports')->delete($archivoPath);

            return;
        }

        // Validar cabeceras antes de encolar
        $rutaCompleta = Storage::disk('pinit_imports')->path($archivoPath);
        try {
            $primerasFilas = Excel::toArray([], $rutaCompleta)[0] ?? [];
            if (empty($primerasFilas) || ! $parser->validarCabeceras($primerasFilas[0])) {
                throw new \RuntimeException('El archivo no parece ser un export valido de Pinit.');
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Archivo invalido')
                ->body($e->getMessage())
                ->send();
            Storage::disk('pinit_imports')->delete($archivoPath);

            return;
        }

        // Verificar si hay un import previo done para esa fecha → marcar superseded
        $previo = PinitImport::query()
            ->whereDate('fecha_archivo', $fechaArchivo)
            ->where('status', StatusImport::Done)
            ->latest()
            ->first();

        if ($previo) {
            $previo->update(['status' => StatusImport::Superseded]);
            activity()
                ->performedOn($previo)
                ->causedBy(auth()->user())
                ->withProperties(['fecha_archivo' => $fechaArchivo->toDateString()])
                ->log('pinit_import.replaced');
        }

        // Verificar que NO haya pending/processing previo para esa fecha
        $enCurso = PinitImport::query()
            ->whereDate('fecha_archivo', $fechaArchivo)
            ->whereIn('status', [StatusImport::Pending, StatusImport::Processing])
            ->exists();

        if ($enCurso) {
            Notification::make()
                ->warning()
                ->title('Import en curso')
                ->body('Ya hay un import procesandose para esta fecha. Espera a que termine.')
                ->send();
            Storage::disk('pinit_imports')->delete($archivoPath);

            return;
        }

        // Crear el import en pending
        $import = PinitImport::create([
            'archivo_path' => $archivoPath,
            'fecha_archivo' => $fechaArchivo,
            'total_filas' => 0,
            'status' => StatusImport::Pending,
            'importado_por' => auth()->id(),
        ]);

        // Disparar job
        ProcesarPinitImport::dispatch($import->id);

        $this->importIdEnProceso = $import->id;

        Notification::make()
            ->success()
            ->title('Archivo recibido')
            ->body("Procesando para fecha {$fechaArchivo->locale('es')->isoFormat('D MMM YYYY')}.")
            ->send();
    }

    public function getImportEnProcesoProperty(): ?PinitImport
    {
        if (! $this->importIdEnProceso) {
            return null;
        }

        return PinitImport::find($this->importIdEnProceso);
    }

    public function pollingTick(): void
    {
        $import = $this->importEnProceso;
        if (! $import) {
            return;
        }

        if (in_array($import->status, [StatusImport::Done, StatusImport::Failed], true)) {
            $this->importIdEnProceso = null;

            if ($import->status === StatusImport::Done) {
                Notification::make()
                    ->success()
                    ->title('Import completado')
                    ->body("{$import->total_filas} filas procesadas.")
                    ->send();
            } else {
                Notification::make()
                    ->danger()
                    ->title('Import fallo')
                    ->body('Revisa el detalle en el listado de imports.')
                    ->send();
            }
        }
    }
}
