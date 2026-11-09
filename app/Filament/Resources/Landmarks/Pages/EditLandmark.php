<?php

namespace App\Filament\Resources\Landmarks\Pages;

use App\Filament\Resources\Landmarks\LandmarkResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLandmark extends EditRecord
{
    protected static string $resource = LandmarkResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
