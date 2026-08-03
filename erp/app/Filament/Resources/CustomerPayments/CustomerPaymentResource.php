<?php

namespace App\Filament\Resources\CustomerPayments;

use App\Filament\Actions\RecordDeletion;
use App\Filament\Concerns\KeepsDeletedRecords;
use App\Filament\Resources\CustomerPayments\Pages\ManageCustomerPayments;
use App\Filament\Resources\Customers\CustomerResource;
use App\Models\CustomerPayment;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Money in from customers.
 *
 * Recording a payment asks only what actually happened — who paid, how much,
 * in what. What it is *for* is a separate question, answered afterwards and
 * never blocking the money being safely on record.
 */
class CustomerPaymentResource extends Resource
{
    use KeepsDeletedRecords;

    protected static ?string $model = CustomerPayment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Payments';

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->description('Record it now; decide what it pays for afterwards.')
                ->columns(3)
                ->schema([
                    Select::make('customer_id')
                        ->label('From')
                        ->relationship('customer', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpan(2),

                    Select::make('direction')
                        ->label('Direction')
                        ->options(['in' => 'Money in', 'refund' => 'Refund out'])
                        ->default('in')
                        ->required(),

                    TextInput::make('amount')->numeric()->required(),

                    Select::make('currency')
                        ->options(['IQD' => 'IQD', 'USD' => 'USD'])
                        ->default('IQD')
                        ->required()
                        ->live(),

                    TextInput::make('exchange_rate')
                        ->label('IQD per 1 USD')
                        ->numeric()
                        ->step('0.000001')
                        // Asked for on the payment rather than taken from the deal:
                        // money can land weeks later, at a rate the deal never saw.
                        ->helperText('The rate on the day it arrived.')
                        ->visible(fn (Get $get) => $get('currency') === 'IQD')
                        ->required(fn (Get $get) => $get('currency') === 'IQD'),

                    Select::make('method')
                        ->options([
                            'cash' => 'Cash',
                            'transfer' => 'Bank transfer',
                            'exchange' => 'Exchange office',
                        ])
                        ->default('cash'),

                    DatePicker::make('paid_at')->label('Date')->default(now())->required(),

                    TextInput::make('reference')->maxLength(255),

                    Textarea::make('notes')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['customer', 'allocations.invoice']))
            ->emptyStateHeading('No payments recorded')
            ->emptyStateDescription(
                'Money in from customers. It lands on their account first and is matched to invoices afterwards, so record it the moment it arrives.'
            )
            ->emptyStateIcon('heroicon-o-banknotes')
            ->columns([
                TextColumn::make('number')->label('Receipt')->weight('medium')->searchable(),

                /*
                 * A payment always belongs to a conversation with somebody, and
                 * the account page is where that conversation lives — so the
                 * name is the way there rather than a label.
                 */
                TextColumn::make('customer.name')
                    ->label('From')
                    ->searchable()
                    ->sortable()
                    ->color('primary')
                    ->url(fn (CustomerPayment $r) => $r->customer
                        ? CustomerResource::getUrl('account', ['record' => $r->customer_id])
                        : null),

                TextColumn::make('paid_at')->label('Date')->date('d M Y')->sortable(),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state, CustomerPayment $r) => number_format(
                        (float) $state, $r->currency === 'IQD' ? 0 : 2
                    ).' '.$r->currency)
                    ->color(fn (CustomerPayment $r) => $r->isRefund() ? 'danger' : null),

                TextColumn::make('base_amount')->label('In USD')->money('USD')->alignEnd()->sortable(),

                /*
                 * What is not yet pointed at an invoice.
                 *
                 * Shown as its own column rather than folded into a status,
                 * because credit sitting on an account is a fact you act on —
                 * it is the answer to "can I put this against the new order?"
                 */
                TextColumn::make('unallocated')
                    ->label('Credit left')
                    ->state(fn (CustomerPayment $r) => $r->unallocatedBase()->toFloat())
                    ->money('USD')
                    ->alignEnd()
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state) => $state > 0 ? '$'.number_format((float) $state, 2) : null)
                    // It is not a problem to be fixed — it goes onto their next
                    // invoice on its own — so it is stated, not flagged.
                    ->description(fn ($state) => $state > 0 ? 'goes to their next invoice' : null)
                    ->color('info'),

                TextColumn::make('allocations.invoice.number')
                    ->label('Matched to')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Not yet matched')
                    ->toggleable(),

                TextColumn::make('method')->badge()->color('gray')->toggleable(),
            ])
            ->defaultSort('paid_at', 'desc')
            ->filters([
                SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('direction')->options(['in' => 'Money in', 'refund' => 'Refunds']),

                Filter::make('unmatched')
                    ->label('Still unmatched')
                    // `$query` by name — see the note in PurchaseResource.
                    ->query(fn (Builder $query) => $query->whereRaw(
                        'base_amount > (select coalesce(sum(base_amount), 0) from customer_payment_allocations '
                        .'where customer_payment_allocations.customer_payment_id = customer_payments.id)'
                    ))
                    ->toggle(),

                RecordDeletion::filter(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCustomerPayments::route('/'),
        ];
    }
}
