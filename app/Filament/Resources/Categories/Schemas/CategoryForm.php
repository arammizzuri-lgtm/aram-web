<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Name (English)')
                        ->required()->maxLength(80)
                        ->live(onBlur: true)
                        // On create, suggest a slug from the name; leave existing keys alone.
                        ->afterStateUpdated(function (string $operation, $state, callable $get, callable $set) {
                            if ($operation === 'create' && blank($get('key'))) {
                                $set('key', Str::slug((string) $state));
                            }
                        }),
                    TextInput::make('name_ku')
                        ->label('Name (Kurdish)')
                        ->maxLength(80)
                        ->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ckb']),
                    TextInput::make('key')
                        ->label('Key (slug)')
                        ->required()->maxLength(60)
                        ->rule('regex:/^[a-z0-9-]+$/')
                        ->unique(ignoreRecord: true)
                        // The key is what every project stores; changing it would
                        // orphan existing projects, so it is locked once created.
                        ->disabledOn('edit')->dehydrated()
                        ->helperText('Lowercase letters, numbers and hyphens. Stored on each project — set once, then leave it.'),
                ]),
        ]);
    }
}
