<?php

namespace App\Filament\Resources\ExchangeRates\Pages;

use App\Filament\Actions\RecordDeletion;
use App\Filament\Resources\ExchangeRates\ExchangeRateResource;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ManageRecords;

class ManageExchangeRates extends ManageRecords
{
    protected static string $resource = ExchangeRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add rate')
                ->mutateDataUsing(function (array $data): array {
                    $data['created_by'] = auth()->id();

                    return $data;
                }),
        ];
    }

    /**
     * A rate typed wrong is worth correcting, and correcting one does not
     * rewrite the past: every document freezes the rate in force on its own
     * date, so what has already been priced stays priced.
     *
     * created_by is left as it was — it records who first entered the rate.
     */
    protected function getTableActions(): array
    {
        return [
            EditAction::make(),
            ...RecordDeletion::actions(),
        ];
    }
}
