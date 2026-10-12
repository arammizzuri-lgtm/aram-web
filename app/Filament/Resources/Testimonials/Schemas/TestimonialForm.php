<?php

namespace App\Filament\Resources\Testimonials\Schemas;

use App\Models\Client;
use App\Models\Project;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TestimonialForm
{
    public static function configure(Schema $schema): Schema
    {
        $rtl = ['dir' => 'rtl', 'lang' => 'ckb'];

        return $schema->components([
            Section::make('What they said')
                ->description('A quote shows only in the language it is written in — the client’s own words are never stood in for by a translation they did not give. Fill in one or both.')
                ->columns(2)
                ->schema([
                    Textarea::make('quote_en')->label('Quote (English)')->rows(5)
                        ->requiredWithout('quote_ku')->validationAttribute('quote'),
                    Textarea::make('quote_ku')->label('Quote (Kurdish)')->rows(5)
                        ->extraInputAttributes($rtl),
                ]),

            Section::make('Who said it')
                ->columns(2)
                ->schema([
                    TextInput::make('person_name')->label('Name (English)')->required()->maxLength(120),
                    TextInput::make('person_name_ku')->label('Name (Kurdish)')->maxLength(120)
                        ->extraInputAttributes($rtl),
                    TextInput::make('person_role_en')->label('Role (English)')->maxLength(120)
                        ->placeholder('Owner · Managing Director · Homeowner'),
                    TextInput::make('person_role_ku')->label('Role (Kurdish)')->maxLength(120)
                        ->extraInputAttributes($rtl),
                    Select::make('client_id')->label('Client (optional)')
                        ->options(fn () => Client::query()->orderBy('sort_order')->pluck('name', 'id'))
                        ->searchable()->native(false)
                        ->helperText('Named after the role, e.g. “Owner, Empire World”.'),
                    Select::make('project_id')->label('About which project (optional)')
                        ->options(fn () => Project::query()->orderBy('sort_order')->pluck('name', 'id'))
                        ->searchable()->native(false)
                        ->helperText('The quote also appears on that project’s page.'),
                    Toggle::make('is_published')->label('Show on the website')->default(true),
                ]),
        ]);
    }
}
