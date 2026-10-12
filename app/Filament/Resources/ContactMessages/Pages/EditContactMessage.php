<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\ContactMessage;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditContactMessage extends EditRecord
{
    protected static string $resource = ContactMessageResource::class;

    /** The three ways to answer, one click each — then delete. */
    protected function getHeaderActions(): array
    {
        /** @var ContactMessage $record */
        $record = $this->record;

        return [
            Action::make('reply')->label('Reply by email')
                ->icon(Heroicon::OutlinedEnvelope)
                ->url($record->email ? 'mailto:'.$record->email : null)
                ->visible(filled($record->email)),
            Action::make('whatsapp')->label('WhatsApp')
                ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                ->color('success')
                ->url($record->whatsappUrl(), shouldOpenInNewTab: true)
                ->visible(filled($record->whatsappUrl())),
            Action::make('call')->label('Call')
                ->icon(Heroicon::OutlinedPhone)
                ->color('gray')
                ->url($record->telUrl())
                ->visible(filled($record->telUrl())),
            DeleteAction::make(),
        ];
    }

    /** Opening an enquiry marks it as read. */
    protected function afterFill(): void
    {
        if ($this->record && ! $this->record->is_read) {
            $this->record->update(['is_read' => true]);
            $this->data['is_read'] = true; // reflect in the form without a full re-fill
        }
    }
}
