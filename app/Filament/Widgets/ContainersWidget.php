<?php

namespace App\Filament\Widgets;

use App\Models\Shipment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** What is on the water and what it will cost when it lands. */
class ContainersWidget extends TableWidget
{
    protected static ?string $heading = 'Containers';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Shipment::query()
                    ->whereNotIn('status', ['closed', 'cancelled'])
                    ->orderByRaw('case when ata is null then 0 else 1 end')
                    ->orderBy('eta')
            )
            ->columns([
                TextColumn::make('number')
                    ->label('Shipment')
                    ->weight('medium')
                    ->description(fn (Shipment $record) => $record->container_number ?: 'no container yet')
                    ->url(fn (Shipment $record) => url("/admin/shipments/{$record->id}/costing")),

                TextColumn::make('route')
                    ->label('Route')
                    ->state(fn (Shipment $record) => trim(($record->port_of_loading ?? '?').' → '.($record->port_of_discharge ?? '?'))),

                TextColumn::make('status')->badge(),

                TextColumn::make('eta')
                    ->label('Arrival')
                    ->date('d M Y')
                    ->description(fn (Shipment $record) => match (true) {
                        $record->ata !== null => 'arrived',
                        $record->eta !== null => $record->eta->diffForHumans(),
                        default => null,
                    }),

                TextColumn::make('total_goods_base')
                    ->label('Goods')
                    ->money('USD')
                    ->alignEnd(),

                TextColumn::make('total_costs_base')
                    ->label('Shipping')
                    ->money('USD')
                    ->alignEnd()
                    ->description(fn (Shipment $record) => '+'.$record->costUpliftPercent().'%'),

                TextColumn::make('landed_cost_status')->label('Costing')->badge(),
            ])
            ->paginated([5, 10]);
    }

    public static function canView(): bool
    {
        return auth()->user()?->can('view_cost') ?? false;
    }
}
