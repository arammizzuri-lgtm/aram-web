<?php

namespace App\Filament\Resources\Landmarks\Pages;

use App\Filament\Resources\Landmarks\LandmarkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLandmarks extends ListRecords
{
    protected static string $resource = LandmarkResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
