<?php

namespace App\Filament\Resources\Partners\Pages;

use App\Filament\Resources\Partners\PartnerResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditPartner extends EditRecord
{
    protected static string $resource = PartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // its card on /partners, once published
            Action::make('viewOnSite')->label('View on site')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                ->url(fn (): string => site_url('/partners', 'en').'#'.$this->record->anchor(), shouldOpenInNewTab: true)
                ->visible(fn (): bool => (bool) $this->record->is_published),
            DeleteAction::make(),
        ];
    }
}
