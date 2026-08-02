<?php

namespace App\Filament\Resources\Suppliers;

use App\Filament\Actions\RecordDeletion;
use App\Filament\Concerns\KeepsDeletedRecords;
use App\Filament\Resources\Suppliers\Pages\ManageSuppliers;
use App\Models\Supplier;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class SupplierResource extends Resource
{
    use KeepsDeletedRecords;

    protected static ?string $model = Supplier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Purchasing';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Company')
                ->columns(2)
                ->schema([
                    TextInput::make('code')->label('Supplier code')->required()->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->whereNull('deleted_at'))->maxLength(32),
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('name_zh')->label('Chinese name 中文名')->maxLength(255),
                    TextInput::make('contact_person')->maxLength(255),
                    TextInput::make('city')->maxLength(128),
                    TextInput::make('country')->length(2)->default('CN'),
                ]),

            Section::make('Contact')
                ->description('WeChat is usually how these conversations actually happen.')
                ->columns(2)
                ->schema([
                    TextInput::make('phone')->tel()->maxLength(64),
                    TextInput::make('whatsapp')->label('WhatsApp')->maxLength(64),
                    TextInput::make('wechat_id')->label('WeChat ID')->maxLength(64),
                    TextInput::make('email')->email()->maxLength(255),
                    TextInput::make('website')->url()->maxLength(255),
                    Textarea::make('address')->rows(2)->columnSpanFull(),
                ]),

            Section::make('Trading terms')
                ->columns(3)
                ->schema([
                    Select::make('default_currency')
                        ->label('Currency')
                        ->options(['USD' => 'USD', 'CNY' => 'CNY', 'IQD' => 'IQD'])
                        ->default('USD')
                        ->required(),

                    Select::make('default_incoterm')
                        ->label('Incoterm')
                        ->options([
                            'EXW' => 'EXW — Ex Works',
                            'FOB' => 'FOB — Free On Board',
                            'CIF' => 'CIF — Cost, Insurance & Freight',
                            'DDP' => 'DDP — Delivered Duty Paid',
                        ])
                        ->default('FOB')
                        ->required()
                        ->helperText('Determines which shipping costs you get billed for.'),

                    TextInput::make('port_of_loading')->maxLength(128),
                    TextInput::make('deposit_percent')->label('Typical deposit')->numeric()->suffix('%')->default(30),
                    TextInput::make('payment_terms_days')->label('Payment terms')->numeric()->suffix('days')->default(30),
                    TextInput::make('average_lead_time_days')->label('Lead time')->numeric()->suffix('days'),
                    TextInput::make('rating')->numeric()->minValue(1)->maxValue(5)->helperText('1–5'),
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
                    ->description(fn (Supplier $record) => $record->name_zh ?: $record->code)
                    ->searchable(['name', 'name_zh', 'code'])
                    ->sortable(),

                TextColumn::make('city')
                    ->label('Location')
                    ->formatStateUsing(fn (?string $state, Supplier $record) => trim(($state ?? '').' '.$record->country))
                    ->toggleable(),

                TextColumn::make('supplier_products_count')
                    ->label('Products')
                    ->counts('supplierProducts')
                    ->alignEnd()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('default_incoterm')->label('Incoterm')->badge()->color('gray'),

                TextColumn::make('average_lead_time_days')
                    ->label('Lead time')
                    ->formatStateUsing(fn (?int $state) => $state ? "{$state} days" : '—')
                    ->alignEnd(),

                TextColumn::make('outstanding')
                    ->label('Outstanding')
                    ->state(fn (Supplier $record) => $record->outstandingBalance())
                    ->money('USD')
                    ->alignEnd()
                    ->visible(fn () => auth()->user()?->can('view_cost')),

                TextColumn::make('wechat_id')
                    ->label('WeChat')
                    ->copyable()
                    ->copyMessage('WeChat ID copied')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('is_active')->label('Active')->default(true),

                RecordDeletion::filter(),
            ]);
    }

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'name_zh', 'code'];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSuppliers::route('/'),
        ];
    }
}
