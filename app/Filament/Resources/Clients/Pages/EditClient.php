<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // the clients band on the homepage, once published
            Action::make('viewOnSite')->label('View on site')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                ->url(fn (): string => site_url('/', 'en').'#clients', shouldOpenInNewTab: true)
                ->visible(fn (): bool => (bool) $this->record->is_published),
            DeleteAction::make(),
        ];
    }
}
