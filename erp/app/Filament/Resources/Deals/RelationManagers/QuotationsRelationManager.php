<?php

namespace App\Filament\Resources\Deals\RelationManagers;

use App\Models\Quotation;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * What you offered, version by version, and what was agreed to.
 *
 * The versions are the point. Editing an approved deal supersedes a quotation
 * rather than changing it, so this list is the trail: what was sent, when, who
 * said yes and through which channel. It was being written faithfully and shown
 * nowhere — the evidence existed but could not be pointed at, which is most of
 * its value gone.
 */
class QuotationsRelationManager extends RelationManager
{
    protected static string $relationship = 'quotations';

    protected static ?string $title = 'Quotations';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('number')
            ->emptyStateHeading('Nothing quoted yet')
            ->emptyStateDescription('Use "Create quotation" above to freeze the items and prices as they stand.')
            ->defaultSort('version', 'desc')
            ->columns([
                TextColumn::make('number')->label('Quotation')->weight('medium'),

                TextColumn::make('version')
                    ->label('Version')
                    ->formatStateUsing(fn ($state) => 'v'.$state)
                    ->alignEnd(),

                TextColumn::make('quotation_date')->label('Dated')->date('d M Y'),

                TextColumn::make('total')
                    ->label('Total')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state, Quotation $r) => number_format(
                        (float) $state, $r->currency === 'IQD' ? 0 : 2
                    ).' '.$r->currency),

                /*
                 * Who said yes, and how. This is the whole reason approval is
                 * recorded rather than assumed: "you approved this model" needs
                 * to be a record you can point at, not a memory of a message.
                 */
                TextColumn::make('approved_by_name')
                    ->label('Approved by')
                    ->placeholder('—')
                    ->description(fn (Quotation $r) => $r->approved_at
                        ? trim(($r->approval_channel ? ucfirst($r->approval_channel).' · ' : '')
                            .$r->approved_at->format('d M Y'))
                        : null),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Quotation::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'approved' => 'success',
                        'sent' => 'info',
                        'rejected' => 'danger',
                        'superseded' => 'gray',
                        default => 'gray',
                    }),
            ]);
    }
}
