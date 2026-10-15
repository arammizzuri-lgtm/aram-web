<?php

namespace App\Filament\Resources\Testimonials\Tables;

use App\Models\Testimonial;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
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
                    ->state(fn (Testimonial $r) => $r->quote_en ?: $r->quote_ku)
                    ->limit(90)->wrap(),
                TextColumn::make('person_name')->label('Who')->searchable()
                    ->description(fn (Testimonial $r) => $r->attribution('en')),
                TextColumn::make('project.name')->label('Project')->placeholder('—'),
                TextColumn::make('languages')->label('Languages')->badge()
                    ->state(fn (Testimonial $r) => array_values(array_filter([
                        filled($r->quote_en) ? 'EN' : null,
                        filled($r->quote_ku) ? 'KU' : null,
                    ]))),
                TextColumn::make('media')->label('Voice · portrait')->badge()->placeholder('—')
                    ->state(fn (Testimonial $r) => array_values(array_filter([
                        $r->voice ? 'Voice' : null,
                        $r->portrait_sketch ? 'Portrait' : null,
                    ])))
                    ->color(fn (Testimonial $r) => $r->media_consent ? 'success' : 'gray')
                    ->tooltip(fn (Testimonial $r) => $r->media_consent ? null : 'Not shown until they agree'),
                ToggleColumn::make('is_published')->label('Live'),
            ])
            ->filters([
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
