<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * A stage of a client room's timeline: the dates planned, the dates it
 * actually started and finished, and how far along it is (percent). Changes
 * the client accepted push the planned dates by their extra days (pushBy).
 */
class RoomStage extends Model
{
    /** The list a room's timeline starts from, in the room's language. */
    public const STANDARD = [
        'en' => ['Concept', 'Permit', 'Construction drawings', 'Construction', 'Handover'],
        'ku' => ['بیرۆکەی دیزاین', 'مۆڵەت', 'نەخشە جێبەجێکارییەکان', 'بیناسازی', 'ڕادەستکردن'],
        'ar' => ['الفكرة التصميمية', 'الترخيص', 'المخططات التنفيذية', 'التنفيذ', 'التسليم'],
    ];

    protected $fillable = [
        'client_room_id', 'name', 'planned_start', 'planned_end', 'started_on', 'finished_on', 'percent', 'sort_order',
    ];

    protected $casts = [
        'planned_start' => 'date',
        'planned_end' => 'date',
        'started_on' => 'date',
        'finished_on' => 'date',
        'percent' => 'integer',
    ];

    /** Days accepted changes push the planned start and end by (set by pushBy, not stored). */
    public int $pushStart = 0;

    public int $pushEnd = 0;

    public function room(): BelongsTo
    {
        return $this->belongsTo(ClientRoom::class, 'client_room_id');
    }

    /**
     * Pushes each planned date by the extra days of the accepted changes
     * decided on or before it: a change pushes what was still ahead of it.
     * shortcut: dates the studio re-plans after a change still get pushed by it; when that bites, keep a "dates set on" column and count only changes after it.
     *
     * @param  Collection<int, RoomChange>  $accepted
     */
    public function pushBy(Collection $accepted): static
    {
        $before = fn (?CarbonInterface $date) => $date === null ? 0 : (int) $accepted
            ->filter(fn (RoomChange $c) => $c->decided_at && $c->decided_at->toDateString() <= $date->toDateString())
            ->sum('extra_days');
        $this->pushStart = $before($this->planned_start);
        $this->pushEnd = $before($this->planned_end);

        return $this;
    }

    public function plannedStart(): ?CarbonInterface
    {
        return $this->planned_start?->copy()->addDays($this->pushStart);
    }

    public function plannedEnd(): ?CarbonInterface
    {
        return $this->planned_end?->copy()->addDays($this->pushEnd);
    }

    public function isDone(): bool
    {
        return $this->finished_on !== null;
    }

    /** Finished after its (pushed) planned end, or still going past it. */
    public function isLate(): bool
    {
        $end = $this->plannedEnd();

        return $end !== null && ($this->finished_on ?? today())->startOfDay()->gt($end);
    }
}
