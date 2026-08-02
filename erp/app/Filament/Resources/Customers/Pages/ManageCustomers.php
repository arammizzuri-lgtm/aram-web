<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCustomers extends ManageRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }

    /**
     * Everything about a customer is correctable afterwards: a phone number
     * changes, a credit limit is renegotiated, a name was mistyped while the
     * first order was being taken in a hurry.
     *
     * Deliberately no delete. Anyone who has ever been invoiced is referenced
     * by that invoice, and the database refuses to let go of them — so deleting
     * is either impossible or takes the history with it. Ending a relationship
     * is what the Active switch is for: they stop appearing without their past
     * being unpicked.
     */
    protected function getTableActions(): array
    {
        return [EditAction::make()];
    }
}
