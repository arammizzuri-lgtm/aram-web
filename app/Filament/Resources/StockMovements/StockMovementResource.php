<?php

namespace App\Filament\Resources\StockMovements;

use App\Enums\StockMovementType;
use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Models\Shipment;
use App\Models\StockMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The append-only stock ledger.
 *
 * Read-only by design: stock is only ever written through StockLedger, so what
 * happened stays reconstructable and nothing can be quietly adjusted after the fact.
 */
class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Movements';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['product', 'warehouse', 'shipment']))
            ->columns([
                TextColumn::make('occurred_at')->label('When')->dateTime('d M Y H:i')->sortable(),

                TextColumn::make('product.name')
                    ->label('Product')
                    ->weight('medium')
                    ->description(fn (StockMovement $record) => $record->product->sku)
                    ->searchable(['name', 'sku']),

                TextColumn::make('type')->badge()->sortable(),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->formatStateUsing(function (string $state) {
                        $value = (float) $state;

                        return ($value > 0 ? '+' : ($value < 0 ? "\u{2212}" : '')).number_format(abs($value), 0);
                    })
                    ->color(fn (string $state) => match (true) {
                        (float) $state > 0 => 'success',
                        (float) $state < 0 => 'danger',
                        default => 'gray',
                    })
                    ->alignEnd(),

                TextColumn::make('balance_after')
                    ->label('Balance')
                    ->formatStateUsing(fn (string $state) => number_format((float) $state, 0))
                    ->alignEnd(),

                TextColumn::make('unit_cost')
                    ->label('Unit cost')
                    ->money('USD')
                    ->alignEnd()
                    ->visible(fn () => auth()->user()?->can('view_cost')),

                // Per-container traceability: which shipment did this unit arrive on.
                TextColumn::make('shipment.number')
                    ->label('Container')
                    ->placeholder('—')
                    ->badge()
                    ->color('info')
                    ->url(fn (StockMovement $record) => $record->shipment_id
                        ? url("/admin/shipments/{$record->shipment_id}/costing")
                        : null),

                TextColumn::make('warehouse.name')->label('Warehouse')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->filters([
                SelectFilter::make('type')->options(StockMovementType::class)->multiple(),

                SelectFilter::make('shipment_id')
                    ->label('Container')
                    ->options(fn () => Shipment::query()->orderByDesc('id')->pluck('number', 'id')),

                SelectFilter::make('warehouse_id')->label('Warehouse')->relationship('warehouse', 'name')->preload(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockMovements::route('/'),
        ];
    }
}
