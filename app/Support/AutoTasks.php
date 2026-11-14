<?php

namespace App\Support;

use App\Models\RoomChange;
use App\Models\RoomDocument;
use App\Models\RoomMeeting;
use App\Models\Task;
use App\Models\VisitRequest;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Tasks the website makes by itself, and closes again once answered:
 *   a visit asked for              → until it is confirmed or another time suggested
 *   a change the client asks for   → until it has a price
 *   meeting notes sent back        → until they are corrected and asked again
 *   a document about to expire     → (made with its e-mail reminder; closed by hand)
 */
class AutoTasks
{
    public static function listen(): void
    {
        VisitRequest::created(fn (VisitRequest $v) => self::open($v, 'Answer '.$v->name.': '.VisitRequest::KINDS[$v->kind]
            .' on '.$v->wanted_on->format('D j M').', '.$v->wanted_time,
            now()->addDay()->min($v->wanted_on->copy()->shiftTimezone(Task::TZ)->setTimeFromTimeString($v->wanted_time))));
        VisitRequest::updated(fn (VisitRequest $v) => $v->status !== VisitRequest::WAITING && self::close($v));

        RoomChange::created(fn (RoomChange $c) => $c->status === RoomChange::ASKED
            && self::open($c, 'Price the change “'.$c->title.'”', now()->addDays(2), $c->client_room_id));
        RoomChange::updated(fn (RoomChange $c) => $c->status !== RoomChange::ASKED && self::close($c));

        RoomMeeting::updated(fn (RoomMeeting $m) => $m->status === RoomMeeting::PENDING && self::close($m));
    }

    /** The client says something is not right in the notes (a plain query update there, so no model event). */
    public static function meetingObjected(RoomMeeting $m): void
    {
        self::open($m, 'Correct the notes of the meeting on '.$m->met_on->format('j M').' — the client says something is not right',
            now()->addDays(2), $m->client_room_id);
    }

    public static function documentExpiring(RoomDocument $d): void
    {
        self::open($d, 'Renew “'.$d->title.'” — it expires on '.$d->expires_on->format('j M Y'),
            $d->expires_on->copy()->shiftTimezone(Task::TZ)->setTime(9, 0), $d->client_room_id);
    }

    /** One open task per thing: a second call while it is open adds nothing. */
    private static function open(Model $source, string $title, CarbonInterface $due, ?int $room = null): bool
    {
        if (Task::query()->open()->whereMorphedTo('source', $source)->exists()) {
            return false;
        }
        // stored as UTC: Eloquent keeps a date's clock time, whatever its timezone
        $task = new Task(['title' => mb_substr($title, 0, 160), 'due_at' => $due->copy()->utc(), 'client_room_id' => $room, 'priority' => 'high']);
        $task->source()->associate($source)->save();

        return true;
    }

    private static function close(Model $source): bool
    {
        Task::query()->open()->whereMorphedTo('source', $source)->get()->each->update(['status' => 'done']);

        return true;
    }
}
