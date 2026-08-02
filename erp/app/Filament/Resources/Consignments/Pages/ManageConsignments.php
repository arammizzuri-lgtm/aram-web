<?php

namespace App\Filament\Resources\Consignments\Pages;

use App\Filament\Resources\Consignments\ConsignmentResource;
use App\Models\Consignment;
use App\Services\Shipping\ConsignmentWriter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class ManageConsignments extends ManageRecords
{
    protected static string $resource = ConsignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Log a tracking number')->after(fn ($record) => $this->settle($record))];
    }

    protected function getTableActions(): array
    {
        return [
            EditAction::make()->after(fn ($record) => $this->settle($record)),
            $this->splitAction(),
        ];
    }

    /**
     * The single-deal case, handled without asking.
     *
     * One customer, one tracking number, one bill that is entirely theirs —
     * there is no decision to make, so no interface appears and the freight
     * simply lands on the deal.
     *
     * The deals attached also take their stage from this: goods in transfer
     * mean a deal that is shipping, and arrived goods mean a deal that has
     * landed. Left to be done by hand it would be done for the tracking number
     * and forgotten for the deals, which are the records anybody actually
     * looks at.
     */
    private function settle(Consignment $record): void
    {
        $writer = app(ConsignmentWriter::class);

        $writer->applyWholeBillToSoleDeal($record->fresh());
        $writer->syncDealStatuses($record->fresh());

        foreach ($writer->warnings($record->fresh()) as $warning) {
            Notification::make()->title($warning)->warning()->persistent()->send();
        }
    }

    /**
     * Divide a bill that covers more than one customer.
     *
     * The suggestion divides by what the mode is actually charged for — CBM by
     * sea, kilograms by air — and every figure stays editable. Splitting by
     * goods value instead would over-charge small expensive crystals and
     * under-charge bulky cheap fabric, which for this product mix is backwards.
     */
    private function splitAction(): Action
    {
        return Action::make('split')
            ->label('Split freight')
            ->icon('heroicon-o-scale')
            ->color('gray')
            // Only when there is genuinely something to divide.
            ->visible(fn (Consignment $record) => $record->isShared()
                && auth()->user()?->can('view_cost'))
            ->modalHeading(fn (Consignment $record) => "Split {$record->tracking_number}")
            ->modalDescription(fn (Consignment $record) => sprintf(
                'Suggested by %s, because that is what %s freight is charged for. Adjust anything.',
                $record->splitBasis() === 'cbm' ? 'volume' : 'weight',
                $record->isAir() ? 'air' : 'sea',
            ))
            ->schema(fn (Consignment $record) => $this->splitFields($record))
            ->action(function (Consignment $record, array $data) {
                $shares = [];

                foreach ($record->deals as $deal) {
                    $shares[$deal->id] = $data['share_'.$deal->id] ?? 0;
                }

                app(ConsignmentWriter::class)->applySplit($record, $shares);

                $warnings = app(ConsignmentWriter::class)->warnings($record->fresh());

                if ($warnings === []) {
                    Notification::make()->title('Freight split saved')->success()->send();

                    return;
                }

                foreach ($warnings as $warning) {
                    Notification::make()->title($warning)->warning()->persistent()->send();
                }
            });
    }

    /** @return array<int, mixed> */
    private function splitFields(Consignment $record): array
    {
        $suggested = app(ConsignmentWriter::class)->suggest($record);
        $fields = [];

        foreach ($record->deals as $deal) {
            $fields[] = TextInput::make('share_'.$deal->id)
                ->label($deal->number.' — '.$deal->customer?->name)
                ->numeric()
                ->required()
                ->live(onBlur: true)
                ->default(fn () => (float) ($suggested[$deal->id]?->amount ?? 0))
                ->suffix($record->freight_currency)
                ->helperText(fn () => $record->splitBasis() === 'cbm'
                    ? number_format($deal->estimatedCbm(), 3).' m³ of known goods'
                    : number_format($deal->estimatedWeightKg(), 2).' kg of known goods');
        }

        /*
         * A running total, because the split has to reconcile to the bill.
         * Showing the difference as you type is the difference between noticing
         * and discovering it in a report six weeks later.
         */
        $fields[] = TextInput::make('_total')
            ->label('Adds up to')
            ->disabled()
            ->dehydrated(false)
            ->suffix($record->freight_currency)
            ->helperText('The bill is '.number_format((float) $record->freight_amount, 2).' '.$record->freight_currency)
            ->afterStateHydrated(function (Set $set, Get $get) use ($record) {
                $set('_total', self::sumShares($get, $record));
            })
            ->live();

        return $fields;
    }

    private static function sumShares(Get $get, Consignment $record): string
    {
        $total = 0.0;

        foreach ($record->deals as $deal) {
            $total += (float) $get('share_'.$deal->id);
        }

        return number_format($total, 2, '.', '');
    }
}
