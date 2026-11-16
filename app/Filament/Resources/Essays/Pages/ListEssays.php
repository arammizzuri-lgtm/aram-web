<?php

namespace App\Filament\Resources\Essays\Pages;

use App\Filament\Resources\Essays\EssayResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEssays extends ListRecords
{
    protected static string $resource = EssayResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
