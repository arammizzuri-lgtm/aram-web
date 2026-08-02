<?php

namespace App\Filament\Resources\Customers;

use App\Filament\Actions\RecordDeletion;
use App\Filament\Concerns\KeepsDeletedRecords;
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
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class CustomerResource extends Resource
{
    use KeepsDeletedRecords;

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
                    TextInput::make('code')->label('Customer code')->required()->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->whereNull('deleted_at'))->maxLength(32),
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

            Section::make('Pricing & documents')
                ->description('Set once here so neither is ever a question while building a deal.')
                ->columns(3)
                ->schema([
                    Select::make('customer_type_id')
                        ->label('Customer type')
                        ->relationship('customerType', 'name')
                        ->preload()
                        ->helperText('Decides which selling price this customer is quoted.'),

                    Select::make('document_language')
                        ->label('Documents in')
                        ->options(['en' => 'English', 'ckb' => 'Kurdish (Sorani)'])
                        ->default('en')
                        ->required()
                        // Sorani is Arabic script and reads right to left, so the
                        // document is mirrored rather than translated.
                        ->helperText('Sorani prints right-to-left.'),

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

                TextColumn::make('customerType.name')->label('Type')->badge()->color('gray')->placeholder('—'),

                TextColumn::make('outstanding')
                    ->label('Owes')
                    ->state(fn (Customer $record) => $record->outstandingBalance())
                    ->money('USD')
                    ->alignEnd()
                    ->color(fn (Customer $record) => $record->outstandingBalance() > 0 ? 'warning' : 'gray'),

                /*
                 * Money held that is not yet matched to an invoice.
                 *
                 * Shown beside what they owe rather than netted into it: an
                 * advance taken before the order existed and a debt still
                 * outstanding are different facts, and collapsing them into one
                 * figure hides the one you need to act on.
                 */
                TextColumn::make('credit')
                    ->label('Credit held')
                    ->state(fn (Customer $record) => $record->unallocatedCredit())
                    ->money('USD')
                    ->alignEnd()
                    ->placeholder('—')
                    ->color(fn (Customer $record) => $record->unallocatedCredit() > 0 ? 'success' : 'gray'),

                TextColumn::make('document_language')
                    ->label('Documents')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state) => $state === 'ckb' ? 'Kurdish' : 'English')
                    ->toggleable(),

                TextColumn::make('phone')->placeholder('—')->toggleable(),

                TextColumn::make('is_blocked')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Blocked' : 'OK')
                    ->color(fn (bool $state) => $state ? 'danger' : 'success'),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('customer_type_id')->label('Type')->relationship('customerType', 'name')->preload(),
                SelectFilter::make('city')
                    ->options(fn () => Customer::query()->whereNotNull('city')->distinct()->pluck('city', 'city')),
                TernaryFilter::make('is_active')->label('Active')->default(true),

                RecordDeletion::filter(),
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
