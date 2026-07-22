<?php

namespace App\Filament\Resources\Statuses\Schemas;

use App\Models\Status;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StatusForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Status text')
                        ->required()->maxLength(80)
                        ->unique(ignoreRecord: true)
                        // Projects store this exact text, so it is locked once
                        // created (delete + recreate to change it).
                        ->disabledOn('edit')->dehydrated()
                        ->helperText('The full status stored on each project, e.g. “Under Construction”. Set once, then leave it.'),
                    TextInput::make('badge')
                        ->label('Short badge label')
                        ->maxLength(40)
                        ->placeholder('e.g. Concept')
                        ->helperText('Shown on the grid card. Defaults to the status text if left blank.'),
                    Select::make('tone')
                        ->label('Badge colour')
                        ->options(Status::TONES)
                        ->default('done')->required()->native(false),
                ]),
        ]);
    }
}
