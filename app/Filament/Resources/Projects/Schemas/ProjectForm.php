<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Filament\Forms\Components\CoverPicker;
use App\Filament\Forms\Components\MapPicker;
use App\Models\Category;
use App\Models\Status;
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
                    TextInput::make('num')
                        ->label('Display number')->maxLength(10)->placeholder('01'),
                    Toggle::make('is_published')
                        ->label('Published (visible on site)')->default(true),
                    Toggle::make('map_only')
                        ->label('Map-only (pin + statistics, no gallery card)')
                        ->helperText('For projects with no photos: shows a pin on the map and counts in the statistics, but never appears in Selected Works and needs no images. The pin card shows title, type, year, plot area and location.')
                        ->default(false),
                    Select::make('category')
                        ->options(fn () => Category::options())->required()->native(false)
                        ->helperText('Manage the list under Manage → Project Categories.'),
                    Select::make('status')
                        ->options(fn () => Status::options())->required()->native(false)
                        ->helperText('Manage the list under Manage → Project Statuses.'),
                    Select::make('size')
                        ->label('Card size on grid')
                        ->options([
                            'default' => 'Default',
                            'large' => 'Large (featured)',
                            'wide' => 'Wide',
                        ])->default('default')->required()->native(false),
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
                    Textarea::make('narrative')->label('Architect’s narrative')->rows(4)->columnSpanFull(),
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
                    Hidden::make('cover'),
                    Hidden::make('cover_x'),
                    Hidden::make('cover_y'),
                    CoverPicker::make('cover_picker')
                        ->label('Card cover & focal point')
                        ->columnSpanFull(),
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
