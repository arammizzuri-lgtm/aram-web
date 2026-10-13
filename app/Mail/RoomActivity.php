<?php

namespace App\Mail;

use App\Filament\Resources\ClientRooms\ClientRoomResource;
use App\Models\ClientRoom;
use App\Models\RoomComment;
use App\Models\RoomDrawing;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tells the studio what a client just did in their room: approved a drawing,
 * sent one back with what to change, or commented on a progress update —
 * with their words, a WhatsApp button to answer, and the room in the admin.
 */
class RoomActivity extends Mailable
{
    public function __construct(
        public ClientRoom $room,
        public string $headline,
        public ?string $words = null,
        public string $tone = 'neutral',
    ) {}

    public static function decided(ClientRoom $room, RoomDrawing $drawing): self
    {
        return $drawing->status === RoomDrawing::APPROVED
            ? new self($room, $room->client_name.' approved “'.$drawing->title.'”', $drawing->decision_note, 'good')
            : new self($room, $room->client_name.' asked for changes to “'.$drawing->title.'”', $drawing->decision_note, 'warn');
    }

    public static function commented(ClientRoom $room, RoomComment $comment): self
    {
        $update = $comment->projectUpdate;
        $about = $update ? ($update->title_en ?: $update->title_ku ?: 'the update of '.$update->date->format('j M Y')) : 'an update';

        return new self($room, $room->client_name.' commented on “'.$about.'”', $comment->body);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->headline.' — '.$this->room->project->name);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.room-activity',
            with: [
                'adminUrl' => ClientRoomResource::getUrl('edit', ['record' => $this->room]),
                'whatsapp' => $this->room->whatsappUrl(),
            ],
        );
    }
}
