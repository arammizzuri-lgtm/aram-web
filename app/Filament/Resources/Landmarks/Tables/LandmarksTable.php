<?php

namespace App\Filament\Resources\Landmarks\Tables;

use App\Models\Landmark;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LandmarksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('sort_order')->label('#')->badge()->color('gray'),
                ImageColumn::make('image')->label('')->disk('public')->height(40),
                TextColumn::make('name_en')->label('Landmark')->description(fn (Landmark $r) => $r->period_en)->searchable(),
                TextColumn::make('region')->label('Part')->badge()->formatStateUsing(fn ($state) => Landmark::REGIONS[$state][0] ?? $state),
                TextColumn::make('era')->label('Age')->formatStateUsing(fn ($state) => Landmark::ERAS[$state][0] ?? $state)->toggleable(),
                IconColumn::make('unesco')->label('UNESCO')->boolean(),
                IconColumn::make('pin')->label('Pin')->boolean()->state(fn (Landmark $r) => $r->lat !== null),
                IconColumn::make('is_published')->label('Shown')->boolean(),
            ])
            ->filters([
                SelectFilter::make('region')->label('Part of Kurdistan')->options(Landmark::options(Landmark::REGIONS)),
                SelectFilter::make('era')->label('Age')->options(Landmark::options(Landmark::ERAS)),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading('No landmarks yet');
    }
}
