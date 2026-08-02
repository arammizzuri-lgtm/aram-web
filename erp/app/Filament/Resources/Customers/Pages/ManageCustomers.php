<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Actions\RecordDeletion;
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
     * There used to be no delete here, on the reasoning that anyone who has
     * been invoiced is referenced by that invoice and the database refuses to
     * let go of them. That is true of erasing one and not of deleting one: a
     * deleted customer is hidden, not unpicked, their invoices and their
     * balance stay exactly where they were, and they can be brought back. So
     * the button is here, and it says what it will do first.
     *
     * Ending a relationship is still what the Active switch is for, and the
     * dialog says so — deleting somebody you simply stopped buying from throws
     * away a list you may want to read later.
     */
    protected function getTableActions(): array
    {
        return [
            EditAction::make(),
            ...RecordDeletion::actions(),
        ];
    }
}
