<?php

namespace App\Filament\Resources\ProductCategories;

use App\Filament\Resources\ProductCategories\Pages\ManageProductCategories;
use App\Models\ProductCategory;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Categories are data, not code.
 *
 * Crystals, furniture, textiles and anything added in five years are all just
 * rows here — which is what lets the business expand into a new product line
 * without touching the system.
 */
class ProductCategoryResource extends Resource
{
    protected static ?string $model = ProductCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Categories';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug((string) $state))),

            TextInput::make('slug')->required()->unique(ignoreRecord: true)->maxLength(255),

            Select::make('parent_id')
                ->label('Parent category')
                ->relationship('parent', 'name')
                ->searchable()
                ->preload()
                ->helperText('Leave empty for a top-level category.'),

            TextInput::make('sort_order')->numeric()->default(0),

            TextInput::make('default_hs_code')
                ->label('Default HS code')
                ->maxLength(32)
                ->helperText('Inherited by products in this category.'),

            TextInput::make('default_duty_rate')
                ->label('Default duty rate')
                ->numeric()
                ->suffix('%')
                ->helperText('Used when a product has no rate of its own.'),

            Textarea::make('description')->rows(2)->columnSpanFull(),

            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // The parent name is shown on every row, so it is eager loaded rather
            // than fetched per row.
            ->modifyQueryUsing(fn ($query) => $query->with('parent'))
            ->columns([
                TextColumn::make('name')
                    ->weight('medium')
                    ->description(fn (ProductCategory $record) => $record->parent?->name)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('products')
                    ->alignEnd()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('default_hs_code')->label('HS code')->placeholder('—'),

                TextColumn::make('default_duty_rate')
                    ->label('Duty')
                    ->formatStateUsing(fn (?string $state) => $state !== null ? rtrim(rtrim($state, '0'), '.').'%' : '—')
                    ->alignEnd(),

                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Active' : 'Inactive')
                    ->color(fn (bool $state) => $state ? 'success' : 'danger'),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProductCategories::route('/'),
        ];
    }
}
