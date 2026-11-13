<?php

namespace App\Mail;

use App\Filament\Resources\ClientRooms\ClientRoomResource;
use App\Models\ClientRoom;
use App\Models\RoomChange;
use App\Models\RoomChoice;
use App\Models\RoomComment;
use App\Models\RoomDrawing;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tells the studio what a client just did in their room: approved a drawing,
 * sent one back with what to change, pinned a comment on one, commented on a
 * progress update, asked for, accepted or declined a change with a price, or
 * chose a finish —
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
        if ($drawing = $comment->drawing) {
            return new self($room, $room->client_name.' pinned a comment on “'.$drawing->title.'”', $comment->body);
        }
        $update = $comment->projectUpdate;
        $about = $update ? ($update->title_en ?: $update->title_ku ?: 'the update of '.$update->date->format('j M Y')) : 'an update';

        return new self($room, $room->client_name.' commented on “'.$about.'”', $comment->body);
    }

    public static function changed(ClientRoom $room, RoomChange $change): self
    {
        $days = $change->extra_days ? ' (+'.$change->extra_days.' days)' : '';

        return match ($change->status) {
            RoomChange::ACCEPTED => new self($room, $room->client_name.' accepted the change “'.$change->title.'” — '.$room->money($change->price, 'en').$days,
                $change->room_payment_id ? 'It is now a payment stage of its own in their room.' : null, 'good'),
            RoomChange::DECLINED => new self($room, $room->client_name.' declined the change “'.$change->title.'”', $change->decline_note, 'warn'),
            default => new self($room, $room->client_name.' asked for a change: “'.$change->title.'” — give it a price in the room', $change->details),
        };
    }

    public static function chose(ClientRoom $room, RoomChoice $choice): self
    {
        $option = $choice->chosen;
        $extra = $option && $option->extra_cost > 0 ? ' (+'.$room->money($option->extra_cost, 'en').', now a payment stage)' : '';

        return new self($room, $room->client_name.' chose “'.$option?->name.'” for “'.$choice->title.'”'.$extra, $choice->client_note, 'good');
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
