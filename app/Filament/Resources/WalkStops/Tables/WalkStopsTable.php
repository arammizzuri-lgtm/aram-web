<?php

namespace App\Filament\Resources\WalkStops\Tables;

use App\Models\WalkStop;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WalkStopsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('sort_order')->label('#')->badge()->color('gray'),
                ImageColumn::make('image')->label('')->disk('public')->height(40),
                TextColumn::make('name_en')->label('Stop')->description(fn (WalkStop $r) => $r->built_en)->searchable(),
                TextColumn::make('name_ar')->label('Arabic')->extraAttributes(['dir' => 'rtl'])->toggleable(),
                IconColumn::make('pin')->label('Pin')->boolean()->state(fn (WalkStop $r) => $r->lat !== null),
                IconColumn::make('is_published')->label('Shown')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading('No stops yet');
    }
}