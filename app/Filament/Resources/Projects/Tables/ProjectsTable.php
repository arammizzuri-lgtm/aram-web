<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Models\Category;
use App\Models\Project;
use App\Models\Status;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('cover')
                    ->label('')
                    ->state(fn (Project $record) => $record->coverUrl())
                    ->height(44)
                    ->extraImgAttributes(['style' => 'width:64px;object-fit:cover;border-radius:6px;']),
                TextColumn::make('num')->label('No.')->sortable()->toggleable(),
                TextColumn::make('name')
                    ->searchable()->sortable()
                    ->description(fn (Project $record) => $record->location),
                TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Category::map()[$state] ?? $state)
                    ->color('primary'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match (Status::meta()[$state]['tone'] ?? null) {
                        'done' => 'success',
                        'build' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('year')->alignEnd()->toggleable(),
                ToggleColumn::make('is_published')->label('Live'),
            ])
            ->filters([
                SelectFilter::make('category')->options(fn () => Category::options()),
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
