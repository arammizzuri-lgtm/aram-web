<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditContactMessage extends EditRecord
{
    protected static string $resource = ContactMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /** Opening a message marks it as read. */
    protected function afterFill(): void
    {
        if ($this->record && ! $this->record->is_read) {
            $this->record->update(['is_read' => true]);
            $this->data['is_read'] = true; // reflect in the form without a full re-fill
        }
    }
}
