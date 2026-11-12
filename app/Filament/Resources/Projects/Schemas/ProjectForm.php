<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Filament\Forms\Components\CalloutPicker;
use App\Filament\Forms\Components\CoverPicker;
use App\Filament\Forms\Components\MapPicker;
use App\Models\Category;
use App\Models\Project;
use App\Models\Status;
use App\Support\Embed;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProjectForm
{
    /** Voice notes as phones and WhatsApp save them (as the client quotes take them). */
    private const AUDIO_TYPES = [
        'audio/ogg', 'audio/opus', 'application/ogg', 'audio/mpeg', 'audio/mp3',
        'audio/mp4', 'audio/x-m4a', 'audio/m4a', 'audio/aac', 'audio/x-aac',
        'audio/wav', 'audio/x-wav', 'audio/wave', 'audio/webm',
        // an .m4a is often taken for a video by its first bytes
        'video/mp4',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Project details')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Name (English)')
                        ->required()->maxLength(160),
                    TextInput::make('name_ku')
                        ->label('Name (Kurdish)')
                        ->maxLength(160)
                        ->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ckb']),
                    TextInput::make('name_ar')
                        ->label('Name (Arabic)')
                        ->maxLength(160)
                        ->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar'])->columnSpanFull(),
                    TextInput::make('slug')
                        ->label('Page address')
                        ->prefix(fn (): string => url('/projects').'/')
                        ->maxLength(191)
                        ->unique(ignoreRecord: true)
                        ->placeholder('made from the English name')
                        ->helperText('The project’s own page, in English; the Kurdish page is the same address under /ku. '
                            .'Filled in from the name when the project is first saved. Changing it breaks links that have already been shared.')
                        ->columnSpanFull(),
                    TextInput::make('num')
                        ->label('Display number')->maxLength(10)
                        // Pre-filled with the next number in upload order, so the
                        // sequence keeps itself. Clearing it hands the job back to
                        // the model; typing your own number overrides both.
                        ->default(fn (): string => Project::nextNum())
                        ->placeholder(fn (): string => Project::nextNum())
                        ->helperText('Set automatically in the order projects are added. Change it only to force a specific number.'),
                    Toggle::make('is_published')
                        ->label('Published (visible on site)')->default(true),
                    Toggle::make('map_only')
                        ->label('Map-only (pin + statistics, no gallery card)')
                        ->helperText('For projects with no photos: shows a pin on the map and counts in the statistics, but never appears in Selected Works and needs no images. The pin card shows title, type, year, plot area and location.')
                        ->default(false),
                    Select::make('categories')
                        ->label('Categories')
                        ->multiple()
                        ->options(fn () => Category::options())
                        ->required()->native(false)
                        ->helperText('Pick every category that fits — e.g. Residential and Exterior. '
                            .'The first one is the project’s main label on the card. '
                            .'Manage the list under Portfolio → Projects → Categories.'),
                    Select::make('status')
                        ->options(fn () => Status::options())->required()->native(false)
                        ->helperText('Manage the list under Portfolio → Projects → Statuses.'),
                    Select::make('size')
                        ->label('Card size on grid')
                        ->options([
                            'default' => 'Default — 1 × 1',
                            'large' => 'Large, featured — 2 × 2',
                            'wide' => 'Wide — 1 × 2',
                        ])->default('default')->required()->native(false)
                        ->helperText('Its tile on the homepage grid and on the projects page wall. Keep most projects Default, a few Large, and fewer still Wide.'),
                    TextInput::make('typology')->maxLength(120)->placeholder('Cultural / Civic'),
                    // Arabic pages (/ar): blank shows the English
                    TextInput::make('typology_ar')->label('Typology (Arabic)')->maxLength(120)
                        ->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar']),
                    TextInput::make('location_ar')->label('Location (Arabic)')->maxLength(191)
                        ->helperText('The whole place in one line, e.g. «فيوتشر سيتي، أربيل، كوردستان». Left blank, the English location is shown.')
                        ->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar']),
                    TextInput::make('neighbourhood')
                        ->label('Neighbourhood / area')
                        ->maxLength(120)->placeholder('Kawrgusk')
                        ->helperText('Shown on the map when the project is opened.'),
                    TextInput::make('city')
                        ->maxLength(120)->placeholder('Erbil')
                        ->helperText('Counts toward the “Cities” statistic.'),
                    TextInput::make('country')
                        ->maxLength(120)->placeholder('Kurdistan Region')
                        ->helperText('Counts toward the “Countries” statistic.')
                        ->columnSpanFull(),
                    // The map picker below writes to these; kept as hidden fields
                    // so they still persist to the lat/lng columns and drive the
                    // public site map.
                    Hidden::make('lat'),
                    Hidden::make('lng'),
                    MapPicker::make('map_picker')
                        ->label('Map pin position')
                        ->helperText('Click the map where the project sits to drop a pin — drag it to fine-tune, or search a place name. This is the exact pin shown on the public map. Leave empty for no pin.')
                        ->columnSpanFull(),
                    TextInput::make('year')->maxLength(40)->placeholder('2022 – 2024'),
                    TextInput::make('area')
                        ->label('Plot area')
                        ->maxLength(40)
                        ->suffix('m²')
                        ->placeholder('8,400')
                        ->helperText('Enter the number only — “m²” is added automatically.')
                        // show the bare number while editing; store it with the unit
                        ->formatStateUsing(fn (?string $state): ?string => self::stripAreaUnit($state))
                        ->dehydrateStateUsing(fn (?string $state): ?string => self::formatArea($state)),
                ]),

            Section::make('Story')
                ->columns(2)
                ->schema([
                    Textarea::make('desc')->label('Description (English)')->rows(5),
                    Textarea::make('desc_ku')->label('Description (Kurdish)')->rows(5)
                        ->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ckb']),
                    Textarea::make('desc_ar')->label('Description (Arabic)')->rows(5)
                        ->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar'])->columnSpanFull(),
                    Textarea::make('narrative')->label('Architect’s narrative (English)')->rows(4)
                        ->helperText('Shown as a quote on the project’s page. Each language shows only its own — leave one empty to show none there.'),
                    Textarea::make('narrative_ku')->label('Architect’s narrative (Kurdish)')->rows(4)
                        ->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ckb']),
                    Textarea::make('narrative_ar')->label('Architect’s narrative (Arabic)')->rows(4)
                        ->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar'])->columnSpanFull(),
                    // Written the same way on every project, a material on a project's
                    // page opens the others built with it — so the ones already used
                    // are offered as they are typed.
                    TagsInput::make('materials')->placeholder('Add a material and press Enter')
                        ->suggestions(fn () => Project::query()->pluck('materials')->flatten()->filter()->unique()->sort()->values()->all())
                        ->helperText('Pick a material already used on another project where it is the same one: the project’s page then links to every project built with it.')
                        ->columnSpanFull(),
                ]),

            Section::make('Images')
                ->description('Upload photos and/or paste image URLs — both work. Choose the card cover and its focal point below.')
                ->schema([
                    FileUpload::make('uploads')
                        ->label('Upload images')
                        ->multiple()->image()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                        ->reorderable()->appendFiles()
                        ->directory('projects')->disk('public')->visibility('public')
                        // Small thumbnails in a grid so every image is visible at
                        // a glance and can be drag-reordered to set the gallery order.
                        ->panelLayout('grid')
                        ->imagePreviewHeight('110')
                        ->openable()
                        ->imageEditor()
                        ->helperText('Drag thumbnails to set the gallery order.'),
                    Repeater::make('image_links')
                        ->label('Image URLs')
                        ->schema([
                            TextInput::make('url')->url()->required()
                                ->label('Image URL')->placeholder('https://…'),
                        ])
                        ->addActionLabel('Add image URL')
                        ->reorderable()->collapsible()->defaultItems(0),
                    // The picker below writes to these; kept as hidden so they persist.
                    // The focal point defaults to the centre — on create there are
                    // no images yet, so the picker never runs and would otherwise
                    // leave these null against their NOT NULL columns.
                    Hidden::make('cover'),
                    Hidden::make('cover_x')->default(50),
                    Hidden::make('cover_y')->default(50),
                    CoverPicker::make('cover_picker')
                        ->label('Card cover & focal point')
                        ->columnSpanFull(),
                ]),

            Section::make('More on the page')
                ->description('Anything more to tell, below the story: chapters of text, a statement, figures, a drawing. '
                    .'They are shown in this order, each numbered as a chapter. To put photographs between them, add "Photographs" '
                    .'and say how many: the gallery continues from where it stopped. Photographs not placed this way follow the last section. '
                    .'Text: a blank line between paragraphs, "- " for a list, "## " for a small heading, **bold**, *italic*.')
                ->collapsible()
                ->schema([
                    Builder::make('sections')->label('')
                        ->blocks(self::blocks())
                        ->collapsible()->collapsed(fn (?array $state) => filled($state))
                        ->cloneable()->reorderableWithButtons()->blockNumbers(false)
                        ->addActionLabel('Add a section')
                        ->columnSpanFull(),
                ]),

            Section::make('Notes on the photographs')
                ->description('Point out what a visitor would ask about — a material, a detail, why a window is where it is. Each note shows on the project page as a numbered point with its words, the way a drawing’s callouts do. Save the photographs first.')
                ->collapsible()
                ->schema([
                    CalloutPicker::make('callouts')->hiddenLabel()->columnSpanFull(),
                ]),

            Section::make('The architect’s voice')
                ->description('A minute in your own voice about this project — recorded on your phone, in Kurdish or English. It plays on the project page, under the story. A page in the other language plays the one there is, and says which language it is in.')
                ->columns(2)
                ->collapsible()
                ->schema([
                    FileUpload::make('voice_ku')->label('In Kurdish')
                        ->disk('public')->directory('projects/voices')
                        ->acceptedFileTypes(self::AUDIO_TYPES)->mimeTypeMap(['opus' => 'audio/ogg', 'm4a' => 'audio/mp4'])
                        ->maxSize(15360),
                    FileUpload::make('voice_en')->label('In English')
                        ->disk('public')->directory('projects/voices')
                        ->acceptedFileTypes(self::AUDIO_TYPES)->mimeTypeMap(['opus' => 'audio/ogg', 'm4a' => 'audio/mp4'])
                        ->maxSize(15360)
                        ->helperText('WhatsApp’s own .opus files play on Android, computers and iPhones with iOS 18.4 or newer; an .m4a or .mp3 plays everywhere.'),
                ]),

            Section::make('Render vs. built')
                ->description('A render and a photograph of the finished building from the same angle. The project page lays one over the other with a slider between them. Shown only when both are set.')
                ->columns(2)
                ->collapsible()
                ->schema([
                    FileUpload::make('compare_before')->label('Render')
                        ->image()->directory('projects/compare')->disk('public')->visibility('public')
                        ->imagePreviewHeight('160')->openable(),
                    FileUpload::make('compare_after')->label('Built (photograph)')
                        ->image()->directory('projects/compare')->disk('public')->visibility('public')
                        ->imagePreviewHeight('160')->openable(),
                ]),

            Section::make('Film & 360° tours')
                ->description('Paste the link from the address bar of YouTube, Vimeo, Matterport or Kuula. Nothing is uploaded here — the video stays where it is hosted and plays on the project page only when a visitor presses play.')
                ->collapsible()
                ->schema([
                    Repeater::make('media')->label('')
                        ->schema([
                            TextInput::make('url')->label('Link')->required()->url()
                                ->placeholder('https://www.youtube.com/watch?v=…')
                                ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                                    if (! Embed::parse($value)) {
                                        $fail('This link is not one the site can play. Use a YouTube, Vimeo, Matterport or Kuula address.');
                                    }
                                })
                                ->columnSpanFull(),
                            TextInput::make('caption_en')->label('Caption (English)')->maxLength(160),
                            TextInput::make('caption_ku')->label('Caption (Kurdish)')->maxLength(160)
                                ->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ckb']),
                        ])
                        ->columns(2)
                        ->addActionLabel('Add a film or tour')
                        ->reorderable()->collapsible()->defaultItems(0)
                        ->itemLabel(fn (array $state): ?string => Embed::parse($state['url'] ?? null)
                            ? ucfirst(Embed::parse($state['url'])['provider']).($state['caption_en'] ? ' — '.$state['caption_en'] : '')
                            : null),
                ]),
        ]);
    }

    /** A text written three times: English, Kurdish, Arabic (English stands in where one is left empty). */
    private static function tri(string $field, string $label, bool $area = false, int $rows = 3): Grid
    {
        $make = fn (string $lang, string $name, array $attrs) => ($area
            ? Textarea::make($field.'_'.$lang)->rows($rows)
            : TextInput::make($field.'_'.$lang)->maxLength(400))
            ->label($label.' ('.$name.')')->extraInputAttributes($attrs);

        return Grid::make(3)->schema([
            $make('en', 'English', []),
            $make('ku', 'Kurdish', ['dir' => 'rtl', 'lang' => 'ckb']),
            $make('ar', 'Arabic', ['dir' => 'rtl', 'lang' => 'ar']),
        ]);
    }

    /** The kinds of section a project's page can be given (Project::BLOCKS). */
    private static function blocks(): array
    {
        return [
            Block::make('text')->label(fn (?array $state) => filled($state['heading_en'] ?? null) ? 'Text · '.$state['heading_en'] : 'Text')
                ->schema([
                    self::tri('heading', 'Heading'),
                    self::tri('body', 'Text', true, 8),
                ]),
            Block::make('quote')->label('Statement')
                ->schema([
                    self::tri('text', 'One strong line', true, 3),
                    self::tri('by', 'Who said it (optional)'),
                ]),
            Block::make('figures')->label('Figures')
                ->schema([
                    self::tri('heading', 'Heading (optional)'),
                    Repeater::make('items')->label('Figures')->schema([
                        TextInput::make('value')->label('Figure')->required()->maxLength(40)->placeholder('1,200 m²'),
                        self::tri('label', 'What it counts'),
                    ])->addActionLabel('Add a figure')->reorderable()->defaultItems(1)->maxItems(6)
                        ->itemLabel(fn (array $state): ?string => $state['value'] ?? null),
                ]),
            Block::make('photos')->label(fn (?array $state) => 'Photographs · '.(filled($state['count'] ?? null) ? 'the next '.$state['count'] : 'all the rest'))
                ->schema([
                    TextInput::make('count')->label('How many photographs here')->numeric()->minValue(1)->maxValue(40)
                        ->helperText('The next photographs of the gallery, in its order. Left empty: all that remain.'),
                ]),
            Block::make('image')->label(fn (?array $state) => filled($state['caption_en'] ?? null) ? 'Drawing · '.$state['caption_en'] : 'Drawing or diagram')
                ->schema([
                    FileUpload::make('image')->label('A plan, section, diagram or sketch')->image()->required()
                        ->directory('projects/sections')->disk('public')->visibility('public')
                        ->imagePreviewHeight('160')->openable()->maxSize(20480),
                    self::tri('caption', 'Caption'),
                    Toggle::make('sheet')->label('On a drawing sheet')->default(true)
                        ->helperText('Shown whole on a pale sheet with a thin frame — for line drawings on white. Off: edge to edge, as a photograph.'),
                ]),
        ];
    }

    /** Drop a trailing m² / m2 / m^2 unit so the field edits the bare number. */
    private static function stripAreaUnit(?string $state): ?string
    {
        if ($state === null) {
            return null;
        }

        $out = preg_replace('/\s*(m²|m2|m\^2)\s*$/iu', '', $state);

        return trim($out ?? $state);
    }

    /** Store the area with the unit appended, thousands-separating a plain number. */
    private static function formatArea(?string $state): ?string
    {
        $n = self::stripAreaUnit($state);
        if ($n === null || $n === '') {
            return null;
        }

        $bare = str_replace(',', '', $n);
        if (preg_match('/^\d+$/', $bare)) {
            $n = number_format((int) $bare);
        }

        return $n.' m²';
    }
}
