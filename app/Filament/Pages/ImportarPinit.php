<?php

namespace App\Filament\Pages;

use App\Enums\StatusImport;
use App\Models\PinitImport;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ImportarPinit extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static string|\UnitEnum|null $navigationGroup = 'Pinit';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Importar archivo Pinit';

    protected static ?string $navigationLabel = 'Importar Pinit';

    protected string $view = 'filament.pages.importar-pinit';

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
