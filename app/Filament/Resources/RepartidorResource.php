<?php

namespace App\Filament\Resources;

use App\Enums\Ciudad;
use App\Exports\RepartidoresExport;
use App\Filament\Resources\RepartidorResource\Pages;
use App\Models\Repartidor;
use App\Settings\UltimamillaSettings;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
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

class RepartidorResource extends Resource
{
    protected static ?string $model = Repartidor::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Operación';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Repartidor';

    protected static ?string $pluralModelLabel = 'Repartidores';

    protected static ?string $recordTitleAttribute = 'nombre';

    protected static ?string $slug = 'repartidores';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Datos personales')
                    ->schema([
                        TextInput::make('cedula')
                            ->label('Cédula')
                            ->required()
                            ->maxLength(15)
                            ->regex('/^[a-zA-Z0-9]{6,15}$/')
                            ->unique(table: 'repartidores', column: 'cedula', ignoreRecord: true)
                            ->helperText('Solo letras y números, 6-15 caracteres')
                            ->disabled(fn (?Repartidor $record) => $record !== null),

                        TextInput::make('nombre')
                            ->label('Nombre completo')
                            ->required()
                            ->maxLength(150),

                        TextInput::make('telefono')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(20)
                            ->regex('/^[\d\s\+\-\(\)]+$/')
                            ->placeholder('300 000 0000')
                            ->helperText('Opcional, para coordinación'),
                    ]),

                Section::make('Operación')
                    ->schema([
                        Select::make('ciudad')
                            ->label('Ciudad')
                            ->required()
                            ->options(collect(Ciudad::cases())->mapWithKeys(fn (Ciudad $c) => [$c->value => $c->label()]))
                            ->default(Ciudad::MED->value),

                        TextInput::make('placa')
                            ->label('Placa de moto')
                            ->maxLength(10)
                            ->helperText('Opcional')
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->dehydrateStateUsing(fn ($state) => $state ? strtoupper($state) : null),

                        TextInput::make('cupo_personalizado')
                            ->label('Cupo personalizado')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(200)
                            ->helperText('Si está vacío, usa el cupo global del sistema (ver Configuración). Solo definir si este repartidor tiene una capacidad distinta del estándar'),

                        Toggle::make('activo')
                            ->label('Activo')
                            ->default(true)
                            ->helperText('Si está inactivo, no podrá iniciar sesión ni reservar'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('cedula')
                    ->label('Cédula')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),

                TextColumn::make('ciudad')
                    ->label('Ciudad')
                    ->badge()
                    ->formatStateUsing(fn (Ciudad $state) => $state->label())
                    ->color(fn (Ciudad $state) => match ($state) {
                        Ciudad::MED => 'primary',
                        Ciudad::ITAGUI => 'warning',
                    }),

                TextColumn::make('placa')
                    ->label('Placa')
                    ->toggleable()
                    ->fontFamily('mono')
                    ->placeholder('—'),

                TextColumn::make('cupo_efectivo')
                    ->label('Cupo')
                    ->state(fn (Repartidor $record) => $record->cupoEfectivo(app(UltimamillaSettings::class)))
                    ->suffix('/día')
                    ->tooltip(fn (Repartidor $record) => $record->cupo_personalizado ? 'Personalizado' : 'Global'),

                IconColumn::make('activo')
                    ->label('Estado')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('last_login_at')
                    ->label('Último ingreso')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->since()
                    ->placeholder('Nunca'),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->date('d M Y'),
            ])
            ->filters([
                SelectFilter::make('ciudad')
                    ->options(collect(Ciudad::cases())->mapWithKeys(fn (Ciudad $c) => [$c->value => $c->label()])),

                TernaryFilter::make('activo')
                    ->label('Estado')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos')
                    ->default(true),

                Filter::make('sin_login')
                    ->label('Nunca se logueó')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereNull('last_login_at')),
            ])
            ->actions([
                EditAction::make(),

                Action::make('regenerarPin')
                    ->label('Nuevo PIN')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Repartidor $record) => "Regenerar PIN de {$record->nombre}")
                    ->modalDescription('Se generará un nuevo PIN. El actual dejará de funcionar de inmediato.')
                    ->action(function (Repartidor $record) {
                        $nuevoPin = (string) random_int(1000, 9999);
                        $record->forceFill(['pin' => $nuevoPin])->save();

                        activity()->performedOn($record)->causedBy(auth()->user())->log('pin_regenerado');

                        return redirect(RepartidorResource::getUrl('edit', [
                            'record' => $record,
                            'pin_recien_generado' => $nuevoPin,
                        ]));
                    }),

                Action::make('toggleActivo')
                    ->label(fn (Repartidor $record) => $record->activo ? 'Desactivar' : 'Activar')
                    ->icon(fn (Repartidor $record) => $record->activo ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (Repartidor $record) => $record->activo ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (Repartidor $record) {
                        $record->update(['activo' => ! $record->activo]);
                        activity()->performedOn($record)->causedBy(auth()->user())
                            ->log($record->activo ? 'activado' : 'desactivado');
                    }),
            ])
            ->bulkActions([
                BulkAction::make('desactivar')
                    ->label('Desactivar seleccionados')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each(fn (Repartidor $r) => $r->update(['activo' => false]))),
            ])
            ->headerActions([
                Action::make('exportar')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function () {
                        return Excel::download(
                            new RepartidoresExport(soloActivos: true),
                            'repartidores-'.now('America/Bogota')->format('Y-m-d').'.xlsx'
                        );
                    }),
            ])
            ->defaultSort('nombre', 'asc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Sin repartidores registrados')
            ->emptyStateDescription('Crea el primer repartidor para empezar a operar')
            ->emptyStateIcon('heroicon-o-user-group')
            ->emptyStateActions([
                CreateAction::make()
                    ->label('Nuevo repartidor'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRepartidores::route('/'),
            'create' => Pages\CreateRepartidor::route('/create'),
            'edit' => Pages\EditRepartidor::route('/{record}/edit'),
        ];
    }
}
