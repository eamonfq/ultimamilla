<?php

namespace App\Filament\Widgets;

use App\Enums\Ciudad;
use App\Enums\EstadoReserva;
use App\Models\Repartidor;
use App\Models\Reserva;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RepartidoresFaltanReservarWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function getTableHeading(): string
    {
        $manana = today('America/Bogota')->addDay()->locale('es')->isoFormat('dddd D MMM');

        return "Repartidores sin reserva para {$manana}";
    }

    public function table(Table $table): Table
    {
        $manana = today('America/Bogota')->addDay();

        $repartidoresQueReservaron = Reserva::query()
            ->where('estado', EstadoReserva::Activa)
            ->whereDate('fecha_operacion', $manana)
            ->pluck('repartidor_id');

        return $table
            ->query(
                Repartidor::query()
                    ->activos()
                    ->whereNotIn('id', $repartidoresQueReservaron)
                    ->orderBy('nombre')
            )
            ->columns([
                TextColumn::make('cedula')->fontFamily('mono'),
                TextColumn::make('nombre')->weight('semibold'),
                TextColumn::make('telefono')
                    ->placeholder('—')
                    ->url(fn (Repartidor $record) => $record->telefono
                        ? 'https://wa.me/57'.preg_replace('/\D/', '', $record->telefono)
                        : null)
                    ->openUrlInNewTab(),
                TextColumn::make('ciudad')
                    ->badge()
                    ->formatStateUsing(fn (Ciudad $state) => $state->label())
                    ->color(fn (Ciudad $state) => match ($state) {
                        Ciudad::MED => 'primary',
                        Ciudad::ITAGUI => 'warning',
                    }),
                TextColumn::make('last_login_at')
                    ->label('Último ingreso')
                    ->since()
                    ->placeholder('Nunca'),
            ])
            ->emptyStateHeading('Todos los repartidores han reservado')
            ->emptyStateDescription('No falta nadie por confirmar mañana.')
            ->emptyStateIcon('heroicon-o-check-badge')
            ->paginated(false);
    }
}
