<?php

namespace App\Filament\Resources\RepartidorResource\Pages;

use App\Filament\Resources\RepartidorResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\View\View;

class EditRepartidor extends EditRecord
{
    protected static string $resource = RepartidorResource::class;

    public ?string $pinRecienGenerado = null;

    public ?string $mensajeWhatsApp = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if (request()->has('pin_recien_generado')) {
            $this->pinRecienGenerado = request()->string('pin_recien_generado')->toString();
            $this->mensajeWhatsApp = $this->armarMensajeWhatsApp(
                $this->record->nombre,
                $this->record->cedula,
                $this->pinRecienGenerado
            );
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('regenerarPin')
                ->label('Regenerar PIN')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Regenerar PIN del repartidor')
                ->modalDescription('Se generará un nuevo PIN de 4 dígitos. El PIN actual dejará de funcionar inmediatamente. ¿Continuar?')
                ->modalSubmitActionLabel('Sí, regenerar')
                ->action(function () {
                    $nuevoPin = (string) random_int(1000, 9999);
                    $this->record->forceFill(['pin' => $nuevoPin])->save();

                    $this->pinRecienGenerado = $nuevoPin;
                    $this->mensajeWhatsApp = $this->armarMensajeWhatsApp(
                        $this->record->nombre,
                        $this->record->cedula,
                        $nuevoPin
                    );

                    activity()
                        ->performedOn($this->record)
                        ->causedBy(auth()->user())
                        ->log('pin_regenerado');

                    Notification::make()
                        ->success()
                        ->title('PIN regenerado')
                        ->body("Nuevo PIN: {$nuevoPin}. Cópialo ahora.")
                        ->persistent()
                        ->send();
                }),
        ];
    }

    public function getHeader(): ?View
    {
        if (! $this->pinRecienGenerado) {
            return null;
        }

        return view('filament.resources.repartidor-resource.pages.pin-banner', [
            'pinRecienGenerado' => $this->pinRecienGenerado,
            'mensajeWhatsApp' => $this->mensajeWhatsApp,
        ]);
    }

    public function armarMensajeWhatsApp(string $nombre, string $cedula, string $pin): string
    {
        $primerNombre = explode(' ', trim($nombre))[0];
        $url = config('app.url').'/repartidor/login';

        return "Hola {$primerNombre}, tu acceso al sistema de reservas está listo.\n\n"
            ."Cédula: {$cedula}\n"
            ."PIN: {$pin}\n\n"
            ."Ingresa aquí: {$url}\n\n"
            .'Guarda este mensaje, el PIN no se volverá a mostrar.';
    }
}
