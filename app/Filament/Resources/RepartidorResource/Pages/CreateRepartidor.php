<?php

namespace App\Filament\Resources\RepartidorResource\Pages;

use App\Filament\Resources\RepartidorResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateRepartidor extends CreateRecord
{
    protected static string $resource = RepartidorResource::class;

    public ?string $pinGenerado = null;

    public function mount(): void
    {
        parent::mount();

        if (request()->has('cedula')) {
            $this->form->fill(['cedula' => request()->string('cedula')->toString()]);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pinGenerado = (string) random_int(1000, 9999);
        $data['pin'] = $this->pinGenerado;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', [
            'record' => $this->record,
            'pin_recien_generado' => $this->pinGenerado,
        ]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Repartidor creado')
            ->body("PIN generado: {$this->pinGenerado}. Cópialo ahora, no se podrá ver de nuevo.")
            ->persistent();
    }
}
