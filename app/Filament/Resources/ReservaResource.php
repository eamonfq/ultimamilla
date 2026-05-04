<?php

namespace App\Filament\Resources;

use App\Enums\Ciudad;
use App\Enums\EstadoReserva;
use App\Exports\ReservasExport;
use App\Filament\Resources\ReservaResource\Pages;
use App\Models\Repartidor;
use App\Models\Reserva;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Facades\Excel;

class ReservaResource extends Resource
{
    protected static ?string $model = Reserva::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Operación';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'Reserva';

    protected static ?string $pluralModelLabel = 'Reservas';

    protected static ?string $slug = 'reservas';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('repartidor_id')
                    ->label('Repartidor')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->relationship('repartidor', 'nombre', fn (Builder $query) => $query->activos())
                    ->getOptionLabelFromRecordUsing(fn (Repartidor $r) => "{$r->cedula} — {$r->nombre}")
                    ->disabled(fn (?Reserva $record) => $record !== null),

                DatePicker::make('fecha_operacion')
                    ->label('Fecha de operación')
                    ->required()
                    ->minDate(today('America/Bogota'))
                    ->native(false)
                    ->displayFormat('d M Y')
                    ->helperText('Día en que el repartidor opera'),

                TextInput::make('paquetes')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(500)
                    ->suffix('paquetes'),

                Select::make('rutas')
                    ->required()
                    ->options([1 => '1 ruta', 2 => '2 rutas', 3 => '3 rutas'])
                    ->default(1),

                Select::make('estado')
                    ->required()
                    ->options(collect(EstadoReserva::cases())->mapWithKeys(fn (EstadoReserva $e) => [$e->value => $e->label()]))
                    ->default(EstadoReserva::Activa->value)
                    ->disabled(fn (?Reserva $record) => $record?->isLocked()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fecha_operacion')
                    ->label('Fecha')
                    ->date('D, d M Y')
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('repartidor.cedula')
                    ->label('Cédula')
                    ->searchable()
                    ->fontFamily('mono')
                    ->copyable(),

                TextColumn::make('repartidor.nombre')
                    ->label('Repartidor')
                    ->searchable()
                    ->weight('semibold'),

                TextColumn::make('repartidor.ciudad')
                    ->label('Ciudad')
                    ->badge()
                    ->formatStateUsing(fn (Ciudad $state) => $state->label())
                    ->color(fn (Ciudad $state) => match ($state) {
                        Ciudad::MED => 'primary',
                        Ciudad::ITAGUI => 'warning',
                    }),

                TextColumn::make('paquetes')
                    ->sortable()
                    ->alignEnd()
                    ->suffix(' paq.'),

                TextColumn::make('rutas')
                    ->sortable()
                    ->alignCenter()
                    ->label('Rutas'),

                TextColumn::make('estado')
                    ->badge()
                    ->formatStateUsing(fn (EstadoReserva $state) => $state->label())
                    ->color(fn (EstadoReserva $state) => match ($state) {
                        EstadoReserva::Activa => 'success',
                        EstadoReserva::Cancelada => 'gray',
                    }),

                IconColumn::make('is_locked')
                    ->label('Cerrada')
                    ->boolean()
                    ->state(fn (Reserva $record) => $record->isLocked())
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('warning')
                    ->falseColor('success'),

                TextColumn::make('asignacion.paquetes_asignados')
                    ->label('Asignado')
                    ->placeholder('—')
                    ->color('success')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Creada')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->since(),
            ])
            ->filters([
                Filter::make('fecha_operacion')
                    ->form([
                        DatePicker::make('desde')
                            ->default(today('America/Bogota')),
                        DatePicker::make('hasta')
                            ->default(today('America/Bogota')->addDays(7)),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['desde'], fn (Builder $q, $date) => $q->whereDate('fecha_operacion', '>=', $date))
                            ->when($data['hasta'], fn (Builder $q, $date) => $q->whereDate('fecha_operacion', '<=', $date));
                    }),

                SelectFilter::make('repartidor_id')
                    ->label('Repartidor')
                    ->relationship('repartidor', 'nombre')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('estado')
                    ->label('Estado')
                    ->trueLabel('Activas')
                    ->falseLabel('Canceladas')
                    ->queries(
                        true: fn (Builder $query) => $query->where('estado', EstadoReserva::Activa),
                        false: fn (Builder $query) => $query->where('estado', EstadoReserva::Cancelada),
                    )
                    ->default(true),

                Filter::make('hoy')
                    ->label('Solo hoy')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereDate('fecha_operacion', today('America/Bogota'))),

                Filter::make('manana')
                    ->label('Solo mañana')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereDate('fecha_operacion', today('America/Bogota')->addDay())),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (Reserva $record) => ! $record->isLocked()),

                Action::make('cancelar')
                    ->label('Cancelar')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->modalDescription('Esta acción cancelará la reserva. El repartidor verá la card como "sin reservar" nuevamente.')
                    ->action(fn (Reserva $record) => $record->update(['estado' => EstadoReserva::Cancelada]))
                    ->visible(fn (Reserva $record) => $record->estado === EstadoReserva::Activa && ! $record->isLocked()),
            ])
            ->bulkActions([
                BulkAction::make('cancelarBulk')
                    ->label('Cancelar seleccionadas')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each(fn (Reserva $r) => $r->update(['estado' => EstadoReserva::Cancelada]))),
            ])
            ->headerActions([
                Action::make('exportar')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->form([
                        DatePicker::make('desde')->required()->default(now()->startOfMonth()),
                        DatePicker::make('hasta')->required()->default(now()->endOfMonth()),
                    ])
                    ->action(function (array $data) {
                        return Excel::download(
                            new ReservasExport(
                                desde: Carbon::parse($data['desde']),
                                hasta: Carbon::parse($data['hasta']),
                            ),
                            "reservas-{$data['desde']}-a-{$data['hasta']}.xlsx"
                        );
                    }),
            ])
            ->defaultSort('fecha_operacion', 'asc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Sin reservas en este filtro')
            ->emptyStateDescription('Ajusta los filtros o crea una reserva nueva.')
            ->emptyStateIcon('heroicon-o-calendar-days');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReservas::route('/'),
            'create' => Pages\CreateReserva::route('/create'),
            'edit' => Pages\EditReserva::route('/{record}/edit'),
        ];
    }
}
