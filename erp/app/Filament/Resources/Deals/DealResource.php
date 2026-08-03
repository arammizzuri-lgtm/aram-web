<?php

namespace App\Filament\Resources\Deals;

use App\Filament\Actions\RecordDeletion;
use App\Filament\Concerns\KeepsDeletedRecords;
use App\Filament\Resources\Deals\Pages\CreateDeal;
use App\Filament\Resources\Deals\Pages\EditDeal;
use App\Filament\Resources\Deals\Pages\ListDeals;
use App\Filament\Resources\Deals\RelationManagers\ConsignmentsRelationManager;
use App\Filament\Resources\Deals\RelationManagers\InvoicesRelationManager;
use App\Filament\Resources\Deals\RelationManagers\PurchasesRelationManager;
use App\Filament\Resources\Deals\RelationManagers\QuotationsRelationManager;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\DealLine;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Deals\CatalogueLookup;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
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
    use KeepsDeletedRecords;

    protected static ?string $model = Deal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static string|UnitEnum|null $navigationGroup = 'Trading';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Customer & dates')
                ->columns(5)
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

                    /*
                     * Where the deal has got to.
                     *
                     * It moves on its own where the system can be sure — naming
                     * a supplier means purchasing, a consignment in transfer
                     * means shipping — but it has to be settable, because the
                     * system does not see the phone call telling you the goods
                     * were handed over, and a deal that can never be closed is
                     * a deal that stays on your list forever.
                     */
                    Select::make('status')
                        ->label('Stage')
                        ->options(Deal::STATUSES)
                        ->default('draft')
                        ->required()
                        ->native(false)
                        ->helperText('Moves by itself as you buy and ship.'),

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
     * One line, in three deliberate rows.
     *
     * The first row reaches into the price lists. The second is what the
     * customer asked for. The third is money, laid out left to right in the
     * order the decision is actually made: where you buy it, what it costs, how
     * you price it, what they pay. Letting the grid wrap on its own put "Sell"
     * on a line of its own, away from the cost it is derived from — the one
     * adjacency that matters on this screen.
     *
     * @return array<int, mixed>
     */
    private static function lineSchema(): array
    {
        $canSeeCost = fn () => auth()->user()?->can('view_cost');

        return [
            /*
             * ---- row one: what it is, from the lists you already keep -------
             *
             * The price lists are the most worked-on part of this system and
             * the deal screen could not reach them at all: every line was typed
             * from memory, and "From price list" was a pricing method that did
             * nothing whatever. So the cost, the supplier, the Chinese name,
             * the battery flag and the weight — all of it already recorded —
             * had to be looked up on another screen and copied by hand, which
             * is the exact re-entry this design exists to abolish.
             *
             * One box searches all four lists. Typing the item by hand instead
             * still works and still costs nothing: a one-off stays a one-off.
             */
            Select::make('catalogue_key')
                ->label('Find it in your price lists')
                ->placeholder('Search crystals, textile, packaging, furniture, products — or just type it below')
                ->searchable()
                ->dehydrated(false)
                ->getSearchResultsUsing(fn (?string $search) => app(CatalogueLookup::class)->search($search))
                ->getOptionLabelUsing(fn (?string $value) => app(CatalogueLookup::class)->label($value))
                // An existing line shows what it was picked from, rather than an
                // empty search box beside a description that plainly came from
                // somewhere.
                ->afterStateHydrated(fn (Get $get, Set $set) => $set(
                    'catalogue_key',
                    app(CatalogueLookup::class)->keyForIds(
                        (int) $get('product_id') ?: null,
                        (int) $get('catalogue_item_id') ?: null,
                        (int) $get('crystal_product_id') ?: null,
                        (int) $get('crystal_size_id') ?: null,
                    ),
                ))
                ->afterStateUpdated(fn (?string $state, Get $get, Set $set) => self::applyCatalogue($state, $get, $set))
                ->columnSpanFull(),

            /*
             * What the line points at in the catalogue.
             *
             * Kept because they are what makes a line more than its
             * description: the weight and volume behind the freight split live
             * on the product, and a report asking which product earned most
             * has nothing to group by without them. Hidden because they are the
             * consequence of the search above, never a decision of their own.
             */
            Hidden::make('product_id'),
            Hidden::make('product_size_id'),
            Hidden::make('catalogue_item_id'),
            Hidden::make('crystal_product_id'),
            Hidden::make('crystal_size_id'),

            // The customer's language and the supplier's, carried from the
            // catalogue so the quotation and the purchase order can each be
            // read by the person receiving it.
            Hidden::make('description_ku'),
            Hidden::make('description_zh'),

            // ---- row two: what they asked for -------------------------------
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
                // Quantity is not only a multiplier: both the cost lists and the
                // selling lists carry breaks, so 500 pieces can be a different
                // price per piece from 50.
                ->afterStateUpdated(fn (Get $get, Set $set) => self::applyPricing($get, $set))
                ->columnSpan(2),

            TextInput::make('unit')->default('pcs')->columnSpan(2),

            Toggle::make('contains_battery')
                ->label('Battery')
                ->inline(false)
                ->helperText('Restricts air shipping')
                ->columnSpan(2),

            // ---- row three: six even columns, cost then price ----------------
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
                ->afterStateUpdated(fn (Get $get, Set $set) => self::applyPricing($get, $set)),

            Select::make('cost_currency')
                ->label('In')
                ->options(['CNY' => 'RMB', 'USD' => 'USD'])
                ->default('CNY')
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => self::applyPricing($get, $set))
                ->visible($canSeeCost)
                ->columnSpan(2),

            Select::make('pricing_method')
                ->label('Price by')
                ->options(DealLine::PRICING_METHODS)
                ->default('markup')
                ->live()
                ->visible($canSeeCost)
                ->columnSpan(2)
                ->afterStateUpdated(fn (Get $get, Set $set) => self::applyPricing($get, $set)),

            TextInput::make('markup_percent')
                ->label('Markup')
                ->numeric()
                ->default(25)
                ->suffix('%')
                ->live(onBlur: true)
                ->visible(fn (Get $get) => auth()->user()?->can('view_cost')
                    && $get('pricing_method') === 'markup')
                ->columnSpan(2)
                ->afterStateUpdated(fn (Get $get, Set $set) => self::applyPricing($get, $set)),

            /*
             * The selling price in the currency the goods were bought in.
             *
             * Never stored — the record keeps the price in the currency the
             * customer is billed in, which is the only one an invoice can be
             * written from. This is the same figure said in yuan, because that
             * is the currency the decision is actually made in: a supplier
             * quotes ¥50, and "¥50 plus half again" is a thought worth being
             * able to have on the screen rather than in your head.
             *
             * On a markup or price-list line it is filled in. On a typed price
             * it is one of the two boxes the price can be typed into, and the
             * other follows it.
             */
            TextInput::make('sell_each_in_cost_currency')
                ->label('Sell each for')
                ->numeric()
                ->dehydrated(false)
                ->live(onBlur: true)
                ->readOnly(fn (Get $get) => $get('pricing_method') !== 'manual')
                ->suffix(fn (Get $get) => $get('cost_currency') === 'USD' ? 'USD' : 'RMB')
                ->visible($canSeeCost)
                ->columnSpan(2)
                ->afterStateHydrated(fn (Get $get, Set $set) => self::showSellInCostCurrency($get, $set))
                ->afterStateUpdated(fn (Get $get, Set $set) => self::priceFromCostCurrency($get, $set)),

            /*
             * The price the customer is actually billed.
             *
             * Typed or derived depending on the method, and on a typed line it
             * is genuinely typed. It had been made read-only for anyone who can
             * see cost, which left the owner able to say "¥50 plus half again"
             * but unable to say "thirty thousand dinars" — and a dinar figure
             * is what a customer negotiates, agrees to and remembers. Both
             * boxes now write to each other, so the price can be settled in
             * whichever currency it was settled in.
             */
            TextInput::make('unit_price')
                ->label(fn () => auth()->user()?->can('view_cost') ? 'Customer pays each' : 'Sell each')
                ->numeric()
                ->required()
                ->default(0)
                ->live(onBlur: true)
                ->suffix(fn (Get $get) => $get('../../sell_currency') ?: 'USD')
                // Locked only where it is a consequence: a markup line derives
                // it from the cost, a price-list line reads it off the list.
                ->readOnly(fn (Get $get) => auth()->user()?->can('view_cost')
                    && $get('pricing_method') !== 'manual')
                ->afterStateUpdated(fn (Get $get, Set $set) => self::showSellInCostCurrency($get, $set))
                ->columnSpan(fn () => auth()->user()?->can('view_cost') ? 2 : 6),

            /*
             * What the line comes to, and what is left of it.
             *
             * Both in dollars, and both read-only: they are consequences of the
             * boxes above, and a figure you can type over is a figure that can
             * be made to say anything.
             */
            Placeholder::make('line_total')
                ->label('Total of that item')
                ->content(fn (Get $get) => self::money(self::lineFigures($get)['total'], 'USD'))
                ->columnSpan(2),

            Placeholder::make('line_profit')
                ->label('Profit')
                ->visible($canSeeCost)
                ->content(function (Get $get) {
                    $figures = self::lineFigures($get);

                    return self::money($figures['profit'], 'USD')
                        .($figures['total'] > 0
                            ? '  ·  '.number_format($figures['profit'] / $figures['total'] * 100, 1).'%'
                            : '');
                })
                ->columnSpan(2),

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

                        if ($total['needs_cost_rate']) {
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

                        if ($total['sell_currency'] === 'USD') {
                            return $shown;
                        }

                        /*
                         * Without the rate there is no dollar figure to show,
                         * and the one that would have been shown is the dinar
                         * total with a dollar sign in front of it — a deal of
                         * 3,190,000 dinars reading as three million dollars.
                         */
                        return $total['needs_sell_rate']
                            ? $shown.'  ·  set the '.$total['sell_currency'].' rate above for the dollar figure'
                            : $shown.'  ·  '.self::money($total['revenue_usd'], 'USD');
                    }),

                Placeholder::make('profit_summary')
                    ->label('Profit')
                    ->visible(fn () => auth()->user()?->can('view_cost'))
                    ->content(function (Get $get): string {
                        $total = self::summarise($get);

                        // Either half missing makes the answer meaningless, and
                        // a meaningless profit is worse than none: it is the one
                        // number on this screen a decision is made on.
                        if ($total['needs_cost_rate'] || $total['needs_sell_rate']) {
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
     * @return array{cost_rmb: float, cost_usd: float, customer_total: float, revenue_usd: float, profit_usd: float, margin: float, sell_currency: string, needs_cost_rate: bool, needs_sell_rate: bool}
     */
    private static function summarise(Get $get): array
    {
        $deal = self::dealFromForm($get);
        $sellCurrency = $deal->sell_currency;

        $costRmb = 0.0;
        $costUsd = 0.0;
        $goods = 0.0;
        $needsCostRate = false;

        foreach ((array) $get('lines') as $line) {
            $quantity = (float) ($line['quantity'] ?? 0);
            $currency = $line['cost_currency'] ?: 'CNY';
            $cost = (float) ($line['unit_cost'] ?? 0) * $quantity;

            if ($currency === 'CNY') {
                $costRmb += $cost;
            }

            if ($cost > 0 && ! self::hasRatesFor($deal, [$currency])) {
                $needsCostRate = true;
            } else {
                $costUsd += $deal->toBase(Money::of($cost, $currency))->toFloat();
            }

            $goods += (float) ($line['unit_price'] ?? 0) * $quantity;
        }

        /*
         * The selling side needs a rate just as much as the cost side.
         *
         * Only the cost currencies were checked, so a dinar deal typed before
         * the rate was filled in valued dinars as dollars — Deal::rateFor()
         * answers 1 for a rate it does not hold — and reported a profit around
         * fifteen hundred times the truth, in the one box on this screen that
         * decides whether a deal is worth doing.
         */
        $needsSellRate = $goods > 0 && ! self::hasRatesFor($deal, [$sellCurrency]);

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
            'needs_cost_rate' => $needsCostRate,
            'needs_sell_rate' => $needsSellRate,
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
     * Price the line the way this line is priced.
     *
     * All three methods are used and mixed inside one deal, which is why the
     * method is per line. Everything that can change a price — the cost, the
     * currency, the quantity, the markup, the catalogue pick — comes through
     * here, so there is one answer to "what happens now" rather than one per
     * field.
     *
     * A typed price is never touched by any of it. On a mixed deal, silently
     * recalculating a price somebody decided on would undo a decision made on
     * purpose, and it would only be noticed on the invoice.
     */
    private static function applyPricing(Get $get, Set $set): void
    {
        match ($get('pricing_method')) {
            'markup' => self::applyMarkup($get, $set),
            'list' => self::applyListPrice($get, $set),
            default => null,
        };
    }

    /**
     * Take the selling price off the price list, for the customer's own type.
     *
     * "From price list" had been a pricing method that did nothing at all: the
     * option was in the menu, the selling prices were in the database, and
     * choosing it changed no number on the screen. Which meant the one method
     * that needs no arithmetic from you was the one that did not work.
     *
     * Priced for the customer's type — wholesale pays what wholesale pays —
     * and at the quantity on the line, because the lists carry breaks.
     */
    private static function applyListPrice(Get $get, Set $set): void
    {
        $found = app(CatalogueLookup::class)->resolve(
            $get('catalogue_key'),
            (float) $get('quantity') ?: 1,
            self::customerTypeId($get),
        );

        if ($found === null) {
            Notification::make()
                ->title('Search the price list above first')
                ->body('A list price needs to know which listed item this is.')
                ->warning()
                ->send();

            return;
        }

        if ($found['list_price'] === null) {
            Notification::make()
                ->title('No selling price on the list for this one')
                ->body('Set one on the price list, or price this line by markup.')
                ->warning()
                ->send();

            return;
        }

        $deal = self::dealFromForm($get, parent: true);
        $listCurrency = $found['list_price_currency'];

        // A missing rate would be read as a rate of one — see applyMarkup.
        if (! self::hasRatesFor($deal, [$listCurrency, $deal->sell_currency])) {
            return;
        }

        $price = $deal->toSellCurrency(Money::of($found['list_price'], $listCurrency));

        $set('unit_price', round($price->toFloat(), 4));

        self::showSellInCostCurrency($get, $set);
    }

    /**
     * Fill a line from something already in your price lists.
     *
     * Cost and sell together, which is the whole promise: one search fills the
     * supplier's side and the customer's side at once. Everything it writes is
     * still editable — a list is where a line starts, not what it is stuck as.
     */
    private static function applyCatalogue(?string $key, Get $get, Set $set): void
    {
        $lookup = app(CatalogueLookup::class);

        $found = $lookup->resolve(
            $key,
            (float) $get('quantity') ?: 1,
            self::customerTypeId($get),
        );

        /*
         * Clearing the box makes the line a one-off again.
         *
         * The description and the prices are left exactly as they are: they may
         * well be what you want, and deleting somebody's typing because they
         * emptied a search box is the sort of help nobody asks for twice.
         */
        if ($found === null) {
            foreach ([
                'product_id', 'product_size_id', 'catalogue_item_id',
                'crystal_product_id', 'crystal_size_id',
            ] as $id) {
                $set($id, null);
            }

            return;
        }

        foreach ([
            'description', 'description_ku', 'description_zh', 'unit',
            'product_id', 'product_size_id', 'catalogue_item_id',
            'crystal_product_id', 'crystal_size_id',
        ] as $field) {
            $set($field, $found[$field]);
        }

        $set('contains_battery', $found['contains_battery']);

        // Only where the list actually knows. An item nobody has priced yet
        // should leave the cost box alone rather than zero it.
        if ($found['unit_cost'] !== null) {
            $set('unit_cost', $found['unit_cost']);
            $set('cost_currency', $found['cost_currency']);
        }

        if ($found['supplier_id'] !== null) {
            $set('supplier_id', $found['supplier_id']);
        }

        /*
         * Nothing carries a selling price of its own any more, so "from price
         * list" has nothing to read and would leave the line at zero. Marking
         * the cost up is the answer the business gave: a suggested number, on
         * the screen, that can be overwritten before the customer sees it.
         *
         * Only switched when the pick genuinely has no list price — the older
         * entries still have theirs, and a method someone chose by hand is not
         * ours to override.
         */
        if ($found['list_price'] === null
            && $found['unit_cost'] !== null
            && $get('pricing_method') === 'list'
        ) {
            $set('pricing_method', 'markup');
        }

        self::applyPricing($get, $set);
    }

    /**
     * Which price list applies to this customer.
     *
     * Set once on the customer, so it is never a question at deal time — the
     * whole reason selling prices are keyed on the type rather than typed per
     * deal.
     */
    private static function customerTypeId(Get $get): ?int
    {
        $customerId = $get('../../customer_id');

        return $customerId
            ? Customer::find($customerId)?->customer_type_id
            : null;
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
        $markup = (float) $get('markup_percent');

        // The same figure said twice: in the currency it was bought in, which
        // is where the decision is made, and in the one it is billed in.
        $set('sell_each_in_cost_currency', round($cost * (1 + $markup / 100), 4));

        $price = (new DealLine([
            'unit_cost' => $cost,
            'cost_currency' => $costCurrency,
            'markup_percent' => $markup,
        ]))->priceFromMarkup($deal);

        if ($price !== null) {
            $set('unit_price', round($price->toFloat(), 4));
        }
    }

    /**
     * A price typed in the currency the goods were bought in, converted into
     * the one the customer is billed in.
     *
     * Only for lines priced by hand. On a markup line the yuan figure is a
     * consequence of the cost and the margin, and on a price-list line it is a
     * consequence of the list — typing over either would put the two out of
     * step with each other.
     */
    private static function priceFromCostCurrency(Get $get, Set $set): void
    {
        if ($get('pricing_method') !== 'manual') {
            return;
        }

        $deal = self::dealFromForm($get, parent: true);
        $costCurrency = $get('cost_currency') ?: 'CNY';

        if (! self::hasRatesFor($deal, [$costCurrency, $deal->sell_currency])) {
            return;
        }

        $sell = Money::of((float) $get('sell_each_in_cost_currency'), $costCurrency);

        $set('unit_price', round($deal->toSellCurrency($sell)->toFloat(), 4));
    }

    /**
     * The reverse, for a line being opened rather than written.
     *
     * Only the billed price is stored, so the yuan box starts empty on an
     * existing line and has to be worked back out of it.
     */
    private static function showSellInCostCurrency(Get $get, Set $set): void
    {
        $deal = self::dealFromForm($get, parent: true);
        $costCurrency = $get('cost_currency') ?: 'CNY';
        $price = (float) $get('unit_price');

        if ($price <= 0 || ! self::hasRatesFor($deal, [$costCurrency, $deal->sell_currency])) {
            return;
        }

        $base = $deal->toBase(Money::of($price, $deal->sell_currency));

        $inCostCurrency = $costCurrency === 'USD'
            ? $base
            : $base->times($deal->rateFor($costCurrency));

        $set('sell_each_in_cost_currency', round($inCostCurrency->toFloat(), 4));
    }

    /**
     * What one line comes to, in dollars.
     *
     * Both figures are in dollars even when the customer is billed in dinars,
     * because profit is only meaningful in the currency the business measures
     * itself in — and a margin shown in dinars against a cost in yuan is two
     * numbers that cannot be compared.
     *
     * @return array{total: float, profit: float}
     */
    private static function lineFigures(Get $get): array
    {
        $deal = self::dealFromForm($get, parent: true);
        $quantity = (float) $get('quantity');

        $total = $deal->toBase(Money::of(
            (float) $get('unit_price') * $quantity,
            $deal->sell_currency,
        ))->toFloat();

        $cost = $deal->toBase(Money::of(
            (float) $get('unit_cost') * $quantity,
            $get('cost_currency') ?: 'CNY',
        ))->toFloat();

        return ['total' => $total, 'profit' => $total - $cost];
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
                // For the permanent-delete guard, which otherwise asks the
                // database once per row whether that deal has been billed.
                'invoices',
            ]))
            ->emptyStateHeading('No deals yet')
            ->emptyStateDescription(
                'A deal is one customer request, followed from "they asked" to "they paid". Everything else in the system hangs off one.'
            )
            ->emptyStateIcon('heroicon-o-briefcase')
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

                /*
                 * Where deleted deals went.
                 *
                 * Defaults to hiding them, so the list reads as it always has —
                 * but a delete you cannot look at again is one nobody dares
                 * make, and the rows were being kept regardless.
                 */
                RecordDeletion::filter(),
            ])
            /*
             * Deleting from the list, which is where you are when you notice a
             * deal should not be there. It had to be done from inside the deal,
             * through a menu, and only while nothing had been invoiced.
             */
            ->recordActions([
                EditAction::make(),
                ...RecordDeletion::actions(),
            ]);
    }

    /**
     * The rest of the deal, on the deal.
     *
     * The design called this "the main working screen — lines, quotation,
     * purchases, consignments, invoices, payments, profit, all in one place",
     * and it was the lines and nothing else. Every other part of a deal lived
     * on a screen listing every deal's, so the ordinary question — where is
     * this order, has it been paid for, what did I quote — meant leaving the
     * deal to go and find out.
     *
     * In the order the deal happens: what you offered, what it costs you, where
     * the goods are, what the customer was billed.
     */
    public static function getRelations(): array
    {
        return [
            QuotationsRelationManager::class,
            PurchasesRelationManager::class,
            ConsignmentsRelationManager::class,
            InvoicesRelationManager::class,
        ];
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
