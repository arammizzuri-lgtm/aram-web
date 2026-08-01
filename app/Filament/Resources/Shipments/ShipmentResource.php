<?php

namespace App\Filament\Resources\Shipments;

use App\Enums\LandedCostStatus;
use App\Enums\ShipmentStatus;
use App\Filament\Resources\Shipments\Pages\ListShipments;
use App\Filament\Resources\Shipments\Pages\ViewShipmentCosting;
use App\Models\Shipment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ShipmentResource extends Resource
{
    protected static ?string $model = Shipment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Logistics';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label('Shipment')
                    ->weight('medium')
                    ->description(fn (Shipment $record) => $record->container_number)
                    ->searchable(['number', 'container_number', 'bl_number'])
                    ->sortable(),

                TextColumn::make('route')
                    ->label('Route')
                    ->state(fn (Shipment $record) => trim(($record->port_of_loading ?? '?').' → '.($record->port_of_discharge ?? '?')))
                    ->toggleable(),

                TextColumn::make('status')->badge()->sortable(),

                TextColumn::make('eta')
                    ->label('ETA')
                    ->date('d M Y')
                    ->description(fn (Shipment $record) => $record->ata ? 'arrived '.$record->ata->diffForHumans() : null)
                    ->sortable(),

                TextColumn::make('total_volume_cbm')
                    ->label('CBM')
                    ->formatStateUsing(fn (string $state) => number_format((float) $state, 2))
                    ->alignEnd()
                    ->toggleable(),

                TextColumn::make('total_goods_base')
                    ->label('Goods')
                    ->money('USD')
                    ->alignEnd()
                    ->visible(fn () => auth()->user()?->can('view_cost')),

                TextColumn::make('total_costs_base')
                    ->label('Shipping costs')
                    ->money('USD')
                    ->alignEnd()
                    ->description(fn (Shipment $record) => '+'.$record->costUpliftPercent().'% uplift')
                    ->visible(fn () => auth()->user()?->can('view_cost')),

                TextColumn::make('landed_cost_status')
                    ->label('Costing')
                    ->badge()
                    // Anything not Final is provisional; the badge is what stops
                    // someone quoting a price off a guess without realising.
                    ->icon(fn (LandedCostStatus $state) => $state->isProvisional() ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle'),
            ])
            ->defaultSort('eta', 'desc')
            ->filters([
                SelectFilter::make('status')->options(ShipmentStatus::class)->multiple(),
                SelectFilter::make('landed_cost_status')->label('Costing')->options(LandedCostStatus::class),
            ])
            ->recordActions([
                Action::make('costing')
                    ->label('Landed cost')
                    ->icon('heroicon-m-calculator')
                    ->url(fn (Shipment $record) => ViewShipmentCosting::getUrl(['record' => $record])),
            ]);
    }

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['number', 'container_number', 'bl_number'];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShipments::route('/'),
            'costing' => ViewShipmentCosting::route('/{record}/costing'),
        ];
    }
}
