<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Category;
use App\Models\Project;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->label('Name (EN)')->searchable()->sortable(),
                TextColumn::make('name_ku')->label('Name (KU)')
                    ->extraAttributes(['dir' => 'rtl'])->toggleable(),
                TextColumn::make('key')->badge()->color('primary'),
                // Counts every project carrying the category, not just the ones
                // where it happens to be the primary pick.
                TextColumn::make('projects_count')->label('Projects')
                    ->state(fn (Category $record) => Project::whereJsonContains('categories', $record->key)->count())
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
