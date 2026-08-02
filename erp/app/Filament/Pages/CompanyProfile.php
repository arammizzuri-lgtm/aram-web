<?php

namespace App\Filament\Pages;

use App\Models\Company;
use App\Models\Currency;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * The company identity that appears on every invoice, purchase order and report.
 */
class CompanyProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Company Profile';

    protected string $view = 'filament.pages.company-profile';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->company()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Identity')
                    ->description('Shown on invoices, purchase orders and statements.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->label('Trading name')->required()->maxLength(255),
                        TextInput::make('legal_name')->maxLength(255),
                        TextInput::make('tax_number')->label('Tax number')->maxLength(64),
                        TextInput::make('registration_number')->maxLength(64),
                    ]),

                Section::make('Contact')
                    ->columns(2)
                    ->schema([
                        Textarea::make('address')->rows(3)->columnSpanFull(),
                        TextInput::make('city')->maxLength(128),
                        TextInput::make('country')
                            ->label('Country code')
                            ->length(2)
                            ->default('IQ')
                            ->helperText('Two-letter ISO code.'),
                        TextInput::make('phone')->tel()->maxLength(64),
                        TextInput::make('email')->email()->maxLength(255),
                        TextInput::make('website')->url()->maxLength(255),
                    ]),

                Section::make('Financial')
                    ->columns(2)
                    ->schema([
                        Select::make('base_currency')
                            ->label('Base currency')
                            ->options(fn () => Currency::query()->pluck('name', 'code'))
                            ->required()
                            // Changing this after documents exist would restate every
                            // historic cost, so it is deliberately locked once set.
                            ->disabled(fn () => filled($this->company()->base_currency))
                            ->helperText('All costing arithmetic happens in this currency. Set once at setup.'),

                        Select::make('fiscal_year_start_month')
                            ->label('Fiscal year starts')
                            ->options(collect(range(1, 12))->mapWithKeys(
                                fn (int $month) => [$month => date('F', mktime(0, 0, 0, $month, 1))]
                            ))
                            ->default(1)
                            ->required(),
                    ]),
            ]);
    }

    public function save(): void
    {
        $this->company()->update($this->form->getState());

        Notification::make()->title('Company profile saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->submit('save'),
        ];
    }

    private function company(): Company
    {
        return Company::current() ?? Company::create(['name' => config('app.name'), 'base_currency' => 'USD']);
    }
}
