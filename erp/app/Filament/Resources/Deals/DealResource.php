<?php

namespace App\Filament\Resources\Deals;

use App\Filament\Resources\Deals\Pages\CreateDeal;
use App\Filament\Resources\Deals\Pages\EditDeal;
use App\Filament\Resources\Deals\Pages\ListDeals;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\DealLine;
use App\Models\Product;
use App\Models\Supplier;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The main working screen.
 *
 * A line carries both sides — what it costs and what it sells for — because the
 * whole point of this screen is that nothing is entered twice. The supplier's
 * purchase document is these lines grouped by supplier; the customer's invoice
 * is these lines with the cost columns removed.
 *
 * Every cost field is gated on `view_cost`, so the assistant sees the same
 * screen with the money half of each line missing, rather than a second screen
 * that could drift out of step with this one.
 */
class DealResource extends Resource
{
    protected static ?string $model = Deal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static string|UnitEnum|null $navigationGroup = 'Trading';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Customer & dates')
                ->columns(4)
                ->schema([
                    Select::make('customer_id')
                        ->label('Customer')
                        ->relationship('customer', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        /*
                         * Choosing the customer settles the selling currency.
                         * Their document language and customer type travel with
                         * them too — both are set once on the customer so that
                         * neither is ever a question at deal time.
                         */
                        ->afterStateUpdated(function (?string $state, Set $set) {
                            $currency = Customer::find($state)?->default_currency;

                            if ($currency) {
                                $set('sell_currency', $currency);
                            }
                        })
                        ->columnSpan(2),

                    DatePicker::make('deal_date')
                        ->label('Date')
                        ->default(now())
                        ->required(),

                    DatePicker::make('expected_delivery')
                        ->label('Expected delivery'),
                ]),

            Section::make('Currency & rates')
                ->description(
                    'Rates are stamped onto this deal and never change again. Editing '
                    .'them later would rewrite the profit on work already finished.'
                )
                ->columns(3)
                ->schema([
                    Select::make('sell_currency')
                        ->label('Customer pays in')
                        ->options(['IQD' => 'IQD — Iraqi Dinar', 'USD' => 'USD — US Dollar'])
                        ->default('IQD')
                        ->required()
                        ->live(),

                    TextInput::make('iqd_usd_rate')
                        ->label('IQD per 1 USD')
                        ->numeric()
                        ->step('0.000001')
                        ->default(fn () => Deal::lastRate('iqd_usd_rate'))
                        ->helperText('Pre-filled with the last rate you used.')
                        // Only asked for when it is actually needed.
                        ->visible(fn (Get $get) => $get('sell_currency') === 'IQD')
                        ->required(fn (Get $get) => $get('sell_currency') === 'IQD'),

                    TextInput::make('rmb_usd_rate')
                        ->label('RMB per 1 USD')
                        ->numeric()
                        ->step('0.000001')
                        ->default(fn () => Deal::lastRate('rmb_usd_rate'))
                        ->helperText('Needed when you buy in yuan.')
                        ->visible(fn () => auth()->user()?->can('view_cost')),
                ]),

            Section::make('What the customer wants')
                ->description(
                    'One row per item. Pick a product to fill both prices, or just type '
                    .'what they asked for.'
                )
                ->schema([
                    Repeater::make('lines')
                        ->relationship()
                        ->hiddenLabel()
                        ->reorderableWithButtons()
                        ->orderColumn('display_order')
                        ->addActionLabel('Add an item')
                        ->itemLabel(fn (array $state): ?string => $state['description'] ?? null)
                        ->columns(12)
                        ->schema(self::lineSchema())
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),

            Section::make('Extra commission')
                ->description(
                    'A lump added to the whole order for your effort or arranging. '
                    .'Because it belongs to the deal rather than any one product, '
                    .'per-product profit is reported as approximate whenever it is set.'
                )
                ->columns(2)
                ->visible(fn () => auth()->user()?->can('view_cost'))
                ->collapsed()
                ->schema([
                    TextInput::make('deal_commission')
                        ->label('Amount')
                        ->numeric()
                        ->default(0),

                    Select::make('deal_commission_currency')
                        ->label('Currency')
                        ->options(['USD' => 'USD', 'IQD' => 'IQD'])
                        ->default('USD'),
                ]),

            Section::make('Notes')
                ->columns(2)
                ->collapsed()
                ->schema([
                    Textarea::make('customer_notes')
                        ->label('Notes for the customer')
                        ->helperText('Appears on the quotation and invoice.')
                        ->rows(3),

                    Textarea::make('internal_notes')
                        ->label('Private notes')
                        ->helperText('Never printed.')
                        ->rows(3),
                ]),
        ]);
    }

    /**
     * One line, in two deliberate rows.
     *
     * The first row is what the customer asked for. The second is money, laid
     * out left to right in the order the decision is actually made: where you
     * buy it, what it costs, how you price it, what they pay. Letting the grid
     * wrap on its own put "Sell" on a line of its own, away from the cost it is
     * derived from — the one adjacency that matters on this screen.
     *
     * @return array<int, mixed>
     */
    private static function lineSchema(): array
    {
        $canSeeCost = fn () => auth()->user()?->can('view_cost');

        return [
            // ---- row one: what they asked for -------------------------------
            TextInput::make('description')
                ->label('Item')
                ->required()
                ->columnSpan(6)
                ->datalist(fn () => Product::query()->active()->limit(50)->pluck('name')->all()),

            TextInput::make('quantity')
                ->numeric()
                ->default(1)
                ->required()
                ->live(onBlur: true)
                ->columnSpan(2),

            TextInput::make('unit')->default('pcs')->columnSpan(2),

            Toggle::make('contains_battery')
                ->label('Battery')
                ->inline(false)
                ->helperText('Restricts air shipping')
                ->columnSpan(2),

            // ---- row two: six even columns, cost then price ------------------
            Select::make('supplier_id')
                ->label('Buy from')
                ->options(fn () => Supplier::query()->active()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->placeholder('Not decided')
                ->visible($canSeeCost)
                ->columnSpan(2),

            TextInput::make('unit_cost')
                ->label('Cost each')
                ->numeric()
                ->default(0)
                ->live(onBlur: true)
                ->visible($canSeeCost)
                ->columnSpan(2)
                // Re-derive the selling price whenever the cost moves, but only
                // on markup lines — a typed price must never be overwritten.
                ->afterStateUpdated(fn (Get $get, Set $set) => self::applyMarkup($get, $set)),

            Select::make('cost_currency')
                ->label('In')
                ->options(['CNY' => 'RMB', 'USD' => 'USD'])
                ->default('CNY')
                ->visible($canSeeCost)
                ->columnSpan(2),

            Select::make('pricing_method')
                ->label('Price by')
                ->options(DealLine::PRICING_METHODS)
                ->default('markup')
                ->live()
                ->visible($canSeeCost)
                ->columnSpan(2)
                ->afterStateUpdated(fn (Get $get, Set $set) => self::applyMarkup($get, $set)),

            TextInput::make('markup_percent')
                ->label('Markup')
                ->numeric()
                ->default(25)
                ->suffix('%')
                ->live(onBlur: true)
                ->visible(fn (Get $get) => auth()->user()?->can('view_cost')
                    && $get('pricing_method') === 'markup')
                ->columnSpan(2)
                ->afterStateUpdated(fn (Get $get, Set $set) => self::applyMarkup($get, $set)),

            TextInput::make('unit_price')
                ->label('Sell each')
                ->numeric()
                ->required()
                ->default(0)
                ->live(onBlur: true)
                // The assistant sees only this half of the row, so it takes the
                // width the cost fields would have used rather than sitting in
                // a narrow column beside empty space.
                ->columnSpan(fn () => auth()->user()?->can('view_cost') ? 2 : 6),

            Textarea::make('specification')
                ->label('Specification')
                ->rows(2)
                ->columnSpan(8),

            /*
             * The photo is part of the agreement, not decoration.
             *
             * Customers approve *models* — the supplier sends pictures, the
             * customer picks one, and that choice is what gets argued about
             * when the goods land. The quotation freezes a copy of this path so
             * replacing the picture later cannot change what was approved.
             */
            FileUpload::make('photo_path')
                ->label('Photo')
                ->image()
                ->imageEditor()
                ->directory('deal-photos')
                ->visibility('private')
                ->helperText('Shown on the quotation.')
                ->columnSpan(4),
        ];
    }

    /**
     * Fill the selling price from cost plus markup.
     *
     * Deliberately does nothing unless the line is set to markup pricing. On a
     * mixed deal — which is how this business actually prices — silently
     * recalculating a hand-typed price would undo a decision made on purpose,
     * and it would only be noticed on the invoice.
     */
    private static function applyMarkup(Get $get, Set $set): void
    {
        if ($get('pricing_method') !== 'markup') {
            return;
        }

        $cost = (float) $get('unit_cost');
        $markup = (float) $get('markup_percent');

        if ($cost <= 0) {
            return;
        }

        $set('unit_price', round($cost * (1 + $markup / 100), 4));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'customer', 'lines', 'purchases.costs', 'expenses', 'consignments',
            ]))
            ->columns([
                TextColumn::make('number')
                    ->label('Deal')
                    ->weight('medium')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->description(fn (Deal $record) => $record->deal_date?->format('d M Y'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Deal::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'draft' => 'gray',
                        'quoted' => 'info',
                        'approved', 'purchasing', 'shipping' => 'warning',
                        'arrived', 'delivered', 'closed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('lines_count')
                    ->label('Items')
                    ->state(fn (Deal $record) => $record->lines->count())
                    ->alignEnd(),

                TextColumn::make('revenue')
                    ->label('Customer total')
                    ->state(fn (Deal $record) => $record->revenueBase()->toFloat())
                    ->money('USD')
                    ->alignEnd(),

                TextColumn::make('profit')
                    ->label('Profit')
                    ->state(fn (Deal $record) => $record->profitBase()->toFloat())
                    ->money('USD')
                    ->alignEnd()
                    ->color(fn (Deal $record) => $record->profitBase()->isNegative() ? 'danger' : 'success')
                    ->description(fn (Deal $record) => $record->marginPercent().'%')
                    // The commercial boundary, in one line.
                    ->visible(fn () => auth()->user()?->can('view_cost')),
            ])
            ->defaultSort('deal_date', 'desc')
            ->filters([
                SelectFilter::make('status')->options(Deal::STATUSES)->multiple(),

                SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeals::route('/'),
            'create' => CreateDeal::route('/create'),
            'edit' => EditDeal::route('/{record}/edit'),
        ];
    }

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['number', 'customer.name'];
    }
}
