<?php

namespace App\Filament\Resources\Deals\Pages;

use App\Filament\Resources\Deals\DealResource;
use App\Services\Deals\DealWriter;
use Filament\Resources\Pages\CreateRecord;

class CreateDeal extends CreateRecord
{
    protected static string $resource = DealResource::class;

    /**
     * The number is allocated here, not by a database default.
     *
     * DocumentNumberGenerator locks its counter row, so two people creating a
     * deal at the same moment cannot be handed D-2026-0007 twice. Reading the
     * highest existing number instead would race and quietly produce duplicates.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['number'] = app(DealWriter::class)->assignNumber(new ($this->getModel())($data));
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        app(DealWriter::class)->sync($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
