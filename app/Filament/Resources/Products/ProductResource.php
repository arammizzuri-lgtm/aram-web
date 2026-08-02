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
use Filament\Tables\Columns\IconColumn;
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

            Section::make('Units & shipping')
                ->description(
                    'Weight and volume are what a shared freight bill gets split by — '
                    .'sea charges for space, air for weight.'
                )
                ->columns(3)
                ->schema([
                    Select::make('unit_id')
                        ->label('Unit')
                        ->relationship('unit', 'name')
                        ->preload()
                        ->required(),

                    TextInput::make('weight_kg')->label('Weight (kg)')->numeric()->default(0)->suffix('kg'),
                    TextInput::make('volume_cbm')->label('Volume (CBM)')->numeric()->step('0.000001')->default(0)->suffix('m³'),

                    Toggle::make('contains_battery')
                        ->label('Contains a battery')
                        ->helperText(
                            'Lithium batteries cannot travel as ordinary air cargo. Flagging it here '
                            .'warns you before a shipping mode is booked, rather than after the '
                            .'forwarder rejects it and a promised delivery date is already gone.'
                        )
                        ->columnSpanFull(),
                ]),

            Section::make('Pricing')
                ->description('Cost is what you pay. Selling price is per customer type, set below.')
                ->columns(2)
                ->schema([
                    TextInput::make('cost_price')
                        ->label('Typical cost')
                        ->numeric()
                        ->prefix('$')
                        ->helperText('A starting point. The real cost comes from the supplier on each deal.')
                        // Never shown to the assistant, on any screen.
                        ->visible(fn () => auth()->user()?->can('view_cost')),

                    TextInput::make('selling_price')
                        ->label('Standard selling price')
                        ->numeric()
                        ->prefix('$')
                        ->required()
                        ->helperText('Used when no customer-type price applies.'),
                ]),

            Section::make('Availability')
                ->columns(2)
                ->schema([
                    Toggle::make('is_active')->label('Active')->default(true),
                    Toggle::make('is_sellable')->label('Can be sold')->default(true),
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

                TextColumn::make('cost_price')
                    ->label('Cost')
                    ->money('USD')
                    ->alignEnd()
                    ->sortable()
                    // The whole commercial boundary in one line: the assistant
                    // works these screens daily and must never see this column.
                    ->visible(fn () => auth()->user()?->can('view_cost')),

                IconColumn::make('contains_battery')
                    ->label('Battery')
                    ->boolean()
                    ->falseIcon(null)
                    // Only worth flagging when true — it restricts how the goods
                    // can be flown, and an icon on every other row is noise.
                    ->toggleable(),

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

                Filter::make('contains_battery')
                    ->label('Battery goods only')
                    ->query(fn (Builder $query) => $query->where('contains_battery', true))
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
