<?php

namespace App\Filament\Resources\JobOpenings\Tables;

use App\Models\JobOpening;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class JobOpeningsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // drag to set the order the careers page lists them in
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->modifyQueryUsing(fn ($query) => $query->withCount('applications'))
            ->columns([
                TextColumn::make('title_en')->label('Position')->searchable()
                    ->description(fn (JobOpening $r) => $r->title_ku),
                TextColumn::make('type')->badge()->color('gray')
                    ->formatStateUsing(fn (JobOpening $r) => $r->typeLabel()),
                TextColumn::make('closes_at')->label('Closes')->date('j M Y')->placeholder('—')
                    ->color(fn (JobOpening $r) => $r->closes_at?->isPast() ? 'danger' : null),
                TextColumn::make('applications_count')->label('Applications')->badge()
                    ->color(fn ($state) => $state ? 'primary' : 'gray'),
                ToggleColumn::make('is_open')->label('Listed'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No positions yet')
            ->emptyStateDescription('Add one and it appears on the careers page. With none listed, the page still invites open applications.');
    }
}
