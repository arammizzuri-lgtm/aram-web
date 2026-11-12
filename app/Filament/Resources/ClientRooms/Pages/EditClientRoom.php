<?php

namespace App\Filament\Resources\ClientRooms\Pages;

use App\Filament\Resources\ClientRooms\ClientRoomResource;
use App\Models\ClientRoom;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditClientRoom extends EditRecord
{
    protected static string $resource = ClientRoomResource::class;

    /**
     * Look at the room as the client will; send them the link, the PIN (in a
     * separate message) or word that there is something new — on WhatsApp,
     * in their language; or change the PIN or the link.
     */
    protected function getHeaderActions(): array
    {
        /** @var ClientRoom $room */
        $room = $this->record;

        return [
            Action::make('open')->label('Open the room')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                ->url(fn () => $this->record->url(), shouldOpenInNewTab: true),

            ActionGroup::make([
                Action::make('sendLink')->label('1 · Send the link')
                    ->icon(Heroicon::OutlinedLink)
                    ->url(fn () => $this->record->whatsappUrl($this->record->message('room_invite_text')), shouldOpenInNewTab: true),
                Action::make('sendPin')->label('2 · Send the PIN')
                    ->icon(Heroicon::OutlinedKey)
                    ->url(fn () => $this->record->whatsappUrl($this->record->message('room_pin_msg')), shouldOpenInNewTab: true),
                Action::make('sendNews')->label('Tell them there is something new')
                    ->icon(Heroicon::OutlinedBellAlert)
                    ->url(fn () => $this->record->whatsappUrl($this->record->message('room_news_text')), shouldOpenInNewTab: true),
            ])->label('WhatsApp')->icon(Heroicon::OutlinedChatBubbleBottomCenterText)->color('success')->button()
                ->visible(fn () => filled($this->record->whatsappUrl())),

            ActionGroup::make([
                Action::make('newPin')->label('New PIN')
                    ->icon(Heroicon::OutlinedKey)
                    ->requiresConfirmation()
                    ->modalDescription('Phones that entered the old PIN will ask for the new one. Send it to the client afterwards.')
                    ->action(function () {
                        $this->record->update(['pin' => ClientRoom::newPin()]);
                        Notification::make()->success()->title('New PIN: '.$this->record->pin)->persistent()->send();
                    }),
                Action::make('newLink')->label('New link')
                    ->icon(Heroicon::OutlinedLink)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('The old link stops working at once — for anyone it was forwarded to as well. Send the client the new one.')
                    ->action(function () {
                        $this->record->update(['token' => ClientRoom::newToken()]);
                        Notification::make()->success()->title('The room has a new link')->send();
                    }),
                DeleteAction::make()
                    ->modalDescription('The room, its drawings, receipts and conversation are deleted. The project and its progress updates stay.'),
            ])->icon(Heroicon::OutlinedEllipsisVertical)->color('gray'),
        ];
    }

    /** Opening the room here counts as reading what the client did in it. */
    protected function afterFill(): void
    {
        $this->record->markSeen();
    }
}
