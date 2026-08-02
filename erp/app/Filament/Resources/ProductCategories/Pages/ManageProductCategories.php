<?php

namespace App\Filament\Resources\ProductCategories\Pages;

use App\Filament\Actions\RecordDeletion;
use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProductCategories extends ManageRecords
{
    protected static string $resource = ProductCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }

    /** Reference data is corrected in place; nothing here derives from it. */
    protected function getTableActions(): array
    {
        return [
            EditAction::make(),
            ...RecordDeletion::actions(),
        ];
    }
}
