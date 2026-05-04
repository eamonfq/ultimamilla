<?php

namespace App\Filament\Resources\ReservaResource\Pages;

use App\Enums\EstadoReserva;
use App\Filament\Resources\ReservaResource;
use App\Models\Reserva;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateReserva extends CreateRecord
{
    protected static string $resource = ReservaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $exists = Reserva::query()
            ->where('repartidor_id', $data['repartidor_id'])
            ->whereDate('fecha_operacion', $data['fecha_operacion'])
            ->where('estado', EstadoReserva::Activa)
            ->exists();

        if ($exists) {
            Notification::make()
                ->danger()
                ->title('Reserva duplicada')
                ->body('Este repartidor ya tiene reserva activa para esa fecha. Edita la existente.')
                ->persistent()
                ->send();

            $this->halt();
        }

        return $data;
    }
}
