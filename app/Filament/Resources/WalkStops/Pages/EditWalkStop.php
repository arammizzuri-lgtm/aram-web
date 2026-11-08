<?php

namespace App\Filament\Resources\WalkStops\Pages;

use App\Filament\Resources\WalkStops\WalkStopResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWalkStop extends EditRecord
{
    protected static string $resource = WalkStopResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
