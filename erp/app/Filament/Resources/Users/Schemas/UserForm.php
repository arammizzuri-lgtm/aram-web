<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Unique;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->whereNull('deleted_at'))
                            ->maxLength(255),

                        TextInput::make('phone')
                            ->tel()
                            ->maxLength(32),

                        Select::make('branch_id')
                            ->label('Branch')
                            ->relationship('branch', 'name')
                            ->searchable()
                            ->preload(),
                    ]),

                Section::make('Access')
                    ->columns(2)
                    ->schema([
                        Select::make('roles')
                            ->label('Role')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->required()
                            ->helperText('Roles carry the permission set. Owner passes every gate.'),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Deactivating locks the user out on their next request.'),

                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            // Only hashed when actually supplied, so editing a user
                            // without touching this field leaves their password alone.
                            ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->required(fn (string $operation) => $operation === 'create')
                            ->helperText(fn (string $operation) => $operation === 'edit'
                                ? 'Leave blank to keep the current password.'
                                : null),
                    ]),

                Section::make('Preferences')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        Select::make('locale')
                            ->options(['en' => 'English', 'ar' => 'العربية', 'ku' => 'Kurdî'])
                            ->default('en')
                            ->required(),

                        Select::make('theme_preference')
                            ->label('Theme')
                            ->options(['system' => 'Match system', 'light' => 'Light', 'dark' => 'Dark'])
                            ->default('system')
                            ->required(),
                    ]),
            ]);
    }
}
