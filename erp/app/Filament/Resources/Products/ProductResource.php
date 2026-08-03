<?php

namespace App\Filament\Resources\Products;

use App\Filament\Actions\RecordDeletion;
use App\Filament\Concerns\KeepsDeletedRecords;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use App\Models\ProductSize;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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
    use KeepsDeletedRecords;

    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Where it belongs')
                ->description('The price list it appears in, whose catalogue it comes from, and what it sits under.')
                ->columns(3)
                ->schema([
                    Select::make('price_list_section_id')
                        ->label('Price list')
                        ->relationship('section', 'name')
                        ->required()
                        ->preload()
                        ->live()
                        ->helperText('Crystals, Textile, Packaging or Furniture.'),

                    Select::make('supplier_id')
                        ->label('Supplier')
                        ->relationship('supplier', 'name')
                        ->searchable()
                        ->required()
                        ->preload()
                        ->live()
                        ->helperText('Each supplier keeps their own tree.'),

                    /*
                     * Only the branch this product could actually join: same
                     * supplier, same price list, and never itself or anything
                     * below it — that last one would make a loop, and every
                     * walk up the tree afterwards would run forever.
                     */
                    Select::make('parent_id')
                        ->label('Sits under')
                        ->searchable()
                        ->placeholder('Nothing — this is a top-level shelf')
                        ->helperText('Leave empty for a top level like "Crystal".')
                        ->options(function (Get $get, ?Product $record): array {
                            $supplierId = $get('supplier_id');
                            $sectionId = $get('price_list_section_id');

                            if (blank($supplierId) || blank($sectionId)) {
                                return [];
                            }

                            return Product::query()
                                ->where('supplier_id', $supplierId)
                                ->where('price_list_section_id', $sectionId)
                                ->when($record, fn (Builder $q) => $q->whereKeyNot($record->descendantIds()))
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (Product $p) => [$p->id => $p->pathLabel()])
                                ->all();
                        }),
                ]),

            Section::make('Identity')
                ->columns(2)
                ->schema([
                    // No SKU field: it is generated from the section and the name.
                    // See Product::generateSku — typing one is still allowed by
                    // the model, it just is not something this screen asks for.
                    TextInput::make('name')
                        ->label('Name or code')
                        ->required()
                        ->maxLength(255)
                        ->helperText('The supplier\'s own name for it — "Flat Crystal", "P13".')
                        ->columnSpanFull(),

                    TextInput::make('barcode')->maxLength(64),

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
                // Category is gone: the tree above is the classification now,
                // and keeping both meant filing the same product twice.
                ->schema([
                    Select::make('brand_id')
                        ->label('Brand')
                        ->relationship('brand', 'name')
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
                    // Not required any more: a shelf like "Flat Crystal" is
                    // never bought by any unit, only the sizes under it are.
                    Select::make('unit_id')
                        ->label('Unit')
                        ->relationship('unit', 'name')
                        ->preload(),

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

            Section::make('Sizes')
                ->description(
                    'The sizes this is sold in. Prices are not set here — you fill those in '
                    .'on the Price Lists screen, and can still adjust them on a deal.'
                )
                ->schema([
                    Repeater::make('sizes')
                        ->relationship()
                        ->hiddenLabel()
                        ->addActionLabel('Add a size')
                        // Starts empty. A repeater's default row is a required
                        // one, which would mean a shelf could never be saved
                        // without first deleting a size it was never going to have.
                        ->defaultItems(0)
                        ->orderColumn('display_order')
                        ->columns(2)
                        ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                        ->schema([
                            /*
                             * Free text, because 10mm means something to a crystal
                             * and nothing to a bolt of fabric. The suggestions are
                             * the labels this section already uses, so "10mm" and
                             * "10 mm" do not drift apart and quietly stop lining
                             * up when two suppliers are compared.
                             */
                            TextInput::make('label')
                                ->label('Size')
                                ->required()
                                ->maxLength(64)
                                ->helperText('"10mm", "150cm wide", "3 seater".')
                                ->datalist(fn (Get $get): array => ProductSize::query()
                                    ->whereHas(
                                        'product',
                                        fn (Builder $q) => $q->where(
                                            'price_list_section_id',
                                            $get('../../price_list_section_id')
                                        )
                                    )
                                    ->distinct()
                                    ->orderBy('label')
                                    ->pluck('label')
                                    ->all()),

                            TextInput::make('moq')
                                ->label('Minimum order')
                                ->numeric()
                                ->helperText('Optional. How few the supplier will sell.'),
                        ]),
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
            // The name column walks up to the parent and the sizes column counts
            // priced ones, so both are loaded once rather than per row.
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['parent', 'sizes', 'section', 'supplier']))
            ->emptyStateHeading('No products yet')
            ->emptyStateDescription(
                'Your catalogue, one tree per supplier. Nest it as deep as the goods go — '
                .'Crystal holds Flat Crystal holds P13 — and give the bottom level its sizes. '
                .'Prices come later, on the Price Lists screen.'
            )
            ->emptyStateIcon('heroicon-o-cube')
            ->columns([
                TextColumn::make('name')
                    ->weight('medium')
                    // The trail, not the SKU: "Crystal › Flat Crystal" under P13
                    // is what tells you which of four similar codes you are on.
                    ->description(fn (Product $record) => $record->parent?->pathLabel())
                    ->searchable(['name', 'sku', 'barcode', 'name_zh'])
                    ->sortable(),

                TextColumn::make('section.name')
                    ->label('Price list')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->placeholder('—')
                    ->sortable(),

                /*
                 * Sizes and how many of them are priced, in one column. An
                 * unpriced size is the thing that stops a deal being quoted, so
                 * it is worth seeing from the list rather than only after
                 * opening the product.
                 */
                TextColumn::make('sizes_summary')
                    ->label('Sizes')
                    ->state(function (Product $record): string {
                        $total = $record->sizes->count();

                        if ($total === 0) {
                            return $record->isShelf() ? '—' : 'none yet';
                        }

                        $priced = $record->sizes->filter->isPriced()->count();

                        return $priced === $total
                            ? "{$total} priced"
                            : "{$priced} of {$total} priced";
                    })
                    ->badge()
                    ->color(function (Product $record): string {
                        $total = $record->sizes->count();

                        if ($total === 0) {
                            return 'gray';
                        }

                        return $record->sizes->filter->isPriced()->count() === $total
                            ? 'success'
                            : 'warning';
                    })
                    ->visible(fn () => auth()->user()?->can('view_cost')),

                IconColumn::make('contains_battery')
                    ->label('Battery')
                    ->boolean()
                    ->falseIcon(null)
                    // Only worth flagging when true — it restricts how the goods
                    // can be flown, and an icon on every other row is noise.
                    ->toggleable(),

                // Selling price and margin are gone from here: nothing is sold
                // at a stored price any more. A deal line takes the cost from
                // the price list and marks it up, where the number can be
                // argued with in front of the customer it applies to.
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('price_list_section_id')
                    ->label('Price list')
                    ->relationship('section', 'name')
                    ->preload()
                    ->multiple(),

                SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                Filter::make('top_level')
                    ->label('Top level only')
                    ->query(fn (Builder $query) => $query->whereNull('parent_id'))
                    ->toggle(),

                Filter::make('unpriced')
                    ->label('Has a size with no price')
                    ->query(fn (Builder $query) => $query->whereHas(
                        'sizes',
                        fn (Builder $q) => $q->whereNull('cost_price')
                    ))
                    ->toggle()
                    ->visible(fn () => auth()->user()?->can('view_cost')),

                SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->relationship('brand', 'name')
                    ->preload(),

                TernaryFilter::make('is_active')->label('Active')->default(true),

                Filter::make('contains_battery')
                    ->label('Battery goods only')
                    ->query(fn (Builder $query) => $query->where('contains_battery', true))
                    ->toggle(),

                RecordDeletion::filter(),
            ])
            ->recordActions([
                EditAction::make(),
                ...RecordDeletion::actions(),
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
            'Supplier' => $record->supplier?->name,
            'Where' => $record->pathLabel(),
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
