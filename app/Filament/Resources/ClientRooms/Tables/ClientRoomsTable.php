<?php

namespace App\Filament\Resources\ClientRooms\Tables;

use App\Models\ClientRoom;
use App\Models\RoomDrawing;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ClientRoomsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('project')->withCount([
                'drawings as waiting_count' => fn ($q) => $q->where('status', RoomDrawing::PENDING),
                'comments as unseen_comments' => fn ($q) => $q->where('from_studio', false)->whereNull('seen_at'),
                'drawings as unseen_decisions' => fn ($q) => $q->where('status', '!=', RoomDrawing::PENDING)->whereNull('seen_at'),
            ]))
            ->columns([
                TextColumn::make('client_name')->label('Client')->searchable()
                    ->description(fn (ClientRoom $r) => $r->project?->name),
                TextColumn::make('news')->label('New from them')->badge()
                    ->state(fn (ClientRoom $r) => $r->unseen_comments + $r->unseen_decisions ?: null)
                    ->color('danger')->placeholder('—'),
                TextColumn::make('waiting_count')->label('Drawings waiting')->badge()
                    ->color(fn ($state) => $state ? 'warning' : 'gray'),
                TextColumn::make('last_seen_at')->label('Last opened')->since()->placeholder('Not yet')->sortable(),
                ToggleColumn::make('is_open')->label('Open'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('The room, its drawings, receipts and conversation are deleted. The project and its progress updates stay.'),
            ])
            ->emptyStateHeading('No client rooms yet')
            ->emptyStateDescription('Make one for a client: they follow their project — progress, drawings to approve, payments — on a private page of their own.');
    }
}
