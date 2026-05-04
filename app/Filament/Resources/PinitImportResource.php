<?php

namespace App\Filament\Resources;

use App\Enums\StatusImport;
use App\Filament\Resources\PinitImportResource\Pages;
use App\Models\PinitImport;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PinitImportResource extends Resource
{
    protected static ?string $model = PinitImport::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-arrow-up';

    protected static string|\UnitEnum|null $navigationGroup = 'Pinit';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'Import';

    protected static ?string $pluralModelLabel = 'Imports';

    protected static ?string $slug = 'pinit-imports';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fecha_archivo')
                    ->label('Fecha archivo')
                    ->date('D, d M Y')
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (StatusImport $state) => $state->label())
                    ->color(fn (StatusImport $state) => $state->color()),

                TextColumn::make('total_filas')
                    ->label('Filas')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('importadoPor.name')
                    ->label('Subido por')
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Subido el')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(collect(StatusImport::cases())->mapWithKeys(fn (StatusImport $s) => [$s->value => $s->label()])),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPinitImports::route('/'),
            'view' => Pages\ViewPinitImport::route('/{record}'),
        ];
    }
}
