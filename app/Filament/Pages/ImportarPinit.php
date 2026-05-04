<?php

namespace App\Filament\Pages;

use App\Enums\StatusImport;
use App\Jobs\ProcesarPinitImport;
use App\Models\PinitImport;
use App\Services\PinitParser;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Maatwebsite\Excel\Facades\Excel;

class ImportarPinit extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static string|\UnitEnum|null $navigationGroup = 'Pinit';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Importar archivo Pinit';

    protected static ?string $navigationLabel = 'Importar Pinit';

    protected string $view = 'filament.pages.importar-pinit';

    public ?array $data = [];

    public ?int $importIdEnProceso = null;

    public function mount(): void
    {
        $this->form->fill();

        $procesando = PinitImport::query()
            ->whereIn('status', [StatusImport::Pending, StatusImport::Processing])
            ->latest()
            ->first();

        if ($procesando) {
            $this->importIdEnProceso = $procesando->id;
        }
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                FileUpload::make('archivo')
                    ->label('Archivo Pinit (.xlsx)')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                    ])
                    ->maxSize(20480)
                    ->required()
                    ->storeFiles(false)
                    ->helperText('Arrastra el archivo o haz clic para seleccionarlo. Acepta .xlsx exportado de Pinit.'),
            ])
            ->statePath('data');
    }

    public function importar(): void
    {
        $archivoRaw = data_get($this->data, 'archivo');
        $tmpFile = is_array($archivoRaw) ? reset($archivoRaw) : $archivoRaw;

        if (empty($tmpFile)) {
            return;
        }

        // Resolver archivo: puede ser TemporaryUploadedFile (upload real) o string (tests)
        if ($tmpFile instanceof TemporaryUploadedFile) {
            $nombreOriginal = $tmpFile->getClientOriginalName();
            $archivoPath = 'uploads/'.now()->format('Ymd_His').'_'.$nombreOriginal;
            Storage::disk('pinit_imports')->put($archivoPath, $tmpFile->get());
            $tmpFile->delete();
        } else {
            // String path — ya esta en el disk pinit_imports (tests)
            $archivoPath = $tmpFile;
            $nombreOriginal = basename($archivoPath);
        }

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
        $this->form->fill();

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
