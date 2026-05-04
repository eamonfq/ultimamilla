<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditoriaResource\Pages;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class AuditoriaResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Sistema';

    protected static ?int $navigationSort = 80;

    protected static ?string $modelLabel = 'Actividad';

    protected static ?string $pluralModelLabel = 'Auditoria';

    protected static ?string $slug = 'auditoria';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('causer.name')
                    ->label('Usuario')
                    ->default('Sistema')
                    ->searchable(),

                TextColumn::make('description')
                    ->label('Accion')
                    ->searchable()
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pin_regenerado' => 'warning',
                        'desactivado' => 'danger',
                        'activado' => 'success',
                        'pinit_import.replaced' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('subject_type')
                    ->label('Modelo')
                    ->searchable()
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—'),

                TextColumn::make('subject_id')
                    ->label('ID')
                    ->placeholder('—'),

                TextColumn::make('properties')
                    ->label('Cambios')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(80)
                    ->formatStateUsing(function ($state) {
                        if (empty($state)) {
                            return '—';
                        }
                        $data = is_array($state) ? $state : json_decode($state, true);

                        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    }),
            ])
            ->filters([
                SelectFilter::make('description')
                    ->label('Accion')
                    ->options(fn () => Activity::query()->distinct()->pluck('description', 'description')->toArray()),

                SelectFilter::make('causer_id')
                    ->label('Usuario')
                    ->options(fn () => User::pluck('name', 'id')->toArray()),

                Filter::make('fecha')
                    ->form([
                        DatePicker::make('desde'),
                        DatePicker::make('hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['desde'], fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['hasta'], fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextEntry::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d M Y H:i:s'),

                TextEntry::make('causer.name')
                    ->label('Usuario')
                    ->default('Sistema'),

                TextEntry::make('description')
                    ->label('Accion'),

                TextEntry::make('subject_type')
                    ->label('Modelo')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—'),

                TextEntry::make('subject_id')
                    ->label('ID del registro'),

                TextEntry::make('properties')
                    ->label('Propiedades')
                    ->formatStateUsing(function ($state) {
                        if (empty($state)) {
                            return '—';
                        }
                        $data = is_array($state) ? $state : json_decode($state, true);

                        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    })
                    ->columnSpanFull(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditoria::route('/'),
            'view' => Pages\ViewAuditoria::route('/{record}'),
        ];
    }
}
