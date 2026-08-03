<?php

namespace App\Filament\Resources\Purchases\Pages;

use App\Filament\Actions\RecordApproval;
use App\Filament\Actions\RecordDeletion;
use App\Filament\Actions\RecordSupplierPayment;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Models\DealPurchase;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePurchases extends ManageRecords
{
    protected static string $resource = PurchaseResource::class;

    protected function getTableActions(): array
    {
        return [
            /*
             * Clear the risk from where you are looking at it.
             *
             * The at-risk column was a read-only complaint: it told you nobody
             * had committed to these goods and left you to go and find the deal.
             * Offered only on the rows still waiting, so it is a to-do list
             * rather than another button on every row.
             */
            RecordApproval::make()
                ->visible(fn (DealPurchase $record) => $record->isAtRisk()),

            RecordSupplierPayment::make(),
            EditAction::make(),
            ...RecordDeletion::actions(),
        ];
    }
}
