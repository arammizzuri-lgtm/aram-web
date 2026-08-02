<?php

namespace App\Filament\Resources\Purchases;

use App\Filament\Resources\Deals\DealResource;
use App\Filament\Resources\Purchases\Pages\ManagePurchases;
use App\Models\DealPurchase;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The internal purchase invoices — private, and never shown to a customer.
 *
 * The whole screen is gated on `view_cost`. There is no assistant-safe version
 * of it, because everything on it is cost: what you paid, to whom, and what is
 * still owed.
 *
 * You never create one here. Naming a supplier on a deal line creates it; this
 * screen is where you then record what you paid against it.
 */
class PurchaseResource extends Resource
{
    protected static ?string $model = DealPurchase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Trading';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Purchases';

    protected static ?string $modelLabel = 'purchase';

    protected static ?string $recordTitleAttribute = 'number';

    /** Cost is the entire content of this screen, so the screen itself is gated. */
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_cost') ?? false;
    }

    public static function canCreate(): bool
    {
        // Purchases appear from deal lines. Creating one standing alone would
        // be goods bought for nobody, which is the thing this business never does.
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(3)
                ->schema([
                    TextInput::make('supplier_reference')
                        ->label('Their invoice / PI no.')
                        ->maxLength(255)
                        ->helperText('What the supplier calls it.'),

                    Select::make('currency')
                        ->options(['CNY' => 'RMB', 'USD' => 'USD'])
                        ->required(),

                    Select::make('status')
                        ->options(DealPurchase::STATUSES)
                        ->required()
                        ->helperText('Paid status follows the payments on its own.'),

                    DatePicker::make('ordered_at')->label('Ordered'),

                    Textarea::make('notes')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'supplier', 'deal.customer', 'lines', 'costs', 'payments',
            ]))
            ->columns([
                TextColumn::make('number')->label('Purchase')->weight('medium')->searchable(),

                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->description(fn (DealPurchase $r) => $r->supplier?->name_zh)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('deal.number')
                    ->label('For deal')
                    ->description(fn (DealPurchase $r) => $r->deal?->customer?->name)
                    ->url(fn (DealPurchase $r) => DealResource::getUrl('edit', ['record' => $r->deal_id]))
                    ->color('primary'),

                /*
                 * Bought before the customer committed.
                 *
                 * Approval is a warning rather than a wall, so this is where the
                 * consequence of pushing through becomes visible.
                 */
                IconColumn::make('bought_at_risk')
                    ->label('At risk')
                    ->boolean()
                    ->falseIcon(null)
                    ->trueColor('warning')
                    ->tooltip('Bought before the customer approved'),

                TextColumn::make('goods')
                    ->label('Goods')
                    // display() already carries the currency; appending it again
                    // gave "¥6,250.00 CNY".
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

                /*
                 * The exchange house's cut. Blank when nothing was recorded,
                 * rather than $0.00 — "unknown" and "cost nothing" are different
                 * facts and only one of them is usually true.
                 */
                TextColumn::make('transfer_loss')
                    ->label('Transfer cost')
                    ->state(fn (DealPurchase $r) => $r->transferLossBase()->toFloat())
                    ->money('USD')
                    ->alignEnd()
                    ->color('danger')
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state) => $state > 0 ? '$'.number_format((float) $state, 2) : null)
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => DealPurchase::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'paid' => 'success',
                        'paid_partial' => 'warning',
                        'received' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')->options(DealPurchase::STATUSES)->multiple(),

                SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('bought_at_risk')
                    ->label('Bought before approval')
                    ->query(fn (Builder $q) => $q->where('bought_at_risk', true))
                    ->toggle(),

                Filter::make('unpaid')
                    ->label('Still owed')
                    ->query(fn (Builder $q) => $q->whereNotIn('status', ['paid', 'cancelled']))
                    ->toggle(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePurchases::route('/'),
        ];
    }
}
