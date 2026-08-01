<?php

namespace App\Filament\Resources\SalesOrders\Pages;

use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Models\SalesOrder;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesOrder extends CreateRecord
{
    protected static string $resource = SalesOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] ??= 'draft';
        $data['sales_rep_id'] ??= auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        // Totals come from the lines, which only exist once the record is saved.
        /** @var SalesOrder $record */
        $record = $this->record;
        $record->recalculateTotals();
        $record->forceFill(['base_total' => $record->fresh()->total])->saveQuietly();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
