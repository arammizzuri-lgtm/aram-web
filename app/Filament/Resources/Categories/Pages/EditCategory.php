<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // the projects page showing only this category
            Action::make('viewOnSite')->label('View on site')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                ->url(fn (): string => site_url('/projects', 'en').'?type='.urlencode($this->record->key), shouldOpenInNewTab: true)
                ->visible(fn (): bool => true),
            DeleteAction::make(),
        ];
    }
}
