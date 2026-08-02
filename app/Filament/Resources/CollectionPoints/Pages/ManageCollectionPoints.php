<?php

namespace App\Filament\Resources\CollectionPoints\Pages;

use App\Filament\Resources\CollectionPoints\CollectionPointResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCollectionPoints extends ManageRecords
{
    protected static string $resource = CollectionPointResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add a collection point')];
    }
}
