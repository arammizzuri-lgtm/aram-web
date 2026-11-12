<?php

namespace App\Filament\Resources\JobOpenings\Pages;

use App\Filament\Resources\JobOpenings\JobOpeningResource;
use App\Models\JobOpening;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditJobOpening extends EditRecord
{
    protected static string $resource = JobOpeningResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // its place on /careers, while it is listed there (open, not past its closing date)
            Action::make('viewOnSite')->label('View on site')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                ->url(fn (): string => site_url('/careers', 'en').'#'.$this->record->slug, shouldOpenInNewTab: true)
                ->visible(fn (): bool => JobOpening::listed()->whereKey($this->record->getKey())->exists()),
            DeleteAction::make(),
        ];
    }
}
