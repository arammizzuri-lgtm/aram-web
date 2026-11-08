<?php

namespace App\Filament\Resources\Partners\Tables;

use App\Models\Partner;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PartnersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('logo')->label('')->disk('public')->height(36),
                TextColumn::make('name')->searchable()->sortable()->description(fn (Partner $r) => $r->description_en),
                TextColumn::make('category')->label('Trade')->badge()->formatStateUsing(fn ($state) => Partner::categoryName((string) $state, 'en')),
                TextColumn::make('website')->limit(30)->placeholder('—'),
                IconColumn::make('is_published')->label('Shown')->boolean(),
            ])
            ->filters([SelectFilter::make('category')->label('Trade')->options(Partner::categoryOptions())])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading('No partners yet')
            ->emptyStateDescription('Add the suppliers, brands and contractors you build with. The Partners page appears on the website once the first one is added.');
    }
}