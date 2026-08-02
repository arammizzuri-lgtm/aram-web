<?php

namespace App\Filament\Resources\Consignments;

use App\Filament\Resources\Consignments\Pages\ManageConsignments;
use App\Models\CollectionPoint;
use App\Models\Consignment;
use App\Models\Deal;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Where the forwarder's tracking numbers get recorded.
 *
 * This screen copies what their app already tells you — number, boxes, weight,
 * CBM, status — and ties it back to the deals it belongs to. Weight and CBM are
 * not decoration: they are what makes a shared freight bill divide honestly.
 */
class ConsignmentResource extends Resource
{
    protected static ?string $model = Consignment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Trading';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'tracking_number';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('From the forwarder')
                ->description('Copy these straight from their tracking app.')
                ->columns(3)
                ->schema([
                    TextInput::make('tracking_number')
                        ->label('Tracking no.')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(64),

                    Select::make('mode')
                        ->label('Shipment type')
                        ->options(Consignment::MODES)
                        ->default('sea')
                        ->required()
                        ->live()
                        ->helperText(fn (?string $state) => $state === 'air_no_battery'
                            ? 'Cannot carry anything with a battery.'
                            : null),

                    Select::make('status')
                        ->options(Consignment::STATUSES)
                        ->default('awaiting')
                        ->required(),

                    TextInput::make('boxes')->label('Total boxes')->numeric(),

                    TextInput::make('gross_weight_kg')
                        ->label('Gross weight')
                        ->numeric()
                        ->suffix('kg')
                        // What air freight is charged for, and therefore what an
                        // air bill shared between customers is divided by.
                        ->helperText('Divides a shared air bill.'),

                    TextInput::make('cbm')
                        ->label('CBM')
                        ->numeric()
                        ->step('0.0001')
                        ->suffix('m³')
                        ->helperText('Divides a shared sea bill.'),

                    Select::make('collection_point_id')
                        ->label('Shipped from')
                        ->options(fn () => CollectionPoint::query()->active()->orderBy('city')
                            ->get()
                            ->mapWithKeys(fn (CollectionPoint $p) => [$p->id => "{$p->city} — {$p->name}"]))
                        ->searchable()
                        ->placeholder('Not recorded'),

                    DatePicker::make('shipped_at')->label('Shipped'),
                    DatePicker::make('arrived_at')->label('Arrived'),
                ]),

            Section::make('Whose goods are these?')
                ->description(
                    'Attach every deal in this consignment. Attach more than one and '
                    .'the freight bill gets divided between them.'
                )
                ->schema([
                    Select::make('deals')
                        ->label('Deals')
                        /*
                         * The customer is loaded with the deals, not fetched per
                         * option.
                         *
                         * Filament works out the eager loads for a *column* by
                         * itself, but it cannot see inside a label closure — so
                         * naming the customer here made this screen fetch one
                         * customer per deal on the list, and a hard 500 the
                         * moment there was more than one deal to label. (Laravel
                         * only arms preventLazyLoading on models that arrive in
                         * company: `Builder::hydrate()` sets it when the query
                         * returned more than one row, which is why this worked
                         * for exactly as long as there was one deal.)
                         *
                         * The third argument reaches all three paths that build
                         * labels — the preload, the search, and resolving what
                         * is already selected.
                         */
                        ->relationship(
                            'deals',
                            'number',
                            fn (Builder $query) => $query->with('customer'),
                        )
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->getOptionLabelFromRecordUsing(
                            fn (Deal $record) => $record->number.' — '.$record->customer?->name
                        )
                        ->helperText('One tracking number can carry several customers\' goods.'),
                ]),

            Section::make('The bill')
                ->description(
                    'Typed from the invoice when it arrives — your forwarder does not '
                    .'quote a rate in advance, so there is nothing to calculate.'
                )
                ->columns(3)
                ->visible(fn () => auth()->user()?->can('view_cost'))
                ->schema([
                    TextInput::make('freight_amount')->label('Freight')->numeric(),

                    Select::make('freight_currency')
                        ->label('Currency')
                        ->options(['USD' => 'USD', 'CNY' => 'RMB', 'IQD' => 'IQD'])
                        ->default('USD'),

                    Textarea::make('notes')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['deals.customer', 'collectionPoint']))
            ->columns([
                TextColumn::make('tracking_number')
                    ->label('Tracking no.')
                    ->weight('medium')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('mode')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Consignment::MODES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'sea' => 'info',
                        'air_battery' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('deals.customer.name')
                    ->label('For')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Not attached'),

                TextColumn::make('boxes')->label('Boxes')->alignEnd()->placeholder('—'),

                TextColumn::make('gross_weight_kg')
                    ->label('Weight')
                    ->alignEnd()
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state) => $state ? number_format((float) $state, 2).' kg' : null),

                TextColumn::make('cbm')
                    ->label('CBM')
                    ->alignEnd()
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state) => $state ? number_format((float) $state, 3) : null),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Consignment::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'awaiting' => 'gray',
                        'in_transfer' => 'info',
                        'arrived' => 'warning',
                        'delivered' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('freight_amount')
                    ->label('Freight')
                    ->alignEnd()
                    ->placeholder('Not billed')
                    ->formatStateUsing(fn ($state, Consignment $record) => $state
                        ? number_format((float) $state, 2).' '.$record->freight_currency
                        : null)
                    ->visible(fn () => auth()->user()?->can('view_cost')),
            ])
            ->defaultSort('shipped_at', 'desc')
            ->filters([
                SelectFilter::make('mode')->options(Consignment::MODES),
                SelectFilter::make('status')->options(Consignment::STATUSES)->multiple(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageConsignments::route('/'),
        ];
    }

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['tracking_number'];
    }
}
