<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Support\Icons\Heroicon;
use Filament\Resources\Pages\EditRecord;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewPage')
                ->label('View page')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (): ?string => $this->record->hasPage() ? $this->record->pageUrl() : null, shouldOpenInNewTab: true)
                ->visible(fn (): bool => $this->record->hasPage()),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return ProjectResource::splitImages($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ProjectResource::mergeImages($data);
    }
}
