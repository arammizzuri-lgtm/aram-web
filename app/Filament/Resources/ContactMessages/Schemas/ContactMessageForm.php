<?php

namespace App\Filament\Resources\ContactMessages\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContactMessageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Message')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->disabled(),
                    TextInput::make('email')->disabled()->prefixIcon('heroicon-o-envelope'),
                    TextInput::make('project_type')->label('Project type')->disabled(),
                    Toggle::make('is_read')->label('Marked as read')->inline(false),
                    Textarea::make('message')->rows(8)->disabled()->columnSpanFull(),
                ]),
        ]);
    }
}
