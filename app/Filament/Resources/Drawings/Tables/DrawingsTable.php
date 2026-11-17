<?php

namespace App\Filament\Resources\Drawings\Tables;

use App\Models\Drawing;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DrawingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title_en')->label('Drawing')->searchable()->wrap(),
                TextColumn::make('base')->label('Corrects')->placeholder('A new drawing')->badge()->color('gray'),
                TextColumn::make('walls')->label('Outlines')->state(fn (Drawing $r) => count($r->data['walls'] ?? []))->badge()->color('gray'),
                IconColumn::make('is_live')->label('Live')->boolean()
                    ->state(fn (Drawing $r) => $r->base ? $r->is_live : null),
                TextColumn::make('updated_at')->label('Saved')->since()->sortable(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading('No drawings yet')
            ->emptyStateDescription('Trace a new plan over a survey, or correct the walls of one of the essays’ drawings.');
    }
}
