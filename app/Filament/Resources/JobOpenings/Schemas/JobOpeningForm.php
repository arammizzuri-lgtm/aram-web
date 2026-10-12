<?php

namespace App\Filament\Resources\JobOpenings\Schemas;

use App\Models\JobOpening;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class JobOpeningForm
{
    public static function configure(Schema $schema): Schema
    {
        $rtl = ['dir' => 'rtl', 'lang' => 'ckb'];

        return $schema->components([
            Section::make('The position')
                ->columns(2)
                ->schema([
                    TextInput::make('title_en')->label('Title (English)')->required()->maxLength(160)
                        ->placeholder('Junior Architect'),
                    TextInput::make('title_ku')->label('Title (Kurdish)')->maxLength(160)
                        ->extraInputAttributes($rtl),
                    Select::make('type')->options(array_map(fn ($t) => $t['en'], JobOpening::types()))
                        ->default('full-time')->required()->native(false)->selectablePlaceholder(false),
                    DatePicker::make('closes_at')->label('Applications close (optional)')->native(false)
                        ->displayFormat('j M Y')
                        ->helperText('After this date the position leaves the careers page by itself.'),
                    TextInput::make('location_en')->label('Location (English)')->placeholder('Erbil studio'),
                    TextInput::make('location_ku')->label('Location (Kurdish)')->extraInputAttributes($rtl),
                    Toggle::make('is_open')->label('Listed on the careers page')->default(true)->columnSpanFull(),
                ]),

            Section::make('Description')
                ->description('Plain text. Leave an empty line between paragraphs; lines starting with "-" become a bullet list.')
                ->columns(2)
                ->schema([
                    Textarea::make('summary_en')->label('Summary (English)')->rows(3)
                        ->helperText('One or two sentences — shown on the card before it is opened.'),
                    Textarea::make('summary_ku')->label('Summary (Kurdish)')->rows(3)->extraInputAttributes($rtl),
                    Textarea::make('description_en')->label('Full description (English)')->rows(12)
                        ->placeholder("What you will do\n\n- Develop concept designs with the team\n- Prepare drawings and models\n\nWhat we look for\n\n- A degree in architecture\n- Revit and Rhino"),
                    Textarea::make('description_ku')->label('Full description (Kurdish)')->rows(12)->extraInputAttributes($rtl),
                ]),
        ]);
    }
}
