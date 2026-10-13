<?php

namespace App\Filament\Resources\ClientRooms\Pages;

use App\Filament\Resources\ClientRooms\ClientRoomResource;
use Filament\Resources\Pages\CreateRecord;

class CreateClientRoom extends CreateRecord
{
    protected static string $resource = ClientRoomResource::class;

    /** Straight on to the room, where its link and PIN now are. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
