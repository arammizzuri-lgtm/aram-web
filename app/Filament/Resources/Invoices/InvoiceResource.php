<?php

namespace App\Filament\Resources\Invoices;

use App\Actions\Sales\GenerateInvoicePdf;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Models\Invoice;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('customer'))
            ->columns([
                TextColumn::make('number')
                    ->label('Invoice')
                    ->weight('medium')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('invoice_date')->label('Issued')->date('d M Y')->sortable(),

                TextColumn::make('due_date')
                    ->label('Due')
                    ->date('d M Y')
                    ->description(fn (Invoice $record) => $record->isOverdue()
                        ? $record->daysOverdue().' days overdue'
                        : null)
                    ->color(fn (Invoice $record) => $record->isOverdue() ? 'danger' : null)
                    ->sortable(),

                TextColumn::make('total')->money('USD')->alignEnd()->sortable(),

                TextColumn::make('amount_paid')->label('Paid')->money('USD')->alignEnd(),

                TextColumn::make('due')
                    ->label('Outstanding')
                    ->state(fn (Invoice $record) => $record->amountDue())
                    ->money('USD')
                    ->alignEnd()
                    ->weight('medium'),

                // Real margin, from the landed cost frozen onto each line when the
                // invoice was posted — not a guess against the supplier price.
                TextColumn::make('margin_percent')
                    ->label('Margin')
                    ->formatStateUsing(fn (string $state) => number_format((float) $state, 1).'%')
                    ->alignEnd()
                    ->badge()
                    ->color(fn (string $state) => match (true) {
                        (float) $state <= 0 => 'danger',
                        (float) $state < 20 => 'warning',
                        default => 'success',
                    })
                    ->visible(fn () => auth()->user()?->can('view_cost')),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => str($state)->replace('_', ' ')->ucfirst())
                    ->color(fn (string $state) => match ($state) {
                        'paid' => 'success',
                        'partially_paid' => 'warning',
                        'cancelled' => 'gray',
                        default => 'info',
                    }),
            ])
            ->defaultSort('invoice_date', 'desc')
            ->recordActions([
                Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('gray')
                    ->action(function (Invoice $record) {
                        try {
                            return app(GenerateInvoicePdf::class)->download($record);
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Could not generate the PDF')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return null;
                        }
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'posted' => 'Posted',
                    'partially_paid' => 'Partially paid',
                    'paid' => 'Paid',
                    'cancelled' => 'Cancelled',
                ])->multiple(),

                SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('overdue')
                    ->label('Overdue only')
                    ->query(fn (Builder $query) => $query->overdue())
                    ->toggle(),

                Filter::make('outstanding')
                    ->label('Unpaid only')
                    ->query(fn (Builder $query) => $query->outstanding())
                    ->toggle(),
            ]);
    }

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['number'];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
        ];
    }
}
