<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use App\Support\SiteText;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * Site Content editor — one tab per public-site section. Every bilingual
 * string on the site is registered in App\Support\SiteText and its English +
 * Kurdish fields are generated here automatically, so nothing needs wiring by
 * hand. A handful of non-text objects (metrics, images, contact numbers) and
 * the add/remove social-links repeater are appended to the relevant tabs.
 */
class ManageSiteContent extends Page
{
    protected string $view = 'filament.pages.manage-site-content';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Manage';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Site Content';

    protected static ?string $title = 'Site Content';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $state = [];
        foreach (SiteText::map() as $key => $meta) {
            $state["{$key}_en"] = SiteText::en($key);
            $state["{$key}_ku"] = SiteText::ku($key);
        }

        // Non-text singles come straight from the settings table; the social
        // repeater is filled from the normalised socials() helper.
        $this->form->fill(array_merge(
            SiteSetting::allKeyed(),
            $state,
            ['footer_socials' => socials()],
        ));
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        $tabs = [];

        foreach (SiteText::tabs() as $tabName => $entries) {
            $fields = [];
            foreach ($entries as $key => $def) {
                $label = $def[0];
                $type = $def[3] ?? 'text';
                $fields[] = $this->biField("{$key}_en", "{$label} — EN", $type, false);
                $fields[] = $this->biField("{$key}_ku", "{$label} — KU", $type, true);
            }

            foreach ($this->extrasFor($tabName) as $extra) {
                $fields[] = $extra;
            }

            $tabs[] = Tab::make($tabName)->schema([
                Grid::make(2)->schema($fields),
            ]);
        }

        // A dedicated home for the numeric metrics and images (not text).
        $tabs[] = Tab::make('Numbers & Media')->icon(Heroicon::OutlinedPhoto)->schema([
            Grid::make(2)->schema([
                $this->text('about_stat1_num', 'Metric 1 — number'),
                $this->text('about_stat1_label', 'Metric 1 — label'),
                $this->text('about_stat2_num', 'Metric 2 — number (also hero "Clients")'),
                $this->text('about_stat2_label', 'Metric 2 — label'),
                $this->text('about_stat3_num', 'Metric 3 — number'),
                $this->text('about_stat3_label', 'Metric 3 — label'),
                $this->text('about_stat4_num', 'Metric 4 — number'),
                $this->text('about_stat4_label', 'Metric 4 — label'),
                $this->text('about_area_num', 'Total area — number (digits only)'),
                $this->text('about_area_unit', 'Total area — unit (e.g. m²)'),
                $this->text('about_area_label', 'Total area — caption'),
                $this->area('about_area_note', 'Total area — note'),
                $this->text('about_portrait_img', 'Portrait image URL', true),
            ]),
        ]);

        return $schema->components([
            Tabs::make('Sections')->persistTabInQueryString()->columnSpanFull()->tabs($tabs),
        ]);
    }

    /** Extra non-text fields grafted onto a given text tab. */
    private function extrasFor(string $tab): array
    {
        return match ($tab) {
            'Contact' => [
                $this->text('contact_email_new', 'Email address (value)', true),
                $this->text('contact_phone', 'Phone 01 — number (value)'),
                $this->text('contact_phone2', 'Phone 02 — number (value)'),
            ],
            'Footer' => [
                $this->text('footer_kurdish', 'Kurdish signature', true, true),
                $this->area('footer_copy', 'Copyright (one line per row)', true),
                Repeater::make('footer_socials')
                    ->label('Social profiles — add / remove / reorder')
                    ->schema([
                        TextInput::make('label')->label('Label')->required()
                            ->placeholder('e.g. Facebook')->maxLength(60),
                        TextInput::make('url')->label('Profile URL')
                            ->placeholder('https://facebook.com/yourstudio')->maxLength(2000),
                    ])
                    ->columns(2)
                    ->reorderable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                    ->addActionLabel('Add social profile')
                    ->default([])
                    ->columnSpanFull(),
            ],
            default => [],
        };
    }

    public function save(): void
    {
        DB::transaction(function () {
            foreach ($this->form->getState() as $key => $value) {
                if ($key === 'footer_socials') {
                    SiteSetting::updateOrCreate(
                        ['key' => 'footer_socials'],
                        ['value' => $this->encodeSocials($value), 'group' => 'footer'],
                    );

                    continue;
                }

                SiteSetting::updateOrCreate(['key' => $key], ['value' => (string) ($value ?? '')]);
            }
        });

        SiteSetting::flushCache();

        Notification::make()->success()->title('Site content updated')->send();
    }

    /** Normalise repeater rows to a compact JSON array, dropping blank labels. */
    private function encodeSocials(mixed $value): string
    {
        $rows = collect(is_array($value) ? $value : [])
            ->map(fn ($r) => [
                'label' => trim((string) ($r['label'] ?? '')),
                'url' => trim((string) ($r['url'] ?? '')),
            ])
            ->filter(fn ($r) => $r['label'] !== '')
            ->values()
            ->all();

        return json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /* ---- field builders ---- */

    private function biField(string $key, string $label, string $type, bool $rtl): TextInput|Textarea
    {
        $field = $type === 'area'
            ? Textarea::make($key)->label($label)->rows(3)
            : TextInput::make($key)->label($label)->maxLength(3000);

        if ($rtl) {
            $field->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ckb']);
        }

        return $field;
    }

    private function text(string $key, string $label, bool $full = false, bool $rtl = false): TextInput
    {
        $field = TextInput::make($key)->label($label)->maxLength(2000);
        if ($full) {
            $field->columnSpanFull();
        }
        if ($rtl) {
            $field->extraInputAttributes(['dir' => 'rtl']);
        }

        return $field;
    }

    private function area(string $key, string $label, bool $full = false): Textarea
    {
        $field = Textarea::make($key)->label($label)->rows(3);
        if ($full) {
            $field->columnSpanFull();
        }

        return $field;
    }
}
