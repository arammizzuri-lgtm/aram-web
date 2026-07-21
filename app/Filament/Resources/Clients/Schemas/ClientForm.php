<?php

namespace App\Filament\Resources\Clients\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Client / Partner')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()->maxLength(120)->columnSpanFull(),
                    TextInput::make('sub_en')
                        ->label('Category (English)')->maxLength(120)
                        ->placeholder('e.g. Real Estate'),
                    TextInput::make('sub_ku')
                        ->label('Category (Kurdish)')->maxLength(120)
                        ->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ckb']),
                    Toggle::make('is_published')
                        ->label('Published (visible on site)')->default(true)->columnSpanFull(),
                ]),

            Section::make('Logo')
                ->description('Upload the original logo — a transparent PNG or SVG works best. It is converted automatically to a clean one-colour version that matches the site (white, gold on hover). Leave empty to keep the built-in line-art mark.')
                ->schema([
                    FileUpload::make('logo')
                        ->label('Original logo')
                        ->image()
                        ->acceptedFileTypes(['image/png', 'image/svg+xml', 'image/webp', 'image/jpeg'])
                        ->directory('clients')->disk('public')
                        ->imageEditor()
                        ->helperText('PNG/SVG with a transparent background gives the best result.'),
                ]),
        ]);
    }
}
