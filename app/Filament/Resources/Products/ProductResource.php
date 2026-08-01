<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use BackedEnum;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identity')
                ->columns(2)
                ->schema([
                    TextInput::make('sku')
                        ->label('SKU')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(64),

                    TextInput::make('barcode')->maxLength(64),

                    TextInput::make('name')->required()->maxLength(255)->columnSpanFull(),

                    // The name you paste into WeChat when asking the supplier about it.
                    TextInput::make('name_zh')
                        ->label('Chinese name 中文名')
                        ->maxLength(255)
                        ->helperText('Used when talking to the supplier.'),

                    TextInput::make('name_ar')->label('Arabic name')->maxLength(255),

                    Textarea::make('description')->rows(3)->columnSpanFull(),
                ]),

            Section::make('Classification')
                ->columns(3)
                ->schema([
                    Select::make('product_category_id')
                        ->label('Category')
                        ->relationship('category', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),

                    Select::make('brand_id')
                        ->label('Brand')
                        ->relationship('brand', 'name')
                        ->searchable()
                        ->preload(),

                    Select::make('default_supplier_id')
                        ->label('Default supplier')
                        ->relationship('defaultSupplier', 'name')
                        ->searchable()
                        ->preload(),

                    KeyValue::make('attributes')
                        ->label('Attributes')
                        ->keyLabel('Attribute')
                        ->valueLabel('Value')
                        ->helperText('Colour, size, material — whatever this category needs. No schema change required.')
                        ->columnSpanFull(),
                ]),

            Section::make('Units & packing')
                ->description('Weight and volume drive freight and duty allocation — a missing CBM makes landed cost wrong.')
                ->columns(3)
                ->schema([
                    Select::make('unit_id')
                        ->label('Stock unit')
                        ->relationship('unit', 'name')
                        ->preload()
                        ->required(),

                    Select::make('purchase_unit_id')
                        ->label('Purchase unit')
                        ->relationship('purchaseUnit', 'name')
                        ->preload(),

                    TextInput::make('pack_size')
                        ->label('Units per purchase unit')
                        ->numeric()
                        ->default(1)
                        ->helperText('e.g. 24 pieces per carton.'),

                    TextInput::make('weight_kg')->label('Weight (kg)')->numeric()->default(0)->suffix('kg'),
                    TextInput::make('volume_cbm')->label('Volume (CBM)')->numeric()->step('0.000001')->default(0)->suffix('m³'),
                    TextInput::make('country_of_origin')->label('Origin')->default('CN')->length(2),
                ]),

            Section::make('Customs')
                ->columns(2)
                ->schema([
                    TextInput::make('hs_code')->label('HS code')->maxLength(32),
                    TextInput::make('duty_rate')
                        ->label('Duty rate')
                        ->numeric()
                        ->suffix('%')
                        ->helperText('Blank falls back to the category default.'),
                ]),

            Section::make('Pricing')
                ->columns(3)
                ->schema([
                    TextInput::make('cost_price')
                        ->label('Supplier price')
                        ->numeric()
                        ->prefix('$')
                        ->helperText('Before freight and duty.'),

                    TextInput::make('average_cost')
                        ->label('Landed cost')
                        ->numeric()
                        ->prefix('$')
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('Weighted average of what actually arrived. Set by goods receipt.'),

                    TextInput::make('selling_price')->numeric()->prefix('$')->required(),

                    TextInput::make('min_selling_price')
                        ->label('Minimum price')
                        ->numeric()
                        ->prefix('$')
                        ->helperText('Sales are warned below this.'),

                    TextInput::make('target_margin_percent')->label('Target margin')->numeric()->suffix('%'),
                    TextInput::make('tax_rate')->label('Tax rate')->numeric()->suffix('%')->default(0),
                ]),

            Section::make('Stock control')
                ->columns(3)
                ->schema([
                    TextInput::make('reorder_level')->numeric()->default(0),
                    TextInput::make('reorder_quantity')->numeric()->default(0),
                    TextInput::make('lead_time_days')->label('Lead time (days)')->numeric(),
                    Toggle::make('track_stock')->default(true),
                    Toggle::make('is_sellable')->label('Sellable')->default(true),
                    Toggle::make('is_purchasable')->label('Purchasable')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->weight('medium')
                    ->description(fn (Product $record) => $record->sku)
                    ->searchable(['name', 'sku', 'barcode', 'name_zh'])
                    ->sortable(),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('defaultSupplier.name')
                    ->label('Supplier')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('stock')
                    ->label('In stock')
                    ->state(fn (Product $record) => number_format($record->stockOnHand(), 0))
                    ->alignEnd()
                    ->badge()
                    ->color(fn (Product $record) => match (true) {
                        $record->stockOnHand() <= 0 => 'danger',
                        $record->stockOnHand() <= (float) $record->reorder_level => 'warning',
                        default => 'success',
                    }),

                TextColumn::make('average_cost')
                    ->label('Landed cost')
                    // Nothing received yet means there is no landed cost — showing
                    // $0.00 would read as "free" next to a healthy-looking margin
                    // that is really based on the supplier price.
                    ->formatStateUsing(fn (string $state, Product $record) => (float) $state > 0
                        ? '$'.number_format((float) $state, 2)
                        : '—')
                    ->description(fn (Product $record) => (float) $record->average_cost > 0
                        ? null
                        : 'supplier $'.number_format((float) $record->cost_price, 2))
                    ->alignEnd()
                    ->sortable()
                    // Cost and margin are commercially sensitive: hidden from Sales
                    // and Warehouse by the same permission that guards them elsewhere.
                    ->visible(fn () => auth()->user()?->can('view_cost')),

                TextColumn::make('selling_price')
                    ->label('Selling')
                    ->money('USD')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('margin')
                    ->label('Margin')
                    ->state(fn (Product $record) => $record->marginPercent().'%')
                    ->alignEnd()
                    ->badge()
                    ->color(fn (Product $record) => match (true) {
                        $record->marginPercent() <= 0 => 'danger',
                        $record->marginPercent() < 20 => 'warning',
                        default => 'success',
                    })
                    ->visible(fn () => auth()->user()?->can('view_cost')),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('product_category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                SelectFilter::make('default_supplier_id')
                    ->label('Supplier')
                    ->relationship('defaultSupplier', 'name')
                    ->preload()
                    ->multiple(),

                SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->relationship('brand', 'name')
                    ->preload(),

                TernaryFilter::make('is_active')->label('Active')->default(true),

                Filter::make('low_stock')
                    ->label('Low stock only')
                    ->query(fn (Builder $query) => $query->lowStock())
                    ->toggle(),
            ]);
    }

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'sku', 'barcode', 'name_zh'];
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'SKU' => $record->sku,
            'Category' => $record->category?->name,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
