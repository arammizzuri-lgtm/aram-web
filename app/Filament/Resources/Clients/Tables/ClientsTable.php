<?php

namespace App\Filament\Resources\Clients\Tables;

use App\Models\Client;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('logo')
                    ->label('Logo')
                    ->state(fn (Client $record) => $record->logo ? Client::resolveImage($record->logo) : null)
                    ->height(40)
                    ->extraImgAttributes(['style' => 'width:56px;object-fit:contain;'])
                    ->default(null),
                TextColumn::make('name')
                    ->searchable()->sortable()
                    ->description(fn (Client $record) => $record->sub_en),
                TextColumn::make('logo_mono')
                    ->label('Logo type')
                    ->badge()
                    ->state(fn (Client $record) => $record->logo_mono ? 'Uploaded (mono)' : ($record->mark ? 'Built-in mark' : 'None'))
                    ->color(fn (Client $record) => $record->logo_mono ? 'success' : 'gray'),
                ToggleColumn::make('is_published')->label('Live'),
            ])
            ->filters([
                TernaryFilter::make('is_published')->label('Published'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
