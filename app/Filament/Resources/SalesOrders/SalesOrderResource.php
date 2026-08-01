<?php

namespace App\Filament\Resources\SalesOrders;

use App\Filament\Resources\SalesOrders\Pages\CreateSalesOrder;
use App\Filament\Resources\SalesOrders\Pages\EditSalesOrder;
use App\Filament\Resources\SalesOrders\Pages\ListSalesOrders;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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

class SalesOrderResource extends Resource
{
    protected static ?string $model = SalesOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Sales orders';

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            // Sections default to sharing the top-level grid, which puts the line
            // editor in half a screen. An order builder needs the full width.
            Section::make('Order')
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    Select::make('customer_id')
                        ->label('Customer')
                        ->options(fn () => Customer::query()->active()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        ->live()
                        // The customer decides the tier and currency, so picking
                        // them re-prices everything already on the order.
                        ->afterStateUpdated(function (?string $state, Set $set) {
                            $customer = Customer::find($state);

                            $set('price_tier_id', $customer?->price_tier_id);
                            $set('currency', $customer?->default_currency ?? 'USD');
                        })
                        ->helperText(fn (?string $state) => static::creditNote($state)),

                    Select::make('warehouse_id')
                        ->label('Warehouse')
                        ->options(fn () => Warehouse::query()->pluck('name', 'id'))
                        ->default(fn () => Warehouse::query()->where('is_default', true)->value('id'))
                        ->required()
                        ->live(),

                    DatePicker::make('order_date')->label('Date')->default(now())->required(),

                    Select::make('currency')
                        ->options(['USD' => 'USD', 'IQD' => 'IQD'])
                        ->default('USD')
                        ->required(),

                    Select::make('price_tier_id')
                        ->label('Price tier')
                        ->relationship('priceTier', 'name')
                        ->preload()
                        ->live()
                        ->helperText('Sets the default unit price on new lines.'),

                    DatePicker::make('delivery_date')->label('Delivery')->default(now()->addDays(3)),
                ]),

            Section::make('Lines')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('items')
                        ->relationship()
                        ->label('')
                        ->addActionLabel('Add product')
                        ->reorderable(false)
                        ->columns(12)
                        ->schema([
                            Select::make('product_id')
                                ->label('Product')
                                ->options(fn () => Product::query()
                                    ->where('is_sellable', true)
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (Product $p) => [$p->id => "{$p->sku} · {$p->name}"]))
                                ->searchable()
                                ->required()
                                ->live()
                                ->columnSpan(4)
                                ->afterStateUpdated(function (?string $state, Get $get, Set $set) {
                                    $product = Product::find($state);

                                    if ($product === null) {
                                        return;
                                    }

                                    $set('unit_price', static::priceFor($product, $get('../../price_tier_id')));
                                    $set('tax_rate', $product->tax_rate);
                                })
                                // What Sales may actually promise, live from stock.
                                ->helperText(fn (?string $state, Get $get) => static::availabilityNote(
                                    $state, $get('../../warehouse_id')
                                )),

                            TextInput::make('quantity')
                                ->numeric()
                                ->default(1)
                                ->minValue(0.0001)
                                ->required()
                                ->live(onBlur: true)
                                ->columnSpan(2),

                            TextInput::make('unit_price')
                                ->label('Unit price')
                                ->numeric()
                                ->required()
                                ->live(onBlur: true)
                                ->columnSpan(2)
                                // Selling below the floor is a decision, not a typo,
                                // so it warns rather than silently accepting it.
                                ->helperText(function (?string $state, Get $get) {
                                    $product = Product::find($get('product_id'));
                                    $floor = (float) ($product?->min_selling_price ?? 0);

                                    return $floor > 0 && (float) $state < $floor
                                        ? 'Below the $'.number_format($floor, 2).' minimum'
                                        : null;
                                }),

                            TextInput::make('discount_rate')
                                ->label('Disc %')
                                ->numeric()
                                ->default(0)
                                ->columnSpan(2),

                            TextInput::make('line_total')
                                ->label('Amount')
                                ->disabled()
                                ->dehydrated(false)
                                ->columnSpan(2)
                                ->formatStateUsing(fn (?string $state) => number_format((float) $state, 2)),
                        ]),
                ]),

            Section::make('Notes')
                ->collapsed()
                ->schema([
                    Textarea::make('delivery_address')->rows(2),
                    Textarea::make('notes')->rows(2),
                ]),
        ]);
    }

    /** The tier price if one exists, otherwise the product's list price. */
    public static function priceFor(Product $product, mixed $tierId): float
    {
        if ($tierId) {
            $tierPrice = ProductPrice::query()
                ->where('product_id', $product->id)
                ->inForce((int) $tierId)
                ->value('price');

            if ($tierPrice !== null) {
                return (float) $tierPrice;
            }
        }

        return (float) $product->selling_price;
    }

    private static function availabilityNote(?string $productId, mixed $warehouseId): ?string
    {
        $product = Product::find($productId);

        if ($product === null || ! $product->track_stock) {
            return null;
        }

        $available = $product->stockAvailable($warehouseId ? (int) $warehouseId : null);
        $incoming = $product->stockIncoming($warehouseId ? (int) $warehouseId : null);

        return sprintf(
            '%s available%s',
            rtrim(rtrim(number_format($available, 2), '0'), '.'),
            $incoming > 0 ? ', '.rtrim(rtrim(number_format($incoming, 2), '0'), '.').' incoming' : '',
        );
    }

    private static function creditNote(?string $customerId): ?string
    {
        $customer = Customer::find($customerId);

        if ($customer === null || (float) $customer->credit_limit <= 0) {
            return null;
        }

        return sprintf(
            '$%s of $%s credit used (%s%%). $%s left.',
            number_format($customer->outstandingBalance(), 2),
            number_format((float) $customer->credit_limit, 2),
            $customer->creditUsedPercent(),
            number_format(max(0, $customer->availableCredit()), 2),
        );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('customer')->withCount('items'))
            ->columns([
                TextColumn::make('number')->label('Order')->weight('medium')->searchable()->sortable(),
                TextColumn::make('customer.name')->label('Customer')->searchable()->sortable(),
                TextColumn::make('order_date')->label('Date')->date('d M Y')->sortable(),
                TextColumn::make('items_count')->label('Lines')->alignEnd()->badge()->color('gray'),

                TextColumn::make('total')
                    ->money(fn (SalesOrder $record) => $record->currency ?? 'USD')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('is_reserved')
                    ->label('Stock')
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Reserved' : 'Not reserved')
                    ->color(fn (bool $state) => $state ? 'success' : 'gray'),

                TextColumn::make('credit_approved_at')
                    ->label('Credit')
                    ->formatStateUsing(fn (?string $state) => $state ? 'Approved' : '—')
                    ->badge()
                    ->color('warning')
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => str($state)->replace('_', ' ')->ucfirst())
                    ->color(fn (string $state) => match ($state) {
                        'confirmed' => 'success',
                        'cancelled' => 'danger',
                        'draft' => 'gray',
                        default => 'info',
                    }),
            ])
            ->defaultSort('order_date', 'desc')
            ->filters([
                SelectFilter::make('status')->options([
                    'draft' => 'Draft', 'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled',
                ])->multiple(),

                SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
            ]);
    }

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['number'];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesOrders::route('/'),
            'create' => CreateSalesOrder::route('/create'),
            'edit' => EditSalesOrder::route('/{record}/edit'),
        ];
    }
}
