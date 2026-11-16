<?php

namespace App\Filament\Resources\Essays\Tables;

use App\Models\Essay;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class EssaysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                ImageColumn::make('image')->label('')->disk('public')->height(40),
                TextColumn::make('title_en')->label('Essay')->searchable()->wrap()
                    ->description(fn (Essay $r) => Str::limit((string) $r->summary_en, 90)),
                TextColumn::make('date')->label('Date')->date()->sortable(),
                TextColumn::make('sections')->label('Sections')->state(fn (Essay $r) => count($r->sections()))->badge()->color('gray')->toggleable(),
                IconColumn::make('is_published')->label('Shown')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading('No essays yet')
            ->emptyStateDescription('Essays with drawings of their own, like the Citadel houses, are written in code and are not listed here.');
    }
}
