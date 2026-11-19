<?php

namespace App\Filament\Resources\Testimonials\Tables;

use App\Models\Testimonial;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class TestimonialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // drag to set the order the homepage shows them in
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('quote_en')->label('Quote')
                    ->state(fn (Testimonial $r) => $r->quote_en ?: $r->quote_ku ?: $r->quote_ar)
                    ->limit(90)->wrap()
                    // sent by the client through a review link, not yet published
                    ->description(fn (Testimonial $r) => $r->from_client && ! $r->is_published ? '✉ New from the client — check it, choose where it shows, then switch on Live' : null),
                TextColumn::make('person_name')->label('Who')->searchable()
                    ->description(fn (Testimonial $r) => $r->attribution('en')),
                TextColumn::make('project.name')->label('Project')->placeholder('—'),
                TextColumn::make('languages')->label('Shows on')->badge()->placeholder('Nowhere')
                    ->state(fn (Testimonial $r) => array_map('strtoupper', $r->shownLanguages()))
                    // green: in its own words of that language; grey: the original words shown there
                    ->color(fn (Testimonial $r, string $state) => filled($r->ownQuote(strtolower($state))) ? 'success' : 'gray')
                    ->tooltip('Green: written in that language. Grey: shown there in the client’s own words.'),
                TextColumn::make('media')->label('Voice · portrait')->badge()->placeholder('—')
                    ->state(fn (Testimonial $r) => array_values(array_filter([
                        $r->voice ? 'Voice' : null,
                        $r->portrait_sketch ? 'Portrait' : ($r->portrait ? 'Photo — open to draw' : null),
                    ])))
                    ->color(fn (Testimonial $r) => $r->media_consent ? 'success' : 'gray')
                    ->tooltip(fn (Testimonial $r) => $r->media_consent ? null : 'Not shown until they agree'),
                ToggleColumn::make('is_published')->label('Live')
                    // a quote shows only in the languages it is written in: say so, or it looks lost
                    ->afterStateUpdated(fn (Testimonial $r, bool $state) => $state ? self::whereItShows($r) : null),
            ])
            ->filters([
                TernaryFilter::make('is_published')->label('Published'),
                TrashedFilter::make()->label('Trash'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->label('Move to trash'),
                RestoreAction::make(),
                ForceDeleteAction::make()->label('Delete forever'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Move to trash'),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make()->label('Delete forever'),
                ]),
            ]);
    }

    /** After switching a quote on: which homepages show it, and how to reach the others. */
    public static function whereItShows(Testimonial $r): void
    {
        $names = ['en' => 'English', 'ku' => 'Kurdish (/ku)', 'ar' => 'Arabic (/ar)'];
        $shown = array_intersect_key($names, array_flip($r->shownLanguages()));
        $missing = array_diff_key($names, $shown);

        Notification::make()->success()->persistent()
            ->title($shown ? 'Live on the '.implode(', ', $shown).' site' : 'Live, but no language is chosen to show it')
            ->body($missing ? 'To show it on the '.implode(' and ', $missing).' site too, open it and tick it under “Show on”.' : null)
            ->send();
    }
}
