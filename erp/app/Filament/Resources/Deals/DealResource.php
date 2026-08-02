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
use App\Support\Money;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
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
                        // Live because the totals below are computed from it.
                        ->live(onBlur: true)
                        // Only asked for when it is actually needed.
                        ->visible(fn (Get $get) => $get('sell_currency') === 'IQD')
                        ->required(fn (Get $get) => $get('sell_currency') === 'IQD'),

                    TextInput::make('rmb_usd_rate')
                        ->label('RMB per 1 USD')
                        ->numeric()
                        ->step('0.000001')
                        ->default(fn () => Deal::lastRate('rmb_usd_rate'))
                        ->helperText('Needed when you buy in yuan.')
                        // Every yuan figure on the screen is valued through
                        // this, so the totals follow it as it is typed.
                        ->live(onBlur: true)
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
                        ->default(0)
                        ->live(onBlur: true),

                    Select::make('deal_commission_currency')
                        ->label('Currency')
                        ->options(['USD' => 'USD', 'IQD' => 'IQD'])
                        ->default('USD')
                        ->live(),
                ]),

            self::totalsSection(),

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
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => self::applyMarkup($get, $set))
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
     * What the deal comes to, kept in front of whoever is building it.
     *
     * Two currencies, because the deal lives in two: the goods are bought in
     * yuan and sold in whatever the customer pays. "Is there anything in this
     * one" cannot be answered in either currency alone, and working it out on a
     * phone calculator after the fact is how a deal gets quoted at a loss.
     *
     * The arithmetic is the same as Deal::costBase() and revenueBase(), which
     * are what the deal reports once saved — done here against the form state,
     * so the figures appear while the rows are still being typed.
     */
    private static function totalsSection(): Section
    {
        return Section::make('Totals')
            ->description('Worked out from the rows above as you type. Nothing here is entered by hand.')
            ->columns(3)
            ->schema([
                Placeholder::make('cost_summary')
                    ->label('Goods cost you')
                    ->visible(fn () => auth()->user()?->can('view_cost'))
                    ->content(function (Get $get): string {
                        $total = self::summarise($get);

                        if ($total['needs_rate']) {
                            return 'Set the RMB rate above — the yuan cannot be valued without it.';
                        }

                        $parts = [];

                        if ($total['cost_rmb'] > 0) {
                            $parts[] = self::money($total['cost_rmb'], 'CNY');
                        }

                        $parts[] = self::money($total['cost_usd'], 'USD');

                        return implode('  ·  ', $parts);
                    }),

                /*
                 * The figure the customer will see. It carries the commission,
                 * which the invoice bills as its own line but which the customer
                 * pays all the same — a total that left it out would be a total
                 * of something nobody is ever asked for.
                 */
                Placeholder::make('customer_summary')
                    ->label('Customer pays')
                    ->content(function (Get $get): string {
                        $total = self::summarise($get);

                        $shown = self::money($total['customer_total'], $total['sell_currency']);

                        return $total['sell_currency'] === 'USD'
                            ? $shown
                            : $shown.'  ·  '.self::money($total['revenue_usd'], 'USD');
                    }),

                Placeholder::make('profit_summary')
                    ->label('Profit')
                    ->visible(fn () => auth()->user()?->can('view_cost'))
                    ->content(function (Get $get): string {
                        $total = self::summarise($get);

                        if ($total['needs_rate']) {
                            return '—';
                        }

                        return self::money($total['profit_usd'], 'USD')
                            .'  ·  '.number_format($total['margin'], 1).'% margin';
                    }),
            ]);
    }

    /**
     * Add the deal up from what is currently on the screen.
     *
     * Everything meets in dollars, because that is the only currency both sides
     * of this business share: yuan out to the supplier, dinars or dollars in
     * from the customer.
     *
     * @return array{cost_rmb: float, cost_usd: float, customer_total: float, revenue_usd: float, profit_usd: float, margin: float, sell_currency: string, needs_rate: bool}
     */
    private static function summarise(Get $get): array
    {
        $deal = self::dealFromForm($get);
        $sellCurrency = $deal->sell_currency;

        $costRmb = 0.0;
        $costUsd = 0.0;
        $goods = 0.0;
        $needsRate = false;

        foreach ((array) $get('lines') as $line) {
            $quantity = (float) ($line['quantity'] ?? 0);
            $currency = $line['cost_currency'] ?: 'CNY';
            $cost = (float) ($line['unit_cost'] ?? 0) * $quantity;

            if ($currency === 'CNY') {
                $costRmb += $cost;
            }

            if ($cost > 0 && ! self::hasRatesFor($deal, [$currency])) {
                $needsRate = true;
            } else {
                $costUsd += $deal->toBase(Money::of($cost, $currency))->toFloat();
            }

            $goods += (float) ($line['unit_price'] ?? 0) * $quantity;
        }

        $goodsUsd = $deal->toBase(Money::of($goods, $sellCurrency))->toFloat();

        $commissionUsd = $deal->toBase(Money::of(
            (float) $get('deal_commission'),
            $get('deal_commission_currency') ?: $sellCurrency,
        ))->toFloat();

        $revenueUsd = $goodsUsd + $commissionUsd;

        // Back into what the customer is billed in, so the commission is part
        // of one figure rather than a number they are told about separately.
        $commissionInSellCurrency = $sellCurrency === 'USD'
            ? $commissionUsd
            : $commissionUsd * (float) $deal->rateFor($sellCurrency);

        return [
            'cost_rmb' => $costRmb,
            'cost_usd' => $costUsd,
            'customer_total' => $goods + $commissionInSellCurrency,
            'revenue_usd' => $revenueUsd,
            'profit_usd' => $revenueUsd - $costUsd,
            'margin' => $revenueUsd > 0 ? round(($revenueUsd - $costUsd) / $revenueUsd * 100, 1) : 0.0,
            'sell_currency' => $sellCurrency,
            'needs_rate' => $needsRate,
        ];
    }

    /** Dinars are never quoted with fractions; dollars and yuan always are. */
    private static function money(float $amount, string $currency): string
    {
        $formatted = number_format($amount, $currency === 'IQD' ? 0 : 2);

        return match ($currency) {
            'USD' => '$'.$formatted,
            'CNY' => '¥'.$formatted,
            default => $formatted.' '.$currency,
        };
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

        if ($cost <= 0) {
            return;
        }

        $costCurrency = $get('cost_currency') ?: 'CNY';
        $deal = self::dealFromForm($get, parent: true);

        /*
         * A missing rate would be read as a rate of one, and yuan would be
         * priced as though they were dollars — which is the whole of the bug
         * this guards. Leave the price alone instead: an empty box gets
         * noticed, a confident wrong number does not.
         */
        if (! self::hasRatesFor($deal, [$costCurrency, $deal->sell_currency])) {
            return;
        }

        /*
         * Priced by the same code that prices a saved line.
         *
         * The screen used to do its own arithmetic — cost times markup, with no
         * regard for the currency — so ¥50 plus 75% became a price of 87.50 to
         * a customer paying dollars: nearly seven times the worth of the goods,
         * and a deal that looks extraordinarily profitable right up until it is
         * quoted. Both prices now come from one place, so they cannot disagree
         * again. The models are unsaved carriers for the numbers on screen;
         * nothing here touches the database.
         */
        $price = (new DealLine([
            'unit_cost' => $cost,
            'cost_currency' => $costCurrency,
            'markup_percent' => (float) $get('markup_percent'),
        ]))->priceFromMarkup($deal);

        if ($price !== null) {
            $set('unit_price', round($price->toFloat(), 4));
        }
    }

    /**
     * The deal as it currently stands on screen, unsaved.
     *
     * Only ever used to carry the currency and rates into the model's own
     * conversions, so that the direction a rate is applied in is decided in one
     * place — Deal::toBase() — rather than re-derived by every screen that
     * needs a figure in dollars.
     */
    private static function dealFromForm(Get $get, bool $parent = false): Deal
    {
        $at = fn (string $field): mixed => $get(($parent ? '../../' : '').$field);

        return new Deal([
            'sell_currency' => $at('sell_currency') ?: 'USD',
            'rmb_usd_rate' => (float) $at('rmb_usd_rate'),
            'iqd_usd_rate' => (float) $at('iqd_usd_rate'),
        ]);
    }

    /**
     * Whether the deal carries a rate for every currency named.
     *
     * Deal::rateFor() answers 1 for a rate it does not have, which is the right
     * answer for dollars and a dangerous one for anything else.
     *
     * @param  array<int, string|null>  $currencies
     */
    private static function hasRatesFor(Deal $deal, array $currencies): bool
    {
        foreach (array_filter($currencies) as $currency) {
            if (strtoupper($currency) !== 'USD' && (float) $deal->rateFor($currency) <= 1) {
                return false;
            }
        }

        return true;
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
