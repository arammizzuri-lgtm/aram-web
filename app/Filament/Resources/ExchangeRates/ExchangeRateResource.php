<?php

namespace App\Filament\Resources\ExchangeRates;

use App\Filament\Resources\ExchangeRates\Pages\ManageExchangeRates;
use App\Models\Currency;
use App\Models\ExchangeRate;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Rates are kept as a dated history, never overwritten.
 *
 * A document freezes the rate in force on its own date, so editing today's rate
 * must not disturb what last quarter's purchase orders were costed at.
 */
class ExchangeRateResource extends Resource
{
    protected static ?string $model = ExchangeRate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 21;

    protected static ?string $navigationLabel = 'Exchange Rates';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('from_currency')
                ->label('From')
                ->options(fn () => Currency::query()->pluck('name', 'code'))
                ->default(fn () => Currency::base())
                ->required()
                ->searchable(),

            Select::make('to_currency')
                ->label('To')
                ->options(fn () => Currency::query()->pluck('name', 'code'))
                ->required()
                ->searchable()
                ->different('from_currency'),

            TextInput::make('rate')
                ->label('Rate')
                ->numeric()
                ->required()
                ->step('0.00000001')
                ->helperText('One unit of "from" expressed in "to". Eight decimals are kept.'),

            DatePicker::make('effective_date')
                ->label('In force from')
                ->default(today())
                ->required()
                ->helperText('Applies to documents dated on or after this day, until a newer rate exists.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('effective_date')->label('In force from')->date()->sortable(),
                TextColumn::make('from_currency')->label('From')->badge()->color('gray'),
                TextColumn::make('to_currency')->label('To')->badge()->color('gray'),
                TextColumn::make('rate')
                    ->alignEnd()
                    ->formatStateUsing(fn (string $state) => rtrim(rtrim($state, '0'), '.'))
                    ->extraCellAttributes(['class' => 'erp-numeric']),
                TextColumn::make('source')->badge()->color('gray')->toggleable(),
                TextColumn::make('createdBy.name')->label('Added by')->placeholder('—')->toggleable(),
            ])
            ->defaultSort('effective_date', 'desc')
            ->filters([
                SelectFilter::make('to_currency')
                    ->label('To currency')
                    ->options(fn () => Currency::query()->pluck('name', 'code')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageExchangeRates::route('/'),
        ];
    }
}
