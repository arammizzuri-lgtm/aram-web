<?php

namespace App\Filament\Resources\ReviewRequests\Pages;

use App\Filament\Resources\ReviewRequests\ReviewRequestResource;
use App\Models\ReviewRequest;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageReviewRequests extends ManageRecords
{
    protected static string $resource = ReviewRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New review link')
                ->modalHeading('A review link for a client')
                ->createAnother(false)
                // the link, ready to send, the moment it is made
                ->successNotification(null)
                ->after(fn (ReviewRequest $record) => Notification::make()->success()->persistent()
                    ->title('Review link ready for '.$record->name)
                    ->body('Send it on WhatsApp now, or copy it from the list (click the link). It works once, for '.ReviewRequest::DAYS.' days.')
                    ->actions([
                        Action::make('wa')->label('Send on WhatsApp')->button()->color('success')
                            ->url($record->whatsappUrl(), shouldOpenInNewTab: true),
                        Action::make('open')->label('See their page')->link()
                            ->url($record->url(), shouldOpenInNewTab: true),
                    ])->send()),
        ];
    }
}
