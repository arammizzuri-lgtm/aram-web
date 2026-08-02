<?php

namespace App\Filament\Resources\Expenses\Pages;

use App\Filament\Actions\RecordDeletion;
use App\Filament\Resources\Expenses\ExpenseResource;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ManageRecords;

class ManageExpenses extends ManageRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Record expense'),
        ];
    }

    /**
     * Safe to edit because the model recomputes what the expense is worth in
     * dollars on every save, not only when it is first written — so changing
     * the amount or the rate cannot leave a stale figure behind. The expense
     * keeps its number.
     */
    protected function getTableActions(): array
    {
        return [
            EditAction::make(),
            ...RecordDeletion::actions(),
        ];
    }
}
