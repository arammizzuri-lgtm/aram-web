<?php

namespace App\Filament\Resources\Greetings\Tables;

use App\Models\Greeting;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class GreetingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on')
            ->columns([
                TextColumn::make('name')->label('Occasion')->searchable()
                    ->description(fn (Greeting $r) => $r->title_en.($r->title_ku ? ' · '.$r->title_ku : '')),
                TextColumn::make('starts_on')->label('Offered')
                    ->formatStateUsing(fn (Greeting $r) => $r->starts_on && $r->ends_on
                        ? $r->starts_on->format($r->yearly ? 'j M' : 'j M Y').' – '.$r->ends_on->format($r->yearly ? 'j M' : 'j M Y').($r->yearly ? ', every year' : '')
                        : 'No dates yet')
                    ->color(fn (Greeting $r) => $r->starts_on ? null : 'warning')
                    ->placeholder('No dates yet'),
                TextColumn::make('on_now')->label('')->state(fn (Greeting $r) => $r->isOn() ? 'On the site now' : null)
                    ->badge()->color('success'),
                ToggleColumn::make('is_published')->label('Offered'),
            ])
            ->recordActions([
                Action::make('preview')->label('Preview')->icon(Heroicon::OutlinedEye)->color('gray')
                    ->url(fn (Greeting $r) => site_url('/card', 'en').'?greeting='.$r->id, shouldOpenInNewTab: true),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No holiday cards')
            ->emptyStateDescription('Add one — Newroz, Eid, the New Year — and between its dates visitors can make it with their name and send it on WhatsApp.');
    }
}
