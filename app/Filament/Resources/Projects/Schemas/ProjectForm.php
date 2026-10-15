<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Filament\Forms\Components\CoverPicker;
use App\Filament\Forms\Components\MapPicker;
use App\Models\Category;
use App\Models\Project;
use App\Models\Status;
use App\Support\Embed;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProjectForm
{
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
                            .'Manage the list under Manage → Project Categories.'),
                    Select::make('status')
                        ->options(fn () => Status::options())->required()->native(false)
                        ->helperText('Manage the list under Manage → Project Statuses.'),
                    Select::make('size')
                        ->label('Card size on grid')
                        ->options([
                            'default' => 'Default — 1 × 1',
                            'large' => 'Large, featured — 2 × 2',
                            'wide' => 'Wide — 1 × 2',
                        ])->default('default')->required()->native(false)
                        ->helperText('Its tile on the homepage grid and on the projects page wall. Keep most projects Default, a few Large, and fewer still Wide.'),
                    TextInput::make('typology')->maxLength(120)->placeholder('Cultural / Civic'),
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
                    Textarea::make('narrative')->label('Architect’s narrative (English)')->rows(4)
                        ->helperText('Shown as a quote on the project’s page. Each language shows only its own — leave one empty to show none there.'),
                    Textarea::make('narrative_ku')->label('Architect’s narrative (Kurdish)')->rows(4)
                        ->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ckb']),
                    TagsInput::make('materials')->placeholder('Add a material and press Enter')->columnSpanFull(),
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
