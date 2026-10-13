<?php

namespace App\Filament\Resources\ClientRooms\Pages;

use App\Filament\Resources\ClientRooms\ClientRoomResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClientRooms extends ListRecords
{
    protected static string $resource = ClientRoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New client room'),
        ];
    }
}
