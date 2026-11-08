<?php

namespace App\Filament\Resources\WalkStops\Pages;

use App\Filament\Resources\WalkStops\WalkStopResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWalkStops extends ListRecords
{
    protected static string $resource = WalkStopResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
