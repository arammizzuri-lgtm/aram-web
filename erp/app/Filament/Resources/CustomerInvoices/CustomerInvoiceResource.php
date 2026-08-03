<?php

namespace App\Filament\Resources\CustomerInvoices;

use App\Filament\Actions\RecordDeletion;
use App\Filament\Concerns\KeepsDeletedRecords;
use App\Filament\Resources\CustomerInvoices\Pages\ManageCustomerInvoices;
use App\Models\CustomerInvoice;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * What the customer receives — and, unlike every other screen here, nothing on
 * it is hidden from the assistant. Selling prices are theirs to work with.
 *
 * Invoices are not editable. They are copies taken at issue, and a document
 * already handed to someone must not change: corrections happen by cancelling
 * and re-issuing, which leaves a trail instead of a silent edit.
 */
class CustomerInvoiceResource extends Resource
{
    use KeepsDeletedRecords;

    protected static ?string $model = CustomerInvoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Invoices';

    protected static ?string $recordTitleAttribute = 'number';

    /** Invoices come from deals. One created standing alone bills nothing real. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->description('The items and totals are fixed. Only these details can still change.')
                ->columns(3)
                ->schema([
                    DatePicker::make('due_date')->label('Due'),

                    Select::make('language')
                        ->label('Print in')
                        ->options(['en' => 'English', 'ckb' => 'Kurdish (Sorani)'])
                        ->required()
                        ->helperText('Taken from the customer.'),

                    Textarea::make('notes')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['customer', 'deal', 'allocations']))
            ->emptyStateHeading('Nothing billed yet')
            ->emptyStateDescription(
                'Invoices are issued from a deal, not created here — open the deal and use "Invoice goods" once the prices are settled.'
            )
            ->emptyStateIcon('heroicon-o-document-currency-dollar')
            ->columns([
                TextColumn::make('number')->label('Invoice')->weight('medium')->searchable()->sortable(),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => CustomerInvoice::TYPES[$state] ?? $state)
                    ->color(fn (string $state) => $state === 'shipping' ? 'info' : 'gray'),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->description(fn (CustomerInvoice $r) => $r->deal?->number)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('invoice_date')->label('Date')->date('d M Y')->sortable(),

                TextColumn::make('total')
                    ->label('Total')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state, CustomerInvoice $r) => number_format(
                        (float) $state, $r->currency === 'IQD' ? 0 : 2
                    ).' '.$r->currency),

                TextColumn::make('paid')
                    ->label('Paid')
                    ->state(fn (CustomerInvoice $r) => $r->paidBase()->toFloat())
                    ->money('USD')
                    ->alignEnd(),

                TextColumn::make('outstanding')
                    ->label('Still due')
                    ->state(fn (CustomerInvoice $r) => $r->outstandingBase()->toFloat())
                    ->money('USD')
                    ->alignEnd()
                    ->color(fn (CustomerInvoice $r) => $r->isPaid() ? 'gray' : 'warning'),

                TextColumn::make('language')
                    ->label('Printed in')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state) => $state === 'ckb' ? 'Kurdish' : 'English')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'paid' => 'success',
                        'issued' => 'info',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('invoice_date', 'desc')
            ->filters([
                SelectFilter::make('type')->options(CustomerInvoice::TYPES),

                SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('unpaid')
                    ->label('Still owed')
                    // `$query` by name — see the note in PurchaseResource. As `$q`
                    // this threw on a model-less builder the moment it was switched on.
                    ->query(fn (Builder $query) => $query->outstanding())
                    ->toggle(),

                RecordDeletion::filter(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCustomerInvoices::route('/'),
        ];
    }

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['number', 'customer.name'];
    }
}
