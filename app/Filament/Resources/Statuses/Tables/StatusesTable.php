<?php

namespace App\Filament\Resources\Statuses\Tables;

use App\Models\Project;
use App\Models\Status;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StatusesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->label('Status')->searchable()->sortable(),
                TextColumn::make('badge')->label('Badge')->badge()
                    ->state(fn (Status $record) => $record->badge ?: $record->name)
                    ->color(fn (Status $record) => match ($record->tone) {
                        'done' => 'success',
                        'build' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('tone')->label('Colour')
                    ->formatStateUsing(fn ($state) => Status::TONES[$state] ?? $state),
                TextColumn::make('projects_count')->label('Projects')
                    ->state(fn (Status $record) => Project::where('status', $record->name)->count())
                    ->badge()->color('gray'),
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
