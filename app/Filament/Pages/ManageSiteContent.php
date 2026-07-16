<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use BackedEnum;
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
 * Site Content editor — a single screen with one tab per public-site section.
 * Every field maps to a key in the site_settings table; English/Kurdish pairs
 * sit side by side (Kurdish inputs are right-to-left).
 */
class ManageSiteContent extends Page
{
    protected string $view = 'filament.pages.manage-site-content';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Manage';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Site Content';

    protected static ?string $title = 'Site Content';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        // Load every setting's current value into the form.
        $this->form->fill(SiteSetting::allKeyed());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Sections')->persistTabInQueryString()->columnSpanFull()->tabs([

                Tab::make('Hero')->icon(Heroicon::OutlinedSparkles)->schema([
                    Grid::make(2)->schema([
                        $this->text('hero_eyebrow', 'Eyebrow', true),
                        ...$this->bilingual('hero_title_line1', 'Title — line 1'),
                        ...$this->bilingual('hero_title_line2', 'Title — line 2 (gold accent)'),
                        ...$this->bilingual('hero_sub', 'Subtitle', 'area'),
                    ]),
                ]),

                Tab::make('Brand')->schema([
                    Grid::make(2)->schema([
                        $this->text('brand_name', 'Navbar studio name'),
                        $this->text('brand_sub', 'Navbar subtitle'),
                        $this->text('footer_logo', 'Footer logo text'),
                        $this->text('footer_tagline', 'Footer tagline'),
                        $this->text('footer_kurdish', 'Footer Kurdish tagline', false, true),
                        $this->area('footer_copy', 'Copyright (one line per row)', true),
                    ]),
                ]),

                Tab::make('About')->schema([
                    Grid::make(2)->schema([
                        ...$this->bilingual('about_label', 'Section label'),
                        ...$this->bilingual('about_heading', 'Heading (allows <em> and <br>)'),
                        $this->area('about_bio_1', 'Biography — paragraph 1', true),
                        $this->area('about_bio_2', 'Biography — paragraph 2', true),
                        $this->text('about_stat1_num', 'Metric 1 — number'),
                        $this->text('about_stat1_label', 'Metric 1 — label'),
                        $this->text('about_stat2_num', 'Metric 2 — number'),
                        $this->text('about_stat2_label', 'Metric 2 — label'),
                        $this->text('about_stat3_num', 'Metric 3 — number'),
                        $this->text('about_stat3_label', 'Metric 3 — label'),
                        $this->text('about_stat4_num', 'Metric 4 — number'),
                        $this->text('about_stat4_label', 'Metric 4 — label'),
                        $this->text('about_area_num', 'Total area — number (digits only, e.g. 1240000)'),
                        $this->text('about_area_unit', 'Total area — unit (e.g. m²)'),
                        $this->text('about_area_label', 'Total area — caption'),
                        $this->area('about_area_note', 'Total area — note', true),
                        $this->area('about_quote', 'Pull quote', true),
                        $this->text('about_quote_cite', 'Quote attribution'),
                        $this->text('about_portrait_img', 'Portrait image URL', true),
                        $this->text('about_portrait_caption', 'Portrait caption'),
                    ]),
                ]),

                Tab::make('Process')->schema([
                    Grid::make(2)->schema([
                        ...$this->bilingual('process_label', 'Section label'),
                        ...$this->bilingual('process_title', 'Section title'),
                        ...$this->bilingual('process_step1_title', 'Step 1 — title'),
                        $this->area('process_step1_desc', 'Step 1 — description', true),
                        ...$this->bilingual('process_step2_title', 'Step 2 — title'),
                        $this->area('process_step2_desc', 'Step 2 — description', true),
                        ...$this->bilingual('process_step3_title', 'Step 3 — title'),
                        $this->area('process_step3_desc', 'Step 3 — description', true),
                        ...$this->bilingual('process_step4_title', 'Step 4 — title'),
                        $this->area('process_step4_desc', 'Step 4 — description', true),
                    ]),
                ]),

                Tab::make('Heritage')->schema([
                    Grid::make(2)->schema([
                        ...$this->bilingual('heritage_label', 'Section label'),
                        $this->area('heritage_title', 'Heading (one line per row)', true),
                        $this->area('heritage_desc', 'Description', true),
                        $this->text('heritage_cta', 'Button text'),
                    ]),
                ]),

                Tab::make('Contact')->schema([
                    Grid::make(2)->schema([
                        ...$this->bilingual('contact_label', 'Section label'),
                        ...$this->bilingual('contact_title', 'Heading (allows <br>)'),
                        $this->text('contact_newwork_label', 'Email — label'),
                        $this->text('contact_email_new', 'Email — address'),
                        $this->text('contact_phone_label', 'Phone 01 — label'),
                        $this->text('contact_phone', 'Phone 01 — number'),
                        $this->text('contact_phone2_label', 'Phone 02 — label'),
                        $this->text('contact_phone2', 'Phone 02 — number'),
                    ]),
                ]),

                Tab::make('Footer links')->schema([
                    Grid::make(2)->schema([
                        $this->url('footer_instagram_url', 'Instagram URL'),
                        $this->url('footer_linkedin_url', 'LinkedIn URL'),
                        $this->url('footer_behance_url', 'Behance URL'),
                        $this->url('footer_archello_url', 'Archello URL'),
                    ]),
                ]),

            ]),
        ]);
    }

    public function save(): void
    {
        DB::transaction(function () {
            foreach ($this->form->getState() as $key => $value) {
                SiteSetting::updateOrCreate(['key' => $key], ['value' => $value]);
            }
        });

        SiteSetting::flushCache();

        Notification::make()->success()->title('Site content updated')->send();
    }

    /* ---- small field builders ---- */

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

    private function url(string $key, string $label): TextInput
    {
        // No strict url() rule: links may be a full URL or a "#" placeholder until set.
        return TextInput::make($key)->label($label)->maxLength(2000)
            ->placeholder('https://… (or # if not set yet)')
            ->helperText('Paste the full profile URL, e.g. https://instagram.com/yourstudio');
    }

    private function area(string $key, string $label, bool $full = false): Textarea
    {
        $field = Textarea::make($key)->label($label)->rows(3);
        if ($full) {
            $field->columnSpanFull();
        }

        return $field;
    }

    /** Returns [English field, Kurdish field] for a base key with _en/_ku suffixes. */
    private function bilingual(string $base, string $label, string $type = 'text'): array
    {
        $make = fn (string $key, string $suffix, bool $rtl) => $type === 'area'
            ? $this->area($key, "{$label} ({$suffix})")->extraInputAttributes($rtl ? ['dir' => 'rtl'] : [])
            : $this->text($key, "{$label} ({$suffix})", false, $rtl);

        return [
            $make("{$base}_en", 'English', false),
            $make("{$base}_ku", 'Kurdish', true),
        ];
    }
}
