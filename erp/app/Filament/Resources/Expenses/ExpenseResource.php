<?php

namespace App\Filament\Resources\Expenses;

use App\Filament\Resources\Expenses\Pages\ManageExpenses;
use App\Models\Deal;
use App\Models\Expense;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    Select::make('expense_category_id')
                        ->label('Category')
                        ->relationship('category', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live(),

                    DatePicker::make('expense_date')->label('Date')->default(now())->required(),

                    TextInput::make('description')->required()->maxLength(255)->columnSpanFull(),

                    TextInput::make('amount')->numeric()->prefix('$')->required(),

                    Select::make('currency')
                        ->options(['USD' => 'USD', 'IQD' => 'IQD', 'CNY' => 'CNY'])
                        ->default('USD')
                        ->required(),

                    Select::make('payment_method')
                        ->options([
                            'cash' => 'Cash',
                            'bank_transfer' => 'Bank transfer',
                            'cheque' => 'Cheque',
                            'card' => 'Card',
                        ])
                        ->default('cash'),

                    Select::make('status')
                        ->options(['draft' => 'Draft', 'approved' => 'Approved', 'paid' => 'Paid'])
                        ->default('paid')
                        ->required(),
                ]),

            Section::make('Charge to a deal')
                ->description(
                    'A cost incurred for one customer\'s order belongs on that order, so it '
                    .'shows against the profit it reduced. Leave this empty for general '
                    .'overhead like rent or phone bills.'
                )
                ->schema([
                    Select::make('deal_id')
                        ->label('Deal')
                        ->options(fn () => Deal::query()
                            ->open()
                            ->with('customer')
                            ->orderByDesc('id')
                            ->limit(100)
                            ->get()
                            ->mapWithKeys(fn (Deal $deal) => [
                                $deal->id => $deal->number.' — '.$deal->customer?->name,
                            ]))
                        ->searchable()
                        ->placeholder('General overhead — not tied to a deal'),
                ]),

            Section::make('Reference')
                ->columns(2)
                ->collapsed()
                ->schema([
                    TextInput::make('vendor_name')->label('Paid to')->maxLength(255),
                    TextInput::make('reference')->label('Document reference')->maxLength(255),
                    Textarea::make('notes')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['category', 'deal']))
            ->columns([
                TextColumn::make('expense_date')->label('Date')->date('d M Y')->sortable(),

                TextColumn::make('description')
                    ->weight('medium')
                    ->description(fn (Expense $record) => $record->vendor_name)
                    ->searchable()
                    ->wrap(),

                TextColumn::make('category.name')->label('Category')->badge()->color('gray')->sortable(),

                TextColumn::make('deal.number')
                    ->label('Deal')
                    ->placeholder('Overhead')
                    ->badge()
                    ->color('info')
                    ->url(fn (Expense $record) => $record->deal_id
                        ? url("/admin/deals/{$record->deal_id}")
                        : null),

                TextColumn::make('base_amount')->label('Amount')->money('USD')->alignEnd()->sortable()->summarize(
                    Sum::make()->money('USD')->label('Total')
                ),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'paid' => 'success',
                        'approved' => 'info',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('expense_date', 'desc')
            ->filters([
                SelectFilter::make('expense_category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->multiple()
                    ->preload(),

                SelectFilter::make('status')->options([
                    'draft' => 'Draft', 'approved' => 'Approved', 'paid' => 'Paid',
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageExpenses::route('/'),
        ];
    }
}
