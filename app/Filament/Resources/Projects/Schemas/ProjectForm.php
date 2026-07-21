<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Models\Project;
use Filament\Forms\Components\FileUpload;
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
                        ->options(Project::CATEGORY_LABELS)->required()->native(false),
                    Select::make('status')
                        ->options([
                            'Completed' => 'Completed',
                            'Under Construction' => 'Under Construction',
                            'Concept / Planning' => 'Concept / Planning',
                        ])->required()->native(false),
                    Select::make('size')
                        ->label('Card size on grid')
                        ->options([
                            'default' => 'Default',
                            'large' => 'Large (featured)',
                            'wide' => 'Wide',
                        ])->default('default')->required()->native(false),
                    TextInput::make('typology')->maxLength(120)->placeholder('Cultural / Civic'),
                    TextInput::make('location')->maxLength(160)->placeholder('Erbil, Kurdistan Region')
                        ->columnSpanFull(),
                    TextInput::make('lat')
                        ->label('Latitude')
                        ->numeric()->step('any')
                        ->minValue(-90)->maxValue(90)
                        ->placeholder('36.1912')
                        ->helperText('Map pin position. In Google Maps, right-click the location → click the coordinates to copy, then paste latitude here and longitude beside it.'),
                    TextInput::make('lng')
                        ->label('Longitude')
                        ->numeric()->step('any')
                        ->minValue(-180)->maxValue(180)
                        ->placeholder('44.0092'),
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
                ->description('The first image is the card cover. Upload photos and/or paste image URLs — both work.')
                ->schema([
                    FileUpload::make('uploads')
                        ->label('Upload images')
                        ->multiple()->image()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                        ->reorderable()->appendFiles()
                        ->directory('projects')->disk('public')
                        ->imageEditor(),
                    Repeater::make('image_links')
                        ->label('Image URLs')
                        ->schema([
                            TextInput::make('url')->url()->required()
                                ->label('Image URL')->placeholder('https://…'),
                        ])
                        ->addActionLabel('Add image URL')
                        ->reorderable()->collapsible()->defaultItems(0),
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
