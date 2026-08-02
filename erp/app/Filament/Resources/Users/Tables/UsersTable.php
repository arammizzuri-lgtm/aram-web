<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Actions\RecordDeletion;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->weight('medium')
                    ->description(fn ($record) => $record->email)
                    ->searchable(['name', 'email'])
                    ->sortable(),

                TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'owner' => 'primary',
                        'manager' => 'info',
                        'accountant' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('phone')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Active' : 'Inactive')
                    ->color(fn (bool $state) => $state ? 'success' : 'danger')
                    ->icon(fn (bool $state) => $state ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle'),

                TextColumn::make('last_login_at')
                    ->label('Last seen')
                    ->since()
                    ->placeholder('Never')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->label('Role'),

                TernaryFilter::make('is_active')
                    ->label('Active')
                    ->default(true),

                RecordDeletion::filter(),
            ])
            ->recordActions([
                EditAction::make(),
                ...RecordDeletion::actions(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
