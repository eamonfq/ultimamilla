<?php

namespace App\Filament\Resources\PinitImportResource\Pages;

use App\Filament\Resources\PinitImportResource;
use App\Filament\Resources\RepartidorResource;
use App\Models\Cruce;
use Filament\Resources\Pages\ViewRecord;

class ViewPinitImport extends ViewRecord
{
    protected static string $resource = PinitImportResource::class;

    protected string $view = 'filament.resources.pinit-import-resource.pages.view-pinit-import';

    public function getCedulasNoEncontradasProperty(): array
    {
        return $this->record->warnings['cedulas_no_encontradas'] ?? [];
    }

    public function getErroresProperty(): array
    {
        $errores = $this->record->errores;
        if (is_array($errores) && isset($errores['mensaje'])) {
            return [$errores['mensaje']];
        }

        return is_array($errores) ? $errores : [];
    }

    public function getCrucesCountProperty(): int
    {
        return Cruce::where('pinit_import_id', $this->record->id)->count();
    }

    public function getCrearRepartidorUrl(string $cedula): string
    {
        return RepartidorResource::getUrl('create').'?cedula='.$cedula;
    }
}
