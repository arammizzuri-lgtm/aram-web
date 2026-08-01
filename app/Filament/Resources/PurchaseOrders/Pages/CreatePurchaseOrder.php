<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] ??= 'draft';
        $data['created_by'] ??= auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var PurchaseOrder $record */
        $record = $this->record;
        $record->recalculateTotals();
        $record->forceFill(['base_total' => $record->fresh()->total])->saveQuietly();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
