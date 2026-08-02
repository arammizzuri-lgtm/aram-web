<?php

namespace App\Filament\Resources\Currencies;

use App\Filament\Actions\RecordDeletion;
use App\Filament\Concerns\KeepsDeletedRecords;
use App\Filament\Resources\Currencies\Pages\ManageCurrencies;
use App\Models\Currency;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class CurrencyResource extends Resource
{
    use KeepsDeletedRecords;

    protected static ?string $model = Currency::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('code')
                ->label('ISO code')
                ->required()
                ->length(3)
                ->alpha()
                ->helperText('Three letters, e.g. USD.')
                ->disabledOn('edit')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->whereNull('deleted_at')),

            TextInput::make('name')->required()->maxLength(64),

            TextInput::make('symbol')->required()->maxLength(8),

            Select::make('symbol_position')
                ->options(['before' => 'Before amount ($100)', 'after' => 'After amount (100 IQD)'])
                ->default('before')
                ->required(),

            TextInput::make('decimal_places')
                ->numeric()
                ->minValue(0)
                ->maxValue(4)
                ->default(2)
                ->required()
                // IQD is quoted in whole dinars; showing ".00" everywhere reads wrong.
                ->helperText('0 for currencies quoted without fractions, such as IQD.'),

            TextInput::make('sort_order')->numeric()->default(0)->required(),

            Toggle::make('is_base')
                ->label('Base currency')
                ->helperText('All costing and reporting arithmetic happens in this currency. Only one may be set.')
                ->disabled(fn (?Currency $record) => $record?->is_base === true),

            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Code')->badge()->color('gray')->sortable(),
                TextColumn::make('name')->weight('medium')->searchable(),
                TextColumn::make('symbol'),
                TextColumn::make('decimal_places')->label('Decimals')->alignEnd(),
                TextColumn::make('is_base')
                    ->label('Base')
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Base' : '—')
                    ->color(fn (bool $state) => $state ? 'primary' : 'gray'),
                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Active' : 'Inactive')
                    ->color(fn (bool $state) => $state ? 'success' : 'danger'),
            ])
            ->defaultSort('sort_order')
            ->filters([RecordDeletion::filter()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCurrencies::route('/'),
        ];
    }
}
