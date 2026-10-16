<?php

namespace App\Filament\Resources\Greetings\Schemas;

use App\Models\Project;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GreetingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('The card')
                ->description('What the card says, large, and the line under it. The visitor adds their own name and chooses one of the photographs.')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label('Occasion (for you)')->required()->maxLength(80)
                        ->placeholder('Newroz')->columnSpanFull(),
                    TextInput::make('title_en')->label('Greeting (English)')->required()->maxLength(60)->placeholder('Happy Newroz'),
                    TextInput::make('title_ku')->label('Greeting (Kurdish)')->maxLength(60)->placeholder('نەورۆزتان پیرۆز بێت')
                        ->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ckb']),
                    Textarea::make('text_en')->label('Line under it (English)')->rows(2)->maxLength(160),
                    Textarea::make('text_ku')->label('Line under it (Kurdish)')->rows(2)->maxLength(160)
                        ->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ckb']),
                    Select::make('project_id')->label('Photographs from')
                        ->options(fn () => Project::publishedOrdered()->get()->filter(fn (Project $p) => $p->hasPage())->pluck('name', 'id')->all())
                        ->searchable()->native(false)->placeholder('The covers of your first projects')
                        ->helperText('Visitors choose from this project’s photographs — or, left empty, from the covers of the first projects on the site.')
                        ->columnSpanFull(),
                ]),
            Section::make('When it is offered')
                ->description('Between these dates the homepage offers the card and /card makes it. A yearly card comes back on the same dates every year; the Eids move each year — give them this year’s dates.')
                ->columns(2)
                ->schema([
                    DatePicker::make('starts_on')->label('From')->native(false)->displayFormat('j M Y'),
                    // a yearly card may run over New Year's Eve, so "until" can be the earlier date
                    DatePicker::make('ends_on')->label('Until')->native(false)->displayFormat('j M Y'),
                    Toggle::make('yearly')->label('The same dates every year')->default(false),
                    Toggle::make('is_published')->label('Offered on the site')->default(true),
                ]),
        ]);
    }
}
