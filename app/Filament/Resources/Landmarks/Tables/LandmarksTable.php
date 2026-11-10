<?php

namespace App\Filament\Resources\Landmarks\Tables;

use App\Models\Landmark;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
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
                TextColumn::make('sections')->label('Sections')->state(fn (Landmark $r) => count($r->sections()))->badge()->color('gray')->toggleable(),
                TextColumn::make('photos')->label('Photos')->state(fn (Landmark $r) => count($r->images()))->badge()->color('gray')->toggleable(),
                IconColumn::make('unesco')->label('UNESCO')->boolean(),
                IconColumn::make('pin')->label('Pin')->boolean()->state(fn (Landmark $r) => $r->lat !== null),
                IconColumn::make('is_published')->label('Shown')->boolean(),
            ])
            ->filters([
                SelectFilter::make('region')->label('Part of Kurdistan')->options(Landmark::options(Landmark::REGIONS)),
                SelectFilter::make('era')->label('Age')->options(Landmark::options(Landmark::ERAS)),
            ])
            ->recordActions([
                EditAction::make(),
                // a copy to start a new landmark from: unpublished, at the end of the list, its own address
                ReplicateAction::make()->label('Duplicate')->excludeAttributes(['slug'])
                    ->beforeReplicaSaved(function (Landmark $replica) {
                        $replica->name_en = $replica->name_en.' (copy)';
                        $replica->is_published = false;
                        $replica->sort_order = (int) Landmark::max('sort_order') + 1;
                    })
                    ->successRedirectUrl(fn (Landmark $replica) => \App\Filament\Resources\Landmarks\LandmarkResource::getUrl('edit', ['record' => $replica])),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No landmarks yet');
    }
}
