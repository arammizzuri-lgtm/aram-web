<?php

namespace App\Filament\Resources\Purchases\Pages;

use App\Filament\Actions\RecordDeletion;
use App\Filament\Actions\RecordSupplierPayment;
use App\Filament\Resources\Purchases\PurchaseResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePurchases extends ManageRecords
{
    protected static string $resource = PurchaseResource::class;

    protected function getTableActions(): array
    {
        return [
            RecordSupplierPayment::make(),
            EditAction::make(),
            ...RecordDeletion::actions(),
        ];
    }
}
