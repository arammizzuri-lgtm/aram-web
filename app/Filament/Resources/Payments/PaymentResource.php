<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Models\Customer;
use App\Models\Payment;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Payment received')
                ->columns(3)
                ->schema([
                    Select::make('customer_id')
                        ->label('Customer')
                        ->options(fn () => Customer::query()->active()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        ->live()
                        ->helperText(fn (?string $state) => static::outstandingNote($state)),

                    DatePicker::make('payment_date')->label('Date')->default(now())->required(),

                    TextInput::make('amount')->numeric()->prefix('$')->required(),

                    Select::make('currency')
                        ->options(['USD' => 'USD', 'IQD' => 'IQD'])
                        ->default('USD')
                        ->required(),

                    Select::make('method')
                        ->options([
                            'cash' => 'Cash',
                            'bank_transfer' => 'Bank transfer',
                            'cheque' => 'Cheque',
                            'card' => 'Card',
                        ])
                        ->default('bank_transfer')
                        ->required(),

                    TextInput::make('reference')->label('Reference')->maxLength(128),

                    Textarea::make('notes')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }

    private static function outstandingNote(?string $customerId): ?string
    {
        $customer = Customer::find($customerId);

        if ($customer === null) {
            return null;
        }

        $outstanding = $customer->outstandingBalance();

        return $outstanding > 0
            ? '$'.number_format($outstanding, 2).' outstanding across '
                .$customer->invoices()->outstanding()->count().' invoices.'
            : 'Nothing outstanding.';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('customer')->withCount('allocations'))
            ->columns([
                TextColumn::make('number')->label('Payment')->weight('medium')->searchable()->sortable(),
                TextColumn::make('customer.name')->label('Customer')->searchable()->sortable(),
                TextColumn::make('payment_date')->label('Date')->date('d M Y')->sortable(),

                TextColumn::make('method')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state) => str($state)->replace('_', ' ')->ucfirst()),

                TextColumn::make('amount')
                    ->money(fn (Payment $record) => $record->currency ?? 'USD')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('allocations_count')->label('Invoices')->alignEnd()->badge()->color('gray'),

                // Money received but not yet applied is customer credit, and it
                // must be visible or it quietly goes missing.
                TextColumn::make('unallocated')
                    ->label('Unallocated')
                    ->state(fn (Payment $record) => $record->unallocated())
                    ->money(fn (Payment $record) => $record->currency ?? 'USD')
                    ->alignEnd()
                    ->badge()
                    ->color(fn (Payment $record) => $record->unallocated() > 0.005 ? 'warning' : 'success'),
            ])
            ->defaultSort('payment_date', 'desc')
            ->filters([
                SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('method')->options([
                    'cash' => 'Cash', 'bank_transfer' => 'Bank transfer',
                    'cheque' => 'Cheque', 'card' => 'Card',
                ]),

                Filter::make('unallocated')
                    ->label('Has unallocated money')
                    ->query(fn (Builder $query) => $query->whereRaw('unallocated_amount > 0.005'))
                    ->toggle(),
            ]);
    }

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['number', 'reference'];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePayments::route('/'),
        ];
    }
}
