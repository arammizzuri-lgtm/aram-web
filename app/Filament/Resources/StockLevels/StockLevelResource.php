<?php

namespace App\Filament\Resources\StockLevels;

use App\Filament\Resources\StockLevels\Pages\ListStockLevels;
use App\Models\StockLevel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class StockLevelResource extends Resource
{
    protected static ?string $model = StockLevel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Stock';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['product.category', 'warehouse']))
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->weight('medium')
                    ->description(fn (StockLevel $record) => $record->product->sku)
                    ->searchable(['name', 'sku'])
                    ->sortable(),

                TextColumn::make('product.category.name')->label('Category')->badge()->color('gray'),

                TextColumn::make('warehouse.name')->label('Warehouse')->toggleable(),

                TextColumn::make('quantity')
                    ->label('On hand')
                    ->formatStateUsing(fn (string $state) => number_format((float) $state, 0))
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('reserved_quantity')
                    ->label('Reserved')
                    ->formatStateUsing(fn (string $state) => number_format((float) $state, 0))
                    ->alignEnd()
                    ->toggleable(),

                // What Sales may actually promise.
                TextColumn::make('available')
                    ->label('Available')
                    ->state(fn (StockLevel $record) => number_format($record->available_quantity, 0))
                    ->alignEnd()
                    ->badge()
                    ->color(fn (StockLevel $record) => match (true) {
                        $record->available_quantity <= 0 => 'danger',
                        $record->available_quantity <= (float) $record->product->reorder_level => 'warning',
                        default => 'success',
                    }),

                TextColumn::make('incoming_quantity')
                    ->label('Incoming')
                    ->formatStateUsing(fn (string $state) => number_format((float) $state, 0))
                    ->alignEnd()
                    ->toggleable(),

                TextColumn::make('average_cost')
                    ->label('Avg cost')
                    ->money('USD')
                    ->alignEnd()
                    ->visible(fn () => auth()->user()?->can('view_cost')),

                TextColumn::make('total_value')
                    ->label('Value')
                    ->money('USD')
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->money('USD')->label('Total'))
                    ->visible(fn () => auth()->user()?->can('view_cost')),
            ])
            ->defaultSort('total_value', 'desc')
            ->filters([
                SelectFilter::make('warehouse_id')->label('Warehouse')->relationship('warehouse', 'name')->preload(),

                Filter::make('in_stock')
                    ->label('In stock only')
                    ->query(fn (Builder $query) => $query->where('quantity', '>', 0))
                    ->toggle(),

                Filter::make('out_of_stock')
                    ->label('Out of stock')
                    ->query(fn (Builder $query) => $query->where('quantity', '<=', 0))
                    ->toggle(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockLevels::route('/'),
        ];
    }
}
