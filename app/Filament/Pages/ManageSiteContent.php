<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use App\Support\SiteText;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\HasUnsavedDataChangesAlert;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Site Content editor — one section of the public site at a time. Every bilingual
 * string on the site is registered in App\Support\SiteText and its English +
 * Kurdish fields are generated here automatically, so nothing needs wiring by
 * hand. A handful of non-text objects (metrics, images, contact numbers) and
 * the add/remove social-links repeater are appended to the relevant tabs.
 *
 * One section is built at a time (?section=…, the links across the top of
 * the page). Built all at once, the three languages came to some 1,250
 * fields and a 3 MB page that took longer to make than shared hosting
 * allows a request — the page simply failed. Saving stores only the section
 * on screen, so the others are never touched.
 */
class ManageSiteContent extends Page
{
    use HasUnsavedDataChangesAlert;   // leaving with changes not saved asks first

    protected string $view = 'filament.pages.manage-site-content';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Website settings';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Site content';

    protected static ?string $title = 'Site Content';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** The section being edited — its slug, kept in the address (?section=). */
    #[Url]
    public string $section = '';

    /** What the search box is looking for, in any section and any language (?find=). */
    #[Url]
    public string $find = '';

    /** Listing the texts still waiting for their Kurdish or Arabic (?missing=1). */
    #[Url]
    public bool $missing = false;

    public const MEDIA = 'Numbers & Media';

    public const GOOGLE = 'Google';

    /**
     * The sections by the part of the site they belong to, so the row of
     * links stays short. A section added later and not placed here shows
     * under "Other" until it is.
     */
    private const GROUPS = [
        'Everywhere' => ['Navigation & UI', 'Search & sharing', self::GOOGLE, 'Footer', 'Saved & sharing', 'Page not found', 'Loading & waiting'],
        'Home page' => ['Hero', 'The Record (stats band)', 'Map', 'Clients', 'Client quotes', 'Projects gallery', 'Services', 'Process', 'Heritage', 'About', 'Contact', self::MEDIA],
        'Projects' => ['Projects page', 'Project page', 'Project overlay', 'Rooms', 'Journal'],
        'For clients' => ['Client rooms', 'Client review page', 'Cost estimate', 'Sketch', 'Site boards', 'Holiday cards'],
        'Heritage' => ['Kurdistan heritage'],
        'Studio' => ['Architect page', 'Careers', 'Partners'],
    ];

    /** slug => section name, in the order they appear on the site */
    public static function sections(): array
    {
        return collect([...array_keys(SiteText::tabs()), self::GOOGLE, self::MEDIA])
            ->mapWithKeys(fn ($name) => [Str::slug($name) => $name])->all();
    }

    /** @return array<string, array<string, string>> group => [slug => section name] */
    public static function groups(): array
    {
        $sections = self::sections();
        $groups = [];
        foreach (self::GROUPS as $group => $names) {
            $groups[$group] = array_intersect($sections, $names);
        }
        $placed = array_merge(...array_values(self::GROUPS));
        $groups['Other'] = array_diff($sections, $placed);

        return array_filter($groups);
    }

    /** The group the section on screen belongs to. */
    public function currentGroup(): string
    {
        foreach (self::groups() as $group => $sections) {
            if (isset($sections[$this->section])) {
                return $group;
            }
        }

        return array_key_first(self::groups());
    }

    /**
     * The texts the search box matches (by their name or their words in any
     * language), or those missing a translation — each linking straight to
     * its field. Null when neither is asked for.
     *
     * @return list<array{section: string, label: string, url: string, values: array<string, string>, missing: list<string>}>|null
     */
    public function results(): ?array
    {
        $find = trim($this->find);
        if (! $this->missing && mb_strlen($find) < 2) {
            return null;
        }

        $untranslated = SiteText::untranslated();
        $out = [];
        foreach (SiteText::tabs() as $tab => $entries) {
            foreach ($entries as $key => $def) {
                if ($this->missing && ! isset($untranslated[$key])) {
                    continue;
                }
                $values = ['EN' => SiteText::en($key), 'KU' => SiteText::ku($key), 'AR' => SiteText::ar($key)];
                if (! $this->missing && ! collect([$def[0], $key, ...array_values($values)])->contains(fn ($text) => mb_stripos($text, $find) !== false)) {
                    continue;
                }
                $out[] = [
                    'section' => $tab,
                    'label' => $def[0],
                    'url' => static::getUrl(['section' => Str::slug($tab)]).'#t-'.$key,
                    'values' => $values,
                    'missing' => $untranslated[$key] ?? [],
                ];
                if (count($out) === 60) {
                    return $out;   // enough to see the search wants narrowing
                }
            }
        }

        return $out;
    }

    /** The name of the section on screen. */
    public function currentSection(): string
    {
        return self::sections()[$this->section] ?? array_values(self::sections())[0];
    }

    public function mount(): void
    {
        if (! isset(self::sections()[$this->section])) {
            $this->section = array_key_first(self::sections());
        }

        // the section's own strings only — in English, Kurdish and Arabic
        $state = [];
        foreach (SiteText::tabs()[$this->currentSection()] ?? [] as $key => $def) {
            $state["{$key}_en"] = SiteText::en($key);
            $state["{$key}_ku"] = SiteText::ku($key);
            $state["{$key}_ar"] = SiteText::ar($key);
        }

        // Non-text singles come straight from the settings table; the social
        // repeater is filled from the normalised socials() helper.
        $this->form->fill(array_merge(
            SiteSetting::allKeyed(),
            $state,
            $this->currentSection() === 'Footer' ? ['footer_socials' => socials()] : [],
        ));
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        $current = $this->currentSection();
        $tabs = [];

        foreach (SiteText::tabs() as $tabName => $entries) {
            if ($tabName !== $current) {
                continue;
            }
            $fields = [];
            foreach ($entries as $key => $def) {
                $label = $def[0];
                $type = $def[3] ?? 'text';
                $fields[] = $this->biField("{$key}_en", "{$label} — EN", $type, false)
                    ->extraFieldWrapperAttributes(['id' => 't-'.$key, 'class' => 'aram-sc-field']);
                $fields[] = $this->biField("{$key}_ku", "{$label} — KU", $type, true);
                $fields[] = $this->biField("{$key}_ar", "{$label} — AR", $type, true, 'ar');
            }

            foreach ($this->extrasFor($tabName) as $extra) {
                $fields[] = $extra;
            }

            $tabs[] = Section::make($tabName)->description('English · Kurdish · Arabic')->schema([
                // English, Kurdish, Arabic side by side
                Grid::make(3)->schema($fields),
            ]);
        }

        if ($current === self::GOOGLE) {
            $tabs[] = Section::make('Google Business Profile')
                ->description('Once the link and the rating are set: a rating badge with the client quotes, the contact map opens your listing, and a client who has sent their words is asked to write them on Google too. Copy the rating and the number of reviews from the profile now and then.')
                ->schema([Grid::make(2)->schema($this->extrasFor(self::GOOGLE))]);
        }

        // A dedicated home for the numeric metrics and images (not text).
        if ($current === self::MEDIA) {
        $tabs[] = Section::make(self::MEDIA)->icon(Heroicon::OutlinedPhoto)->schema([
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
        }

        return $schema->components($tabs);
    }

    /**
     * The verification code from whatever was pasted: the whole tag Google or
     * Bing shows (<meta name="…" content="CODE" />) or just the code. Only
     * the characters such codes are made of are kept.
     */
    public static function verificationCode(mixed $state): string
    {
        $state = trim((string) $state);
        if (preg_match('/content\s*=\s*["\']([^"\']+)["\']/i', $state, $m)) {
            $state = $m[1];
        }

        return preg_replace('/[^A-Za-z0-9_\-]/', '', $state);
    }

    /** Extra non-text fields grafted onto a given text tab. */
    private function extrasFor(string $tab): array
    {
        return match ($tab) {
            // Proof of ownership for Google Search Console and Bing Webmaster
            // Tools: the code each gives for its "HTML tag" method, put on the
            // homepage as a <meta> (partials/site-verification).
            'Search & sharing' => [
                $this->text('google_site_verification', 'Google Search Console — verification code (from the "HTML tag" method)', true)
                    ->placeholder('Paste the whole <meta …> tag, or only its content="…" code')
                    ->dehydrateStateUsing(fn ($state) => self::verificationCode($state)),
                $this->text('bing_site_verification', 'Bing Webmaster Tools — verification code (msvalidate.01, "HTML Meta Tag" method)', true)
                    ->placeholder('Paste the whole <meta …> tag, or only its content="…" code')
                    ->dehydrateStateUsing(fn ($state) => self::verificationCode($state)),
            ],
            // The studio's Google Business Profile: its link (the contact
            // map opens it, and search engines are told it is the studio's),
            // the link that opens "write a review" (clients are offered it
            // once they have sent their words), and the rating and number of
            // reviews shown on the homepage — copied from the profile, so
            // update them now and then. The badge shows once the link and the
            // rating are set.
            self::GOOGLE => [
                $this->text('google_profile_url', 'Google Business Profile — its link (Google Maps → your studio → Share → Copy link)', true)
                    ->placeholder('https://maps.app.goo.gl/…')->url(),
                $this->text('google_review_url', 'Google — the "write a review" link (Business Profile → Ask for reviews → copy)', true)
                    ->placeholder('https://g.page/r/…/review')->url(),
                // numbers, so no length limit: on a number Filament makes it a digits rule, which refuses 4.9
                TextInput::make('google_rating')->label('Google rating, as shown on the profile (e.g. 4.9)')
                    ->placeholder('4.9')->numeric()->minValue(1)->maxValue(5),
                TextInput::make('google_review_count')->label('Number of Google reviews')
                    ->placeholder('23')->numeric()->minValue(0),
            ],
            'Contact' => [
                $this->text('contact_email_new', 'Email address (value)', true),
                $this->text('contact_phone', 'Phone 01 — number (value)'),
                $this->text('contact_phone2', 'Phone 02 — number (value)'),
                $this->text('contact_whatsapp', 'WhatsApp number (value) — every WhatsApp button stays hidden while this is empty', true)
                    ->placeholder('+964 750 000 0000'),
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

        $this->rememberData();   // saved: nothing left to warn about
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

    private function biField(string $key, string $label, string $type, bool $rtl, string $lang = 'ckb'): TextInput|Textarea
    {
        $field = $type === 'area'
            ? Textarea::make($key)->label($label)->rows(3)
            : TextInput::make($key)->label($label)->maxLength(3000);

        if ($rtl) {
            $field->extraInputAttributes(['dir' => 'rtl', 'lang' => $lang]);
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
