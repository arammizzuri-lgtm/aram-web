<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use App\Support\CostEstimate;
use BackedEnum;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Manage → Cost estimator: the rates behind "What will your house cost?"
 * (/estimate). Every figure the visitor sees comes from here, through
 * App\Support\CostEstimate. Amounts in US dollars.
 */
class CostEstimator extends Page
{
    protected string $view = 'filament.pages.cost-estimator';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = 'Manage';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Cost estimator';

    protected static ?string $title = 'Cost estimator';

    protected ?string $subheading = 'The rates behind "What will your house cost?" — in US dollars. Visitors see ranges, never a quote.';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $values = [];
        foreach (array_keys(CostEstimate::DEFAULTS) as $key) {
            $values[$key] = CostEstimate::get($key);
        }
        $values['enabled'] = CostEstimate::enabled();
        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        $usd = fn (string $name, string $label) => TextInput::make($name)->label($label)
            ->numeric()->minValue(0)->prefix('$')->required();

        $levels = [];
        foreach (CostEstimate::LEVELS as $i => $level) {
            $levels[] = Section::make('Finish '.($i + 1))->compact()->columns(2)->schema([
                TextInput::make($level.'_en')->label('Name (English)')->required()->maxLength(40),
                TextInput::make($level.'_ku')->label('Name (Kurdish)')->maxLength(40)
                    ->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ckb']),
                $usd($level.'_low', 'Building, per m² — from'),
                $usd($level.'_high', 'Building, per m² — to'),
                $usd($level.'_bath', 'Each bathroom')->columnSpanFull()
                    ->helperText('Added once per bathroom, on top of the m² rate.'),
            ]);
        }

        return $schema->statePath('data')->components([
            Section::make('The estimator')
                ->columns(['default' => 1, 'md' => 4])
                ->schema([
                    Toggle::make('enabled')->label('On the site')->default(true)
                        ->helperText('Off: the page, its homepage band and its links disappear.')->columnSpanFull(),
                    TextInput::make('coverage')->label('Plot covered by each floor')->numeric()->minValue(10)->maxValue(100)
                        ->suffix('%')->required()
                        ->helperText('Built area = plot × this × floors, unless the visitor gives their own.'),
                    TextInput::make('max_floors')->label('Most floors offered')->numeric()->minValue(1)->maxValue(12)->required(),
                    TextInput::make('iqd_rate')->label('Dinars to the dollar')->numeric()->minValue(0)->suffix('IQD')->required()
                        ->helperText('For the dinar figure under each range. 0 hides it.'),
                ]),

            Grid::make(['default' => 1, 'lg' => 3])->schema($levels),

            Grid::make(['default' => 1, 'lg' => 2])->schema([
                Section::make('Extras')->columns(2)->schema([
                    $usd('basement_low', 'Basement, per m² — from'),
                    $usd('basement_high', 'Basement, per m² — to'),
                    $usd('pool_low', 'Swimming pool — from'),
                    $usd('pool_high', 'Swimming pool — to'),
                ])->description('A basement is the size of the ground floor.'),

                Section::make('Your design fee')->columns(2)->schema([
                    Radio::make('fee_basis')->label('Worked out as')->options([
                        'percent' => 'A share of the building cost',
                        'per_m2' => 'A rate per m² built (basement included)',
                    ])->required()->live()->columnSpanFull(),
                    TextInput::make('fee_low')->label('From')->numeric()->minValue(0)->required()
                        ->prefix(fn (Get $get) => $get('fee_basis') === 'per_m2' ? '$' : null)
                        ->suffix(fn (Get $get) => $get('fee_basis') === 'per_m2' ? '/ m²' : '%'),
                    TextInput::make('fee_high')->label('To')->numeric()->minValue(0)->required()
                        ->prefix(fn (Get $get) => $get('fee_basis') === 'per_m2' ? '$' : null)
                        ->suffix(fn (Get $get) => $get('fee_basis') === 'per_m2' ? '/ m²' : '%'),
                ]),
            ]),

            Section::make('An example, at the saved rates')
                ->description('600 m² plot, 2 floors, 4 bedrooms, 3 bathrooms, basement — the middle finish. Save to update it.')
                ->schema([
                    TextEntry::make('example')->hiddenLabel()->state(fn () => self::example()),
                ]),
        ]);
    }

    private static function example(): string
    {
        $e = CostEstimate::calculate(CostEstimate::inputs([
            'plot' => 600, 'floors' => 2, 'basement' => true, 'bedrooms' => 4, 'bathrooms' => 3, 'finish' => 'premium',
        ]));

        return number_format($e['built']).' m² built → '.CostEstimate::usdRange($e['construction']).' to build, '
            .CostEstimate::usdRange($e['fee']).' design fee'
            .(($iqd = CostEstimate::iqdRange($e['construction'], $e['iqd_rate'], 'en')) ? ' ('.$iqd.' to build)' : '');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach (array_keys(CostEstimate::DEFAULTS) as $key) {
            $value = $key === 'enabled' ? (! empty($state['enabled']) ? '1' : '0') : trim((string) ($state[$key] ?? ''));
            SiteSetting::put(CostEstimate::PREFIX.$key, $value, 'estimate');
        }
        SiteSetting::flushCache();

        Notification::make()->success()->title('Estimator rates saved')->send();
    }

    /** Look at the page as visitors will. */
    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('view')->label('View the estimator')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                ->url(site_url('/estimate', 'en'), shouldOpenInNewTab: true),
        ];
    }
}
