<?php

namespace App\Filament\Resources\ReservaResource\Pages;

use App\Enums\EstadoReserva;
use App\Filament\Resources\ReservaResource;
use App\Models\Reserva;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditReserva extends EditRecord
{
    protected static string $resource = ReservaResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $exists = Reserva::query()
            ->where('repartidor_id', $this->record->repartidor_id)
            ->whereDate('fecha_operacion', $data['fecha_operacion'])
            ->where('estado', EstadoReserva::Activa)
            ->where('id', '!=', $this->record->id)
            ->exists();

        if ($exists) {
            Notification::make()
                ->danger()
                ->title('Reserva duplicada')
                ->body('Este repartidor ya tiene reserva activa para esa fecha.')
                ->persistent()
                ->send();

            $this->halt();
        }

        return $data;
    }
}
