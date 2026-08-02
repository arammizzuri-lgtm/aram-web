<?php

namespace App\Filament\Resources\Suppliers\Pages;

use App\Filament\Actions\RecordDeletion;
use App\Filament\Resources\Suppliers\SupplierResource;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSuppliers extends ManageRecords
{
    protected static string $resource = SupplierResource::class;

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
