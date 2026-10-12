<?php

namespace App\Filament\Resources\JobApplications\Pages;

use App\Filament\Resources\JobApplications\JobApplicationResource;
use App\Models\JobApplication;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditJobApplication extends EditRecord
{
    protected static string $resource = JobApplicationResource::class;

    /** Read the CV, answer the applicant, or delete (the CV goes with it). */
    protected function getHeaderActions(): array
    {
        /** @var JobApplication $record */
        $record = $this->record;
        $cv = route('filament.studio-aks-panel.application-cv', $record);

        return [
            Action::make('cv')->label('Open CV')
                ->icon(Heroicon::OutlinedDocumentText)
                ->url($cv, shouldOpenInNewTab: true)
                ->visible($record->cvExists()),
            Action::make('cvDownload')->label('Download')
                ->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->url($cv.'?download=1')
                ->visible($record->cvExists()),
            Action::make('reply')->label('Reply by email')
                ->icon(Heroicon::OutlinedEnvelope)->color('gray')
                ->url('mailto:'.$record->email),
            Action::make('whatsapp')->label('WhatsApp')
                ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)->color('success')
                ->url($record->whatsappUrl(), shouldOpenInNewTab: true)
                ->visible(filled($record->whatsappUrl())),
            DeleteAction::make(),
        ];
    }

    /** Opening an application marks it as read. */
    protected function afterFill(): void
    {
        if ($this->record && ! $this->record->is_read) {
            $this->record->update(['is_read' => true]);
            $this->data['is_read'] = true;
        }
    }
}
