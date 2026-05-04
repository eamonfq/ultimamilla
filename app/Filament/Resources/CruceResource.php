<?php

namespace App\Filament\Resources;

use App\Enums\PatronCruce;
use App\Exports\CrucesExport;
use App\Exports\ReporteMensualExport;
use App\Filament\Resources\CruceResource\Pages;
use App\Models\Cruce;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

class CruceResource extends Resource
{
    protected static ?string $model = Cruce::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|\UnitEnum|null $navigationGroup = 'Pinit';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'Cruce';

    protected static ?string $pluralModelLabel = 'Cruces';

    protected static ?string $slug = 'cruces';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fecha_operacion')
                    ->label('Fecha')
                    ->date('D, d M Y')
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('repartidor.nombre')
                    ->label('Repartidor')
                    ->searchable()
                    ->weight('semibold'),

                TextColumn::make('repartidor.cedula')
                    ->label('Cedula')
                    ->searchable()
                    ->fontFamily('mono')
                    ->copyable(),

                TextColumn::make('reservado')
                    ->numeric()
                    ->suffix(' paq.')
                    ->alignEnd(),

                TextColumn::make('asignado')
                    ->numeric()
                    ->suffix(' paq.')
                    ->alignEnd(),

                TextColumn::make('entregado')
                    ->numeric()
                    ->suffix(' paq.')
                    ->alignEnd()
                    ->weight('semibold'),

                TextColumn::make('cumplimiento_pct')
                    ->label('Cumplimiento')
                    ->numeric(2)
                    ->suffix('%')
                    ->alignEnd()
                    ->color(fn (?string $state) => match (true) {
                        $state === null => 'gray',
                        (float) $state >= 90 => 'success',
                        (float) $state >= 80 => 'warning',
                        default => 'danger',
                    }),

                TextColumn::make('patron')
                    ->label('Patron')
                    ->badge()
                    ->formatStateUsing(fn (?PatronCruce $state) => $state?->label() ?? '—')
                    ->color(fn (?PatronCruce $state) => $state?->color() ?? 'gray'),

                TextColumn::make('import.created_at')
                    ->label('Importado')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('fecha_operacion')
                    ->form([
                        DatePicker::make('desde'),
                        DatePicker::make('hasta'),
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

                SelectFilter::make('patron')
                    ->label('Patron')
                    ->options(collect(PatronCruce::cases())->mapWithKeys(fn (PatronCruce $p) => [$p->value => $p->label()])),

                Filter::make('cumplimiento_minimo')
                    ->form([
                        TextInput::make('min')
                            ->label('Cumplimiento minimo (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(200),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['min'],
                            fn (Builder $q, $min) => $q->where('cumplimiento_pct', '>=', (float) $min)
                        );
                    }),
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
                            new CrucesExport(
                                desde: Carbon::parse($data['desde']),
                                hasta: Carbon::parse($data['hasta']),
                            ),
                            "cruces-{$data['desde']}-a-{$data['hasta']}.xlsx"
                        );
                    }),
                Action::make('reporteMensual')
                    ->label('Reporte mensual')
                    ->icon('heroicon-o-document-chart-bar')
                    ->color('success')
                    ->form([
                        Select::make('mes')
                            ->required()
                            ->options(function () {
                                $opciones = [];
                                for ($i = 0; $i < 6; $i++) {
                                    $mes = now('America/Bogota')->copy()->subMonths($i)->startOfMonth();
                                    $opciones[$mes->format('Y-m')] = $mes->locale('es')->isoFormat('MMMM YYYY');
                                }

                                return $opciones;
                            })
                            ->default(now('America/Bogota')->subMonth()->format('Y-m')),
                    ])
                    ->action(function (array $data) {
                        $mes = Carbon::createFromFormat('Y-m', $data['mes'])->startOfMonth();

                        return Excel::download(
                            new ReporteMensualExport($mes),
                            "reporte-mensual-{$data['mes']}.xlsx"
                        );
                    }),
            ])
            ->defaultSort('fecha_operacion', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCruces::route('/'),
        ];
    }
}
