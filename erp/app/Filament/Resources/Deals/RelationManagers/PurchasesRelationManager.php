<?php

namespace App\Filament\Resources\Deals\RelationManagers;

use App\Filament\Actions\RecordSupplierPayment;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Models\DealPurchase;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * What this deal costs you, on the deal.
 *
 * The purchases existed but lived on a screen of their own, so answering "have
 * I paid for this order?" meant leaving the deal, finding the right rows among
 * every other deal's, and carrying the answer back in your head. The buying
 * side of a deal belongs on the deal.
 *
 * Nothing is created here — a purchase appears because a line named a supplier,
 * which is the promise the whole design rests on.
 */
class PurchasesRelationManager extends RelationManager
{
    protected static string $relationship = 'purchases';

    protected static ?string $title = 'What it costs you';

    /** Entirely cost, so it is invisible to an assistant — as the screen is. */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view_cost') ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return PurchaseResource::form($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['supplier', 'lines', 'costs', 'payments']))
            ->recordTitleAttribute('number')
            ->emptyStateHeading('Nothing bought yet')
            ->emptyStateDescription('Name a supplier on a line above and its purchase appears here.')
            ->columns([
                TextColumn::make('number')->label('Purchase')->weight('medium'),

                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    // The Chinese name is the one the supplier answers to.
                    ->description(fn (DealPurchase $r) => $r->supplier?->name_zh),

                IconColumn::make('bought_at_risk')
                    ->label('At risk')
                    ->boolean()
                    ->falseIcon(null)
                    ->trueColor('warning')
                    ->tooltip('Bought before the customer approved'),

                TextColumn::make('goods')
                    ->label('Goods')
                    // display() carries the currency already.
                    ->state(fn (DealPurchase $r) => $r->goodsTotal()->display())
                    ->alignEnd(),

                TextColumn::make('total_base')
                    ->label('Total')
                    ->state(fn (DealPurchase $r) => $r->totalBase()->toFloat())
                    ->money('USD')
                    ->alignEnd(),

                TextColumn::make('paid')
                    ->label('Paid')
                    ->state(fn (DealPurchase $r) => $r->paidBase()->toFloat())
                    ->money('USD')
                    ->alignEnd(),

                TextColumn::make('outstanding')
                    ->label('Still owed')
                    ->state(fn (DealPurchase $r) => $r->outstandingBase()->toFloat())
                    ->money('USD')
                    ->alignEnd()
                    ->color(fn (DealPurchase $r) => $r->isFullyPaid() ? 'gray' : 'warning'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => DealPurchase::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'paid', 'received' => 'success',
                        'paid_partial' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->recordActions([
                RecordSupplierPayment::make(),
                EditAction::make(),
            ]);
    }
}
