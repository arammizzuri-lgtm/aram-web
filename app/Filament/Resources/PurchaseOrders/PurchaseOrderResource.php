<?php

namespace App\Filament\Resources\PurchaseOrders;

use App\Filament\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\Warehouse;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
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

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Purchasing';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Purchase orders';

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Order')
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    Select::make('supplier_id')
                        ->label('Supplier')
                        ->options(fn () => Supplier::query()->active()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        ->live()
                        // The supplier carries the trading terms, so choosing one
                        // fills in what would otherwise be retyped every order.
                        ->afterStateUpdated(function (?string $state, Set $set) {
                            $supplier = Supplier::find($state);

                            $set('currency', $supplier?->default_currency ?? 'USD');
                            $set('incoterm', $supplier?->default_incoterm ?? 'FOB');
                            $set('deposit_percent', $supplier?->deposit_percent ?? 30);
                            $set('payment_terms_days', $supplier?->payment_terms_days ?? 30);
                            $set('port_of_loading', $supplier?->port_of_loading);
                        }),

                    Select::make('warehouse_id')
                        ->label('Deliver to')
                        ->options(fn () => Warehouse::query()->pluck('name', 'id'))
                        ->default(fn () => Warehouse::query()->where('is_default', true)->value('id'))
                        ->required(),

                    DatePicker::make('order_date')->label('Order date')->default(now())->required(),

                    TextInput::make('supplier_reference')
                        ->label('Their proforma no.')
                        ->maxLength(64)
                        ->helperText('The PI number they quote back at you.'),

                    Select::make('currency')
                        ->options(['USD' => 'USD', 'CNY' => 'CNY', 'IQD' => 'IQD'])
                        ->default('USD')
                        ->required(),

                    Select::make('incoterm')
                        ->options([
                            'EXW' => 'EXW — Ex Works',
                            'FOB' => 'FOB — Free On Board',
                            'CIF' => 'CIF — Cost, Insurance & Freight',
                            'DDP' => 'DDP — Delivered Duty Paid',
                        ])
                        ->default('FOB')
                        ->required()
                        ->helperText('Decides which shipping costs you get billed for.'),

                    TextInput::make('deposit_percent')->label('Deposit')->numeric()->suffix('%')->default(30),
                    TextInput::make('payment_terms_days')->label('Terms')->numeric()->suffix('days')->default(30),
                    DatePicker::make('expected_date')->label('Expected arrival'),
                ]),

            Section::make('Lines')
                ->description('Order in cartons — pieces are worked out from the pack size.')
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
                                    ->where('is_purchasable', true)
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

                                    $supplierProduct = static::supplierProduct($product, $get('../../supplier_id'));

                                    $set('pack_size', (float) ($supplierProduct?->pack_size ?: $product->pack_size ?: 1));
                                    $set('unit_price', (float) ($supplierProduct?->unit_price ?: $product->cost_price));
                                    $set('supplier_sku', $supplierProduct?->supplier_sku);
                                    $set('hs_code', $product->hs_code);
                                    $set('duty_rate', $product->effectiveDutyRate());
                                    $set('unit_weight_kg', (float) $product->weight_kg);
                                    $set('unit_volume_cbm', (float) $product->volume_cbm);
                                })
                                ->helperText(fn (?string $state, Get $get) => static::sourcingNote(
                                    $state, $get('../../supplier_id')
                                )),

                            TextInput::make('order_quantity')
                                ->label('Cartons')
                                ->numeric()
                                ->default(1)
                                ->minValue(0.0001)
                                ->required()
                                ->live(onBlur: true)
                                ->columnSpan(2)
                                // Stock is held in pieces, so the carton figure is
                                // converted here rather than anywhere downstream.
                                ->afterStateUpdated(fn (?string $state, Get $get, Set $set) => $set(
                                    'quantity', (float) $state * (float) ($get('pack_size') ?: 1)
                                ))
                                ->helperText(function (?string $state, Get $get) {
                                    $pieces = (float) $state * (float) ($get('pack_size') ?: 1);
                                    $moq = static::moqFor($get('product_id'), $get('../../supplier_id'));

                                    $note = rtrim(rtrim(number_format($pieces, 2), '0'), '.').' pcs';

                                    return $moq > 0 && $pieces < $moq
                                        ? $note.' · below MOQ of '.rtrim(rtrim(number_format($moq, 2), '0'), '.')
                                        : $note;
                                }),

                            TextInput::make('pack_size')
                                ->label('Pcs/ctn')
                                ->numeric()
                                ->default(1)
                                ->required()
                                ->live(onBlur: true)
                                ->columnSpan(1)
                                ->afterStateUpdated(fn (?string $state, Get $get, Set $set) => $set(
                                    'quantity', (float) ($get('order_quantity') ?: 0) * (float) ($state ?: 1)
                                )),

                            TextInput::make('unit_price')
                                ->label('Price/pc')
                                ->numeric()
                                ->required()
                                ->live(onBlur: true)
                                ->columnSpan(2)
                                ->helperText(fn (?string $state, Get $get) => static::priceChangeNote(
                                    $state, $get('product_id'), $get('../../supplier_id')
                                )),

                            TextInput::make('quantity')
                                ->label('Total pcs')
                                ->numeric()
                                ->required()
                                ->columnSpan(2)
                                ->disabled()
                                ->dehydrated(),

                            TextInput::make('line_total')
                                ->label('Amount')
                                ->disabled()
                                ->dehydrated(false)
                                ->columnSpan(1)
                                ->formatStateUsing(fn (?string $state) => number_format((float) $state, 2)),

                            Hidden::make('supplier_sku'),
                            Hidden::make('hs_code'),
                            Hidden::make('duty_rate'),
                            Hidden::make('unit_weight_kg'),
                            Hidden::make('unit_volume_cbm'),
                        ]),
                ]),

            Section::make('Notes')
                ->collapsed()
                ->columnSpanFull()
                ->schema([
                    TextInput::make('port_of_loading')->maxLength(128),
                    Textarea::make('notes')->rows(2),
                ]),
        ]);
    }

    /** This supplier's own listing for a product, if they carry it. */
    private static function supplierProduct(Product $product, mixed $supplierId): ?SupplierProduct
    {
        return $supplierId
            ? SupplierProduct::query()
                ->where('supplier_id', $supplierId)
                ->where('product_id', $product->id)
                ->first()
            : null;
    }

    private static function moqFor(mixed $productId, mixed $supplierId): float
    {
        $product = Product::find($productId);

        return $product && $supplierId
            ? (float) (static::supplierProduct($product, $supplierId)?->moq ?? 0)
            : 0.0;
    }

    /**
     * Flag when a typed price differs from the supplier's own catalogue.
     *
     * Catching a price that has drifted at the moment of ordering is far cheaper
     * than discovering it on the invoice.
     */
    private static function priceChangeNote(?string $typed, mixed $productId, mixed $supplierId): ?string
    {
        $product = Product::find($productId);

        if ($product === null || ! $supplierId) {
            return null;
        }

        $catalogue = (float) (static::supplierProduct($product, $supplierId)?->unit_price ?? 0);

        if ($catalogue <= 0 || $typed === null || $typed === '') {
            return null;
        }

        $delta = ((float) $typed - $catalogue) / $catalogue * 100;

        return abs($delta) < 0.05
            ? 'Matches their price list'
            : sprintf('Their list price is $%s (%s%s%%)',
                number_format($catalogue, 2),
                $delta > 0 ? '+' : '',
                number_format($delta, 1),
            );
    }

    private static function sourcingNote(?string $productId, mixed $supplierId): ?string
    {
        $product = Product::find($productId);

        if ($product === null) {
            return null;
        }

        $supplierProduct = $supplierId ? static::supplierProduct($product, $supplierId) : null;

        if ($supplierProduct === null) {
            return 'This supplier does not list this product — the price will need entering by hand.';
        }

        return sprintf(
            'Their code %s · %s in stock · %s incoming',
            $supplierProduct->supplier_sku,
            rtrim(rtrim(number_format($product->stockOnHand(), 2), '0'), '.'),
            rtrim(rtrim(number_format($product->stockIncoming(), 2), '0'), '.'),
        );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('supplier'))
            ->columns([
                TextColumn::make('number')
                    ->label('Order')
                    ->weight('medium')
                    ->description(fn (PurchaseOrder $record) => $record->supplier_reference
                        ? 'PI '.$record->supplier_reference
                        : null)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('supplier.name')->label('Supplier')->searchable()->sortable(),

                TextColumn::make('order_date')->label('Ordered')->date('d M Y')->sortable(),

                TextColumn::make('expected_date')
                    ->label('Expected')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('incoterm')->badge()->color('gray')->toggleable(),

                TextColumn::make('total')
                    ->money(fn (PurchaseOrder $record) => $record->currency ?? 'USD')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('deposit')
                    ->label('Deposit')
                    ->state(fn (PurchaseOrder $record) => round((float) $record->total * (float) $record->deposit_percent / 100, 2))
                    ->money(fn (PurchaseOrder $record) => $record->currency ?? 'USD')
                    ->description(fn (PurchaseOrder $record) => $record->deposit_percent.'%')
                    ->alignEnd()
                    ->toggleable(),

                TextColumn::make('progress')
                    ->label('Received')
                    ->state(fn (PurchaseOrder $record) => $record->receivedPercent().'%')
                    ->alignEnd()
                    ->badge()
                    ->color(fn (PurchaseOrder $record) => match (true) {
                        $record->receivedPercent() >= 100 => 'success',
                        $record->receivedPercent() > 0 => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('status')->badge()->sortable(),
            ])
            ->defaultSort('order_date', 'desc')
            ->filters([
                SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')->options([
                    'draft' => 'Draft',
                    'sent' => 'Sent',
                    'confirmed' => 'Confirmed',
                    'in_production' => 'In production',
                    'shipped' => 'Shipped',
                    'partially_received' => 'Partially received',
                    'received' => 'Received',
                    'cancelled' => 'Cancelled',
                ])->multiple(),
            ]);
    }

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['number', 'supplier_reference'];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseOrders::route('/'),
            'create' => CreatePurchaseOrder::route('/create'),
            'edit' => EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}
