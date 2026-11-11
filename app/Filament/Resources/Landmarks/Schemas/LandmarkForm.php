<?php

namespace App\Filament\Resources\Landmarks\Schemas;

use App\Filament\Forms\Components\MapPicker;
use App\Models\Landmark;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * The admin's form for a landmark: who it is, how its page opens, and the
 * page itself as a list of sections of different kinds — added, reordered,
 * duplicated and removed freely, so every landmark can have a page shaped
 * for its own story. Every text is written three times: English, Kurdish,
 * Arabic (the English stands in where a translation is left empty).
 */
class LandmarkForm
{
    private const KU = ['dir' => 'rtl', 'lang' => 'ckb'];

    private const AR = ['dir' => 'rtl', 'lang' => 'ar'];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('The landmark')->columns(3)->columnSpanFull()->schema([
                TextInput::make('name_en')->label('Name (English)')->required()->maxLength(160),
                TextInput::make('name_ku')->label('Name (Kurdish)')->maxLength(160)->extraInputAttributes(self::KU),
                TextInput::make('name_ar')->label('Name (Arabic)')->maxLength(160)->extraInputAttributes(self::AR),
                TextInput::make('place_en')->label('Place (English)')->maxLength(160)->placeholder('Near Riha (Şanlıurfa)'),
                TextInput::make('place_ku')->label('Place (Kurdish)')->maxLength(160)->extraInputAttributes(self::KU),
                TextInput::make('place_ar')->label('Place (Arabic)')->maxLength(160)->extraInputAttributes(self::AR),
                TextInput::make('period_en')->label('Period (English)')->maxLength(160)->placeholder('c. 9600–8200 BC'),
                TextInput::make('period_ku')->label('Period (Kurdish)')->maxLength(160)->extraInputAttributes(self::KU),
                TextInput::make('period_ar')->label('Period (Arabic)')->maxLength(160)->extraInputAttributes(self::AR),
                Select::make('region')->label('Part of Kurdistan')->options(Landmark::options(Landmark::REGIONS))->required()->native(false),
                Select::make('era')->label('Age')->options(Landmark::options(Landmark::ERAS))->required()->native(false),
                Grid::make(2)->schema([
                    TextInput::make('year_from')->label('From (year)')->numeric()->placeholder('-9600')
                        ->helperText('Negative for BC. Places it on the collection\'s timeline.'),
                    TextInput::make('year_to')->label('To (year)')->numeric()->placeholder('-8200')
                        ->helperText('Empty = still lived in or in use.'),
                ]),
                // what it is built of: the map's "By material" filter and the tags on its page
                Select::make('materials')->label('Built of')->multiple()->native(false)->columnSpanFull()
                    ->options(collect(Landmark::MATERIALS)->mapWithKeys(fn ($colour, $key) => [$key => Landmark::materialName($key, 'en')])->all())
                    ->helperText('The main material first, then any others. Shown as tags on the page, and the heritage map can be filtered by it. The names and the line about each material are in Site content → Kurdistan heritage.'),
            ]),

            Section::make('How the page opens')->columns(3)->columnSpanFull()
                ->description('The one line is what the map and the cards say; the introduction is the first thing read on the page.')
                ->schema([
                    Select::make('hero_style')->label('Opening')->options(Landmark::HERO_STYLES)->default('editorial')->required()->native(false)->columnSpanFull(),
                    Textarea::make('summary_en')->label('One line (English)')->rows(3),
                    Textarea::make('summary_ku')->label('One line (Kurdish)')->rows(3)->extraInputAttributes(self::KU),
                    Textarea::make('summary_ar')->label('One line (Arabic)')->rows(3)->extraInputAttributes(self::AR),
                    Textarea::make('intro_en')->label('Introduction (English)')->rows(5)->helperText('Two or three sentences that make the visitor curious.'),
                    Textarea::make('intro_ku')->label('Introduction (Kurdish)')->rows(5)->extraInputAttributes(self::KU),
                    Textarea::make('intro_ar')->label('Introduction (Arabic)')->rows(5)->extraInputAttributes(self::AR),
                    FileUpload::make('image')->label('Cover photograph')->image()
                        ->disk('public')->directory('heritage')->visibility('public')->imagePreviewHeight('160')->maxSize(20480)
                        ->helperText('Landscape. The opening of the page, the card and the map pop-up. Your own photograph, or one you may use — then write its credit.'),
                    TextInput::make('image_credit')->label('Photo credit')->maxLength(200)->placeholder('Name, CC BY-SA 4.0, via Wikimedia Commons'),
                    TextInput::make('image_source')->label('Photo source link')->url()->maxLength(300)->placeholder('https://commons.wikimedia.org/wiki/File:…'),
                ]),

            Section::make('The page')->columnSpanFull()
                ->description('Sections, in the order they are read. Add the kinds this landmark\'s story needs, drag to reorder, duplicate or remove. Text: a blank line between paragraphs, "- " for a list, "> " for a quotation, "## " for a small heading, **bold**, *italic*.')
                ->schema([
                    Builder::make('sections')->label('')
                        ->blocks(self::blocks())
                        ->collapsible()->collapsed(fn (?array $state) => filled($state))
                        ->cloneable()->reorderableWithButtons()->blockNumbers(false)
                        ->blockPickerColumns(2)->blockPickerWidth('2xl')
                        ->addActionLabel('Add a section')
                        ->columnSpanFull(),
                ]),

            Section::make('UNESCO')->columns(4)->columnSpanFull()->schema([
                Toggle::make('unesco')->label('World Heritage Site')->inline(false)->live(),
                TextInput::make('unesco_year')->label('Inscribed in')->numeric()->placeholder('2018')->visible(fn (Get $get) => $get('unesco')),
                TextInput::make('unesco_ref')->label('Reference no.')->numeric()->placeholder('1572')->helperText('whc.unesco.org/en/list/<no.>')->visible(fn (Get $get) => $get('unesco')),
                TextInput::make('unesco_criteria')->label('Criteria')->maxLength(40)->placeholder('(i)(ii)(iv)')->visible(fn (Get $get) => $get('unesco')),
                TextInput::make('unesco_name')->label('Official name of the property')->maxLength(200)->placeholder('Cultural Landscape of Hawraman/Uramanat')
                    ->helperText('When it differs from the landmark\'s name.')->columnSpanFull()->visible(fn (Get $get) => $get('unesco')),
            ]),

            Section::make('Where')->columnSpanFull()->schema([
                Hidden::make('lat'),
                Hidden::make('lng'),
                MapPicker::make('map_picker')->label('Pin on the map')
                    ->helperText('Drag the pin to the exact spot (or search a place name).')
                    ->columnSpanFull(),
            ]),

            Section::make('Publishing')->columns(3)->columnSpanFull()->schema([
                TextInput::make('slug')->label('Web address')->prefix('/kurdistan-heritage/')->maxLength(120)
                    ->rules(['nullable', 'alpha_dash'])->unique(ignoreRecord: true)
                    ->helperText('Made from the English name when left empty.'),
                TextInput::make('sort_order')->label('Order in the list')->numeric()->default(fn () => (int) Landmark::max('sort_order') + 1),
                Toggle::make('is_published')->label('Shown on the website')->default(true)->inline(false)
                    ->helperText('Unpublished, the page is reachable only through Preview.'),
            ]),
        ]);
    }

    /* ── Field helpers ───────────────────────────────────────────────── */

    /** One text, three times: English, Kurdish, Arabic, side by side. */
    private static function tri(string $field, string $label, bool $area = false, int $rows = 3, ?string $help = null): Grid
    {
        $make = fn (string $lang, string $name) => ($area
            ? Textarea::make($field.'_'.$lang)->rows($rows)
            : TextInput::make($field.'_'.$lang)->maxLength(400))
            ->label($label.' ('.$name.')')
            ->extraInputAttributes($lang === 'ku' ? self::KU : ($lang === 'ar' ? self::AR : []));

        return Grid::make(3)->schema([
            $make('en', 'English')->helperText($help),
            $make('ku', 'Kurdish'),
            $make('ar', 'Arabic'),
        ]);
    }

    /** A photograph with its measurements, credit, alt text and caption. */
    private static function imageFields(string $label = 'Photograph', bool $caption = true): array
    {
        $fields = [
            FileUpload::make('file')->label($label)->image()
                ->disk('public')->directory('heritage')->visibility('public')->imagePreviewHeight('160')->maxSize(20480)
                ->live(onBlur: true)->columnSpanFull(),
            Hidden::make('w'),
            Hidden::make('h'),
            Grid::make(2)->schema([
                TextInput::make('credit')->label('Credit')->maxLength(200)->placeholder('Name, CC BY-SA 4.0, via Wikimedia Commons'),
                TextInput::make('source')->label('Source link')->url()->maxLength(300),
            ]),
            self::tri('alt', 'Alt text', help: 'What the photograph shows, for screen readers and search.'),
        ];
        if ($caption) {
            $fields[] = self::tri('caption', 'Caption', true, 2);
        }

        return $fields;
    }

    /** The first words of a text, for a collapsed section's label. */
    private static function words(?string $s, int $n = 8): string
    {
        return Str::words(trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $s))), $n, '…');
    }

    /* ── The kinds of section ────────────────────────────────────────── */

    /** @return array<int, Block> */
    private static function blocks(): array
    {
        return [
            Block::make('text')->label(fn (?array $state) => filled($state['heading_en'] ?? null) ? 'Text · '.$state['heading_en'] : (filled($state['body_en'] ?? null) ? 'Text · '.self::words($state['body_en']) : 'Text'))
                ->icon(Heroicon::OutlinedBars3BottomLeft)->schema([
                    Select::make('layout')->label('Layout')->default('standard')->native(false)->options([
                        'standard' => 'Reading column',
                        'lead' => 'Lead — larger first paragraph, with a drop cap',
                        'aside' => 'Aside — the heading in a side column, the text beside it',
                        'columns' => 'Two columns of text',
                        'wide' => 'Wide — across the whole page',
                    ]),
                    self::tri('heading', 'Heading'),
                    self::tri('body', 'Text', true, 10),
                ]),

            Block::make('statement')->label(fn (?array $state) => 'Statement · '.self::words($state['big_en'] ?? '', 6))
                ->icon(Heroicon::OutlinedMegaphone)->schema([
                    Select::make('align')->label('Alignment')->default('start')->native(false)->options(['start' => 'Start of the line', 'center' => 'Centred']),
                    self::tri('kicker', 'Small line above', help: 'Optional, e.g. "Older than Stonehenge by"'),
                    self::tri('big', 'The statement', help: 'Set very large: a figure ("6,000 years") or a short sentence.'),
                    self::tri('text', 'Line below', true, 2),
                ]),

            Block::make('image')->label(fn (?array $state) => 'Photograph'.(filled($state['caption_en'] ?? null) ? ' · '.self::words($state['caption_en']) : ''))
                ->icon(Heroicon::OutlinedPhoto)->schema([
                    Select::make('layout')->label('Layout')->default('wide')->native(false)->options([
                        'full' => 'Full bleed — edge to edge',
                        'wide' => 'Wide — the width of the page',
                        'column' => 'Reading width',
                        'offset-start' => 'Offset — large, to the start, caption beside',
                        'offset-end' => 'Offset — large, to the end, caption beside',
                    ]),
                    ...self::imageFields(),
                ]),

            Block::make('gallery')->label(fn (?array $state) => 'Gallery · '.count($state['images'] ?? []).' photographs'.(filled($state['heading_en'] ?? null) ? ' · '.$state['heading_en'] : ''))
                ->icon(Heroicon::OutlinedSquares2x2)->schema([
                    Select::make('layout')->label('Layout')->default('collage')->native(false)->options([
                        'collage' => 'Collage — one large, the rest small',
                        'grid' => 'Grid — equal cells',
                        'pairs' => 'Pairs — two per row, alternating sizes',
                        'strip' => 'Strip — one row, scrolls sideways',
                        'stack' => 'Stack — one after another, full width',
                    ]),
                    self::tri('heading', 'Heading'),
                    self::tri('text', 'Introduction', true, 2),
                    Repeater::make('images')->label('Photographs')->schema(self::imageFields())
                        ->itemLabel(fn (array $state) => filled($state['caption_en'] ?? null) ? self::words($state['caption_en']) : ($state['file'] ?? 'Photograph'))
                        ->reorderable()->collapsible()->collapsed()->cloneable()->defaultItems(0)->addActionLabel('Add a photograph'),
                ]),

            Block::make('stats')->label(fn (?array $state) => 'Figures · '.count($state['items'] ?? []).(filled($state['heading_en'] ?? null) ? ' · '.$state['heading_en'] : ''))
                ->icon(Heroicon::OutlinedHashtag)->schema([
                    Select::make('layout')->label('Layout')->default('row')->native(false)->options([
                        'row' => 'Row — large figures side by side',
                        'band' => 'Band — a gold band across the page',
                        'list' => 'List — one under another, with their notes',
                    ]),
                    self::tri('heading', 'Heading'),
                    Repeater::make('items')->label('Figures')->schema([
                        Grid::make(3)->schema([
                            TextInput::make('value')->label('Figure')->required()->maxLength(30)->placeholder('11,000+')->helperText('Digits are shown in each language\'s own numerals.'),
                            TextInput::make('unit_en')->label('Unit (English)')->maxLength(40)->placeholder('years'),
                            Grid::make(2)->schema([
                                TextInput::make('unit_ku')->label('Unit (Kurdish)')->maxLength(40)->extraInputAttributes(self::KU),
                                TextInput::make('unit_ar')->label('Unit (Arabic)')->maxLength(40)->extraInputAttributes(self::AR),
                            ]),
                        ]),
                        // a figure with words in it (88 BC – AD 33, 1 Apr 2020) needs them in each language
                        Grid::make(2)->schema([
                            TextInput::make('value_ku')->label('Figure in Kurdish (only if it has words)')->maxLength(40)->placeholder('٨٨ پ.ز – ٣٣ ز')->extraInputAttributes(self::KU),
                            TextInput::make('value_ar')->label('Figure in Arabic (only if it has words)')->maxLength(40)->placeholder('٨٨ ق.م – ٣٣ م')->extraInputAttributes(self::AR),
                        ]),
                        self::tri('label', 'What it is'),
                        self::tri('note', 'Small print', help: 'e.g. "estimate", "UNESCO 2018", "longest edge"'),
                    ])->itemLabel(fn (array $state) => trim(($state['value'] ?? '').' '.($state['label_en'] ?? '')))
                        ->reorderable()->collapsible()->collapsed()->cloneable()->defaultItems(0)->addActionLabel('Add a figure')->grid(2),
                ]),

            Block::make('facts')->label(fn (?array $state) => 'Facts · '.count($state['items'] ?? []).(filled($state['heading_en'] ?? null) ? ' · '.$state['heading_en'] : ''))
                ->icon(Heroicon::OutlinedLightBulb)->schema([
                    Select::make('layout')->label('Layout')->default('cards')->native(false)->options([
                        'cards' => 'Cards — a few facts side by side',
                        'list' => 'Numbered list',
                        'callout' => 'One fact, set large',
                        'world' => 'Meanwhile — what was happening elsewhere (label = where or when)',
                    ]),
                    self::tri('heading', 'Heading', help: 'Default: "Did you know?"'),
                    Repeater::make('items')->label('Facts')->schema([
                        self::tri('label', 'Label', help: 'Optional small line: a place, a date, a word.'),
                        self::tri('text', 'The fact', true, 3),
                    ])->itemLabel(fn (array $state) => self::words($state['text_en'] ?? $state['label_en'] ?? 'Fact'))
                        ->reorderable()->collapsible()->collapsed()->cloneable()->defaultItems(0)->addActionLabel('Add a fact'),
                ]),

            Block::make('timeline')->label(fn (?array $state) => 'Timeline · '.count($state['events'] ?? []).' events'.(filled($state['heading_en'] ?? null) ? ' · '.$state['heading_en'] : ''))
                ->icon(Heroicon::OutlinedClock)->schema([
                    Select::make('layout')->label('Layout')->default('vertical')->native(false)->options([
                        'vertical' => 'Vertical — a line down the page',
                        'horizontal' => 'Horizontal — scrolls sideways',
                        'compact' => 'Compact — dates and titles only',
                    ]),
                    self::tri('heading', 'Heading'),
                    Repeater::make('events')->label('Events')->schema([
                        self::tri('date', 'When', help: 'As written: "c. 9600 BC", "1963", "12th century"'),
                        self::tri('title', 'What'),
                        self::tri('text', 'More', true, 2),
                    ])->itemLabel(fn (array $state) => trim(($state['date_en'] ?? '').' — '.($state['title_en'] ?? '')))
                        ->reorderable()->collapsible()->collapsed()->cloneable()->defaultItems(0)->addActionLabel('Add an event'),
                ]),

            Block::make('quote')->label(fn (?array $state) => 'Quotation · '.self::words($state['text_en'] ?? '', 6))
                ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)->schema([
                    self::tri('text', 'The words', true, 3, 'Only a documented quotation — an inscription, a traveller, an archaeologist.'),
                    self::tri('source', 'Who, and where it is written'),
                ]),

            // "How it was built": one of the studio's drawings, built up in steps.
            // The drawing is fixed (it is code); every word beside it is written here.
            Block::make('technique')->label(fn (?array $state) => 'How it was built · '.count($state['steps'] ?? []).' steps'.(filled($state['heading_en'] ?? null) ? ' · '.$state['heading_en'] : ''))
                ->icon(Heroicon::OutlinedPencilSquare)->schema([
                    Select::make('drawing')->label('Drawing')->required()->native(false)->options(Landmark::TECHNIQUES)
                        ->helperText('The studio\'s drawings, each made in a set number of steps. Write one step of text for each stage of the drawing, in order; with fewer, the last step shows the rest of the drawing.'),
                    self::tri('heading', 'Heading'),
                    self::tri('text', 'One line under the heading', true, 2),
                    Repeater::make('steps')->label('Steps')->schema([
                        self::tri('title', 'Step'),
                        self::tri('text', 'What happens in it', true, 2, 'Only what is documented about how it was built.'),
                    ])->itemLabel(fn (array $state) => $state['title_en'] ?? null)
                        ->reorderable()->collapsible()->collapsed()->cloneable()->defaultItems(0)->addActionLabel('Add a step'),
                ]),

            Block::make('annotated')->label(fn (?array $state) => 'Annotated photograph · '.count($state['points'] ?? []).' points')
                ->icon(Heroicon::OutlinedViewfinderCircle)->schema([
                    self::tri('heading', 'Heading'),
                    ...self::imageFields(),
                    Repeater::make('points')->label('Points on the photograph')->schema([
                        Grid::make(2)->schema([
                            TextInput::make('x')->label('From the left (%)')->numeric()->minValue(0)->maxValue(100)->required(),
                            TextInput::make('y')->label('From the top (%)')->numeric()->minValue(0)->maxValue(100)->required(),
                        ]),
                        self::tri('label', 'Name'),
                        self::tri('text', 'What it is', true, 2),
                    ])->itemLabel(fn (array $state) => $state['label_en'] ?? 'Point')
                        ->reorderable()->collapsible()->collapsed()->cloneable()->defaultItems(0)->addActionLabel('Add a point'),
                ]),

            Block::make('compare')->label('Before / after')
                ->icon(Heroicon::OutlinedArrowsRightLeft)->schema([
                    self::tri('heading', 'Heading'),
                    Fieldset::make('Before')->schema([Grid::make(1)->statePath('before')->schema(self::imageFields('Photograph (before)', false))]),
                    Fieldset::make('After')->schema([Grid::make(1)->statePath('after')->schema(self::imageFields('Photograph (after)', false))]),
                    self::tri('label_before', 'Label (before)', help: 'e.g. "1911"'),
                    self::tri('label_after', 'Label (after)', help: 'e.g. "2011"'),
                    self::tri('caption', 'Caption', true, 2),
                ]),

            Block::make('map')->label('Map')
                ->icon(Heroicon::OutlinedMapPin)->schema([
                    self::tri('heading', 'Heading'),
                    self::tri('text', 'Where it is, in words', true, 3),
                ]),

            Block::make('unesco')->label('UNESCO panel')
                ->icon(Heroicon::OutlinedGlobeAlt)->schema([
                    self::tri('text', 'Why it was inscribed', true, 4, 'The year, number and criteria come from the UNESCO section below.'),
                ]),

            Block::make('related')->label('Related landmarks')
                ->icon(Heroicon::OutlinedLink)->schema([
                    self::tri('heading', 'Heading'),
                    Select::make('slugs')->label('Landmarks')->multiple()->native(false)
                        ->options(fn () => Landmark::query()->orderBy('sort_order')->pluck('name_en', 'slug')->all())
                        ->helperText('Left empty, the page picks landmarks of the same age and part of Kurdistan.'),
                ]),

            Block::make('sources')->label(fn (?array $state) => 'Sources · '.count($state['items'] ?? []))
                ->icon(Heroicon::OutlinedBookOpen)->schema([
                    self::tri('heading', 'Heading', help: 'Default: "Sources & further reading"'),
                    Repeater::make('items')->label('Sources')->schema([
                        Grid::make(3)->schema([
                            TextInput::make('title')->label('Title')->required()->maxLength(200),
                            TextInput::make('url')->label('Link')->url()->maxLength(400),
                            TextInput::make('note')->label('Publisher / note')->maxLength(120),
                        ]),
                    ])->itemLabel(fn (array $state) => $state['title'] ?? 'Source')
                        ->reorderable()->collapsible()->collapsed()->defaultItems(0)->addActionLabel('Add a source'),
                ]),
        ];
    }
}
