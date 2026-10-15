<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use App\Models\Testimonial;
use App\Support\ReadAloud;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use RuntimeException;
use UnitEnum;

/**
 * "Read aloud" on client quotes that have no voice note: whether it is
 * offered, and the Kurdish voice that reads them (App\Support\ReadAloud).
 * "Hear this voice" answers the first question about a voice — does it
 * sound right? — before every quote is read in it.
 */
class ReadAloudSettings extends Page
{
    protected string $view = 'filament.pages.read-aloud';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSpeakerWave;

    protected static string|UnitEnum|null $navigationGroup = 'Manage';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Read aloud';

    protected static ?string $title = 'Read aloud';

    protected static ?string $slug = 'read-aloud';

    /** A sentence to try a voice on: "Hello — this is how our clients' words will be read on the website." */
    private const SAMPLE = 'سڵاو، ئەمە ئەو دەنگەیە کە وتەی کڕیارەکانمان لە ماڵپەڕەکەدا پێ دەخوێنرێتەوە.';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'enabled' => ReadAloud::enabled(),
            'api_key' => '',
            'speaker' => ReadAloud::speaker(),
            'speed' => (string) ReadAloud::speed(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('On the website')
                ->description('A quote without a voice note gets a “Read aloud” button, marked “computer voice” so that no one takes it for the client. The portrait still draws itself while it is read. English is read by the visitor’s own phone or computer — free, with nothing to set up.')
                ->schema([
                    Toggle::make('enabled')->label('Offer “Read aloud” on quotes without a voice note')->default(true),
                ]),

            Section::make('The Kurdish voice')
                ->description(new HtmlString('Phones almost never have a Kurdish voice, so Kurdish quotes are read by <a href="https://www.kurdishtts.com/pricing" target="_blank" rel="noopener" style="text-decoration:underline">kurdishtts.com</a> (Sorani). Each quote is read once, when it is saved, and the recording is kept: a quote costs its length in characters once, not every time it is played. Their free plan is for testing only — readings published on the website need a paid plan (Creator or above), and their terms let you keep what was made while the plan was active.'))
                ->columns(2)
                ->schema([
                    TextInput::make('api_key')->label('API key (TTS)')
                        ->password()->revealable()
                        ->placeholder(ReadAloud::hasKey() ? '•••••••• saved — leave empty to keep' : 'From kurdishtts.com → Settings → API')
                        ->helperText('Stored encrypted. Never sent to the website’s visitors.')
                        ->columnSpanFull(),
                    Select::make('speaker')->label('Voice')
                        ->options(fn () => ReadAloud::speakers() ?: [ReadAloud::speaker() => ReadAloud::speaker()])
                        ->searchable()->native(false)->selectablePlaceholder(false)
                        ->helperText('453 Sorani voices — women first. Try one with “Hear this voice” after saving.'),
                    Select::make('speed')->label('Speed')
                        ->options(['0.85' => 'Slower', '0.95' => 'A little slower', '1' => 'Normal', '1.1' => 'A little faster'])
                        ->native(false)->selectablePlaceholder(false)->default('1'),
                ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        SiteSetting::put(ReadAloud::ENABLED, ! empty($state['enabled']) ? '1' : '0', 'reader');
        SiteSetting::put(ReadAloud::SPEAKER, (string) ($state['speaker'] ?? ReadAloud::DEFAULT_SPEAKER), 'reader');
        SiteSetting::put(ReadAloud::SPEED, (string) ($state['speed'] ?? '1'), 'reader');
        if (filled($state['api_key'] ?? null)) {
            ReadAloud::storeKey($state['api_key']);
        }
        SiteSetting::flushCache();

        $this->form->fill(array_merge($state, ['api_key' => '']));
        Notification::make()->success()->title('Read-aloud settings saved')
            ->body(ReadAloud::kurdishReady() ? 'Use “Read the Kurdish quotes now” for quotes saved before, or after changing the voice.' : null)
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('hear')
                ->label('Hear this voice')
                ->icon(Heroicon::OutlinedPlay)
                ->color('gray')
                ->modalHeading('The Kurdish voice')
                ->modalDescription('The saved voice and speed (save first if you have changed them), reading one sentence.')
                ->modalContent(fn () => $this->sample())
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close'),

            Action::make('readAll')
                ->label('Read the Kurdish quotes now')
                ->icon(Heroicon::OutlinedArrowPath)
                ->requiresConfirmation()
                ->modalDescription('Every Kurdish quote without a voice note of its own is read in the saved voice — those already read in it are left as they are.')
                ->action(function () {
                    $made = 0;
                    $errors = [];
                    Testimonial::query()->whereNotNull('quote_ku')->where('quote_ku', '!=', '')->get()
                        ->each(function (Testimonial $t) use (&$made, &$errors) {
                            $before = $t->reading_ku;
                            $error = $t->refreshReading();
                            if ($error !== null) {
                                $errors[] = $error;
                            } elseif ($t->reading_ku && $t->reading_ku !== $before) {
                                $made++;
                            }
                        });

                    $errors === []
                        ? Notification::make()->success()->title($made === 0 ? 'Every Kurdish quote was already read' : "Read {$made} Kurdish ".str('quote')->plural($made))->send()
                        : Notification::make()->danger()->title(count($errors).' could not be read'.($made ? " ({$made} were)" : ''))
                            ->body($errors[0])->persistent()->send();
                }),
        ];
    }

    /** One sentence read in the saved voice, to listen to here — or why it could not be. */
    private function sample(): HtmlString
    {
        try {
            $audio = ReadAloud::synthesize(self::SAMPLE);
        } catch (RuntimeException $e) {
            return new HtmlString('<p style="color:rgb(220,38,38)">'.e($e->getMessage()).'</p>');
        }

        return new HtmlString('<audio controls autoplay style="width:100%" src="data:audio/mpeg;base64,'.base64_encode($audio).'"></audio>'
            .'<p dir="rtl" style="margin-top:10px;opacity:.75">'.e(self::SAMPLE).'</p>');
    }
}
