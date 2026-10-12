<?php

namespace App\Filament\Resources\JobOpenings\Pages;

use App\Filament\Resources\JobOpenings\JobOpeningResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListJobOpenings extends ListRecords
{
    protected static string $resource = JobOpeningResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewPage')->label('View careers page')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                ->url(site_url('/careers', 'en'), shouldOpenInNewTab: true),
            CreateAction::make(),
        ];
    }
}
