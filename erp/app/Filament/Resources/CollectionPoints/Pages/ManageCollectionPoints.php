<?php

namespace App\Filament\Resources\CollectionPoints\Pages;

use App\Filament\Resources\CollectionPoints\CollectionPointResource;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCollectionPoints extends ManageRecords
{
    protected static string $resource = CollectionPointResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add a collection point')];
    }

    /** Reference data is corrected in place; nothing here derives from it. */
    protected function getTableActions(): array
    {
        return [EditAction::make()];
    }
}
