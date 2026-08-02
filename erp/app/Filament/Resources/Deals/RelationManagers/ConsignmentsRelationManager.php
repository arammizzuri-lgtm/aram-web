<?php

namespace App\Filament\Resources\Deals\RelationManagers;

use App\Models\Consignment;
use App\Services\Shipping\ConsignmentWriter;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Where this deal's goods are.
 *
 * A deal can arrive under several tracking numbers and a tracking number can
 * carry several deals, so this is the many-to-many seen from the deal's side —
 * the side a customer asking "where is my order?" is asking about.
 *
 * The freight share is shown but not edited here: dividing one bill between
 * several deals is a decision about the whole bill, so it belongs on the
 * consignment where every share can be seen adding up at once.
 */
class ConsignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'consignments';

    protected static ?string $title = 'Shipping';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('tracking_number')
            ->emptyStateHeading('Not shipped yet')
            ->emptyStateDescription('Attach the tracking number your forwarder gives you.')
            ->columns([
                TextColumn::make('tracking_number')->label('Tracking')->weight('medium'),

                TextColumn::make('mode')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state) => Consignment::MODES[$state] ?? $state),

                TextColumn::make('collectionPoint.name')->label('From')->placeholder('—'),

                TextColumn::make('boxes')->label('Boxes')->alignEnd(),

                TextColumn::make('gross_weight_kg')
                    ->label('Weight')
                    ->formatStateUsing(fn ($state) => $state ? number_format((float) $state, 2).' kg' : '—')
                    ->alignEnd(),

                TextColumn::make('cbm')
                    ->label('CBM')
                    ->formatStateUsing(fn ($state) => $state ? number_format((float) $state, 3) : '—')
                    ->alignEnd(),

                /*
                 * This deal's share of the freight, not the whole bill — on a
                 * consolidated shipment those are different numbers, and the one
                 * that belongs to this deal's profit is the share.
                 */
                TextColumn::make('freight_share')
                    ->label('Freight on this deal')
                    ->state(fn (Consignment $r) => (float) ($r->pivot?->freight_share_base ?? 0))
                    ->money('USD')
                    ->alignEnd()
                    ->visible(fn () => auth()->user()?->can('view_cost')),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Consignment::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'delivered', 'arrived' => 'success',
                        'in_transfer' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Attach a tracking number')
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['tracking_number'])
                    /*
                     * Attaching a deal to goods already in transfer says where
                     * the deal is, exactly as logging the consignment would.
                     *
                     * Every attached consignment is re-read rather than just the
                     * new one: the answer is the furthest along any of them has
                     * got, and DealProgress only ever moves forward, so asking
                     * them all is both simplest and right.
                     */
                    ->after(function () {
                        $writer = app(ConsignmentWriter::class);

                        foreach ($this->getOwnerRecord()->consignments as $consignment) {
                            $writer->syncDealStatuses($consignment);
                        }
                    }),
            ])
            ->recordActions([
                DetachAction::make(),
            ]);
    }
}
