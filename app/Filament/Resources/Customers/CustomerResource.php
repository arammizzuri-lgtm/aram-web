<?php

namespace App\Filament\Resources\Customers;

use App\Filament\Resources\Customers\Pages\ManageCustomers;
use App\Models\Customer;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Company')
                ->columns(2)
                ->schema([
                    TextInput::make('code')->label('Customer code')->required()->unique(ignoreRecord: true)->maxLength(32),
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('name_ar')->label('Arabic name')->maxLength(255),
                    TextInput::make('name_ku')->label('Kurdish name')->maxLength(255),
                    TextInput::make('contact_person')->maxLength(255),
                    TextInput::make('tax_number')->maxLength(64),
                ]),

            Section::make('Contact & location')
                ->columns(2)
                ->schema([
                    TextInput::make('phone')->tel()->maxLength(64),
                    TextInput::make('whatsapp')->label('WhatsApp')->maxLength(64),
                    TextInput::make('email')->email()->maxLength(255),
                    TextInput::make('city')->maxLength(128),
                    TextInput::make('area')->maxLength(128),
                    Textarea::make('billing_address')->rows(2)->columnSpanFull(),
                ]),

            Section::make('Credit & pricing')
                ->description('Wholesale on credit is where importers lose money. Limits are enforced when an order is confirmed.')
                ->columns(3)
                ->schema([
                    Select::make('price_tier_id')
                        ->label('Price tier')
                        ->relationship('priceTier', 'name')
                        ->preload()
                        ->helperText('Sets the default price this customer is quoted.'),

                    TextInput::make('credit_limit')->numeric()->prefix('$')->default(0),
                    TextInput::make('payment_terms_days')->label('Payment terms')->numeric()->suffix('days')->default(30),

                    Select::make('default_currency')
                        ->label('Invoice currency')
                        ->options(['USD' => 'USD', 'IQD' => 'IQD'])
                        ->default('USD')
                        ->required(),

                    Toggle::make('is_blocked')
                        ->label('Blocked')
                        ->helperText('Stops new orders being confirmed.'),

                    Toggle::make('is_active')->default(true),

                    Textarea::make('notes')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->weight('medium')
                    ->description(fn (Customer $record) => trim($record->city.' · '.$record->area, ' ·'))
                    ->searchable(['name', 'name_ar', 'code'])
                    ->sortable(),

                TextColumn::make('priceTier.name')->label('Tier')->badge()->color('gray')->placeholder('—'),

                TextColumn::make('outstanding')
                    ->label('Outstanding')
                    ->state(fn (Customer $record) => $record->outstandingBalance())
                    ->money('USD')
                    ->alignEnd(),

                TextColumn::make('credit_limit')->label('Credit limit')->money('USD')->alignEnd()->sortable(),

                TextColumn::make('credit_used')
                    ->label('Credit used')
                    ->state(fn (Customer $record) => $record->creditUsedPercent().'%')
                    ->alignEnd()
                    ->badge()
                    ->color(fn (Customer $record) => match (true) {
                        $record->creditUsedPercent() >= 100 => 'danger',
                        $record->creditUsedPercent() >= 80 => 'warning',
                        default => 'success',
                    }),

                TextColumn::make('phone')->placeholder('—')->toggleable(),

                TextColumn::make('is_blocked')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Blocked' : 'OK')
                    ->color(fn (bool $state) => $state ? 'danger' : 'success'),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('price_tier_id')->label('Tier')->relationship('priceTier', 'name')->preload(),
                SelectFilter::make('city')
                    ->options(fn () => Customer::query()->whereNotNull('city')->distinct()->pluck('city', 'city')),
                TernaryFilter::make('is_active')->label('Active')->default(true),
            ]);
    }

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'name_ar', 'code', 'phone'];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCustomers::route('/'),
        ];
    }
}
