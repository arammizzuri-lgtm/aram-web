<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One of the studio's tasks (admin → Tasks). Due at a day and time (Erbil);
 * the board and the timeline show how long is left. Done, a repeating task
 * makes its next one. A task from the website (source) closes itself when
 * what it was about is answered (App\Support\AutoTasks).
 */
class Task extends Model
{
    public const TZ = 'Asia/Baghdad';

    public const STATUSES = ['todo' => 'To do', 'doing' => 'Doing', 'done' => 'Done'];

    public const PRIORITIES = ['high' => 'High', 'normal' => 'Normal', 'low' => 'Low'];

    public const REPEATS = ['daily' => 'Every day', 'weekly' => 'Every week', 'monthly' => 'Every month'];

    protected $fillable = [
        'title', 'notes', 'status', 'priority', 'pinned', 'labels', 'project_id', 'client_room_id', 'starts_on', 'due_at', 'repeat', 'checklist',
        'estimate_minutes', 'spent_minutes', 'timer_started_at',
    ];

    protected $attributes = ['status' => 'todo', 'priority' => 'normal', 'pinned' => false, 'spent_minutes' => 0];

    /** The id of the next one this save made (a repeating task, done) — for Undo to take back. */
    public ?int $madeNext = null;

    protected $casts = [
        'pinned' => 'boolean',
        'labels' => 'array',
        'starts_on' => 'date',
        'due_at' => 'datetime',
        'done_at' => 'datetime',
        'timer_started_at' => 'datetime',
        'estimate_minutes' => 'integer',
        'spent_minutes' => 'integer',
        'checklist' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (Task $t) {
            // a room's task is also its project's: listed there, and grouped with it on the timeline
            if ($t->client_room_id && ! $t->project_id) {
                $t->project_id = ClientRoom::query()->whereKey($t->client_room_id)->value('project_id');
            }
            if ($t->isDirty('checklist')) {
                // the admin's repeater keys each step by a random id: keep a plain list
                $t->checklist = array_values((array) $t->checklist) ?: null;
            }
            if ($t->isDirty('labels')) {
                $t->labels = array_values(array_unique((array) $t->labels)) ?: null;
            }
            if ($t->isDirty('status')) {
                $t->done_at = $t->status === 'done' ? now() : null;
            }
            // done: its timer stops, and the time is kept
            if ($t->status === 'done' && $t->timer_started_at) {
                $t->spent_minutes = $t->spent();
                $t->timer_started_at = null;
            }
            // done: the next one of a repeating task, and this one stops repeating
            if ($t->isDirty('status') && $t->status === 'done' && $t->repeat) {
                $next = $t->next();
                $next->save();
                $t->madeNext = $next->id;
                $t->repeat = null;
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(ClientRoom::class, 'client_room_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('status', '!=', 'done');
    }

    /** The next one of a repeating task: the same, a day, week or month on — and never already late. */
    public function next(): self
    {
        $step = fn (CarbonInterface $d) => match ($this->repeat) {
            'daily' => $d->addDay(), 'weekly' => $d->addWeek(), default => $d->addMonthNoOverflow(),
        };
        $due = $step($this->due_at->copy());
        while ($due->isPast()) {
            $due = $step($due);
        }
        $next = $this->replicate(['status', 'done_at', 'source_type', 'source_id', 'created_at', 'updated_at']);
        $next->status = 'todo';
        $next->due_at = $due;
        $next->starts_on = $this->starts_on ? $due->copy()->subDays((int) $this->starts_on->diffInDays($this->due_at)) : null;
        $next->checklist = collect($this->checklist ?? [])->map(fn ($s) => ['text' => $s['text'] ?? '', 'done' => false])->all() ?: null;
        $next->spent_minutes = 0;
        $next->timer_started_at = null;

        return $next;
    }

    // ---- time: the estimate, the time spent, and one timer running at a time

    /** Minutes spent, with the running timer's. */
    public function spent(): int
    {
        return $this->spent_minutes + ($this->timer_started_at ? (int) round($this->timer_started_at->diffInSeconds(now()) / 60) : 0);
    }

    /** Starts its timer — stopping any other — and a task still to do is begun. */
    public function startTimer(): void
    {
        static::query()->whereNotNull('timer_started_at')->whereKeyNot($this->id)->get()->each->stopTimer();
        $this->update(['timer_started_at' => now(), 'status' => $this->status === 'todo' ? 'doing' : $this->status]);
    }

    public function stopTimer(): void
    {
        if ($this->timer_started_at) {
            $this->update(['spent_minutes' => $this->spent(), 'timer_started_at' => null]);
        }
    }

    /** 90 → "1h 30m", 45 → "45m", 360 → "6h" */
    public static function hm(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return trim(($h ? $h.'h ' : '').($m || ! $h ? $m.'m' : ''));
    }

    /** "1h 24m of 6h", "45m", or null when nothing is known yet */
    public function timeSummary(): ?string
    {
        $spent = $this->spent();
        if (! $spent && ! $this->estimate_minutes) {
            return null;
        }

        return self::hm($spent).($this->estimate_minutes ? ' of '.self::hm($this->estimate_minutes) : '');
    }

    public function isOverEstimate(): bool
    {
        return $this->estimate_minutes && $this->spent() > $this->estimate_minutes;
    }

    /** Snoozed some days: the same time of day (Erbil), counted from today if it is already late. */
    public function snoozed(int $days): CarbonInterface
    {
        $due = $this->due_at->copy()->timezone(self::TZ);
        if ($due->isPast()) {
            $due = now(self::TZ)->setTime($due->hour, $due->minute);
        }

        return $due->addDays($days);
    }

    public function isLate(): bool
    {
        return $this->status !== 'done' && $this->due_at->isPast();
    }

    /** "3 days left", "Due in 5 hours", "2 days late"… */
    public function timeLeft(): string
    {
        if ($this->status === 'done') {
            return 'Done '.$this->done_at?->timezone(self::TZ)->format('j M');
        }
        $mins = (int) now()->diffInMinutes($this->due_at, false);
        $late = $mins < 0;
        $mins = abs($mins);
        $say = fn (int $n, string $unit) => $n.' '.$unit.($n === 1 ? '' : 's');
        $amount = match (true) {
            $mins < 60 => $say(max(1, $mins), 'minute'),
            $mins < 24 * 60 => $say(intdiv($mins, 60), 'hour'),
            default => $say(intdiv($mins, 24 * 60), 'day'),
        };

        return $late ? $amount.' late' : ($mins < 24 * 60 ? 'Due in '.$amount : $amount.' left');
    }

    /** danger: late · warning: under a day · info: under three days · gray: further or done */
    public function urgency(): string
    {
        return match (true) {
            $this->status === 'done' => 'gray',
            $this->isLate() => 'danger',
            $this->due_at->lt(now()->addDay()) => 'warning',
            $this->due_at->lt(now()->addDays(3)) => 'info',
            default => 'gray',
        };
    }

    /** [ticked, all] of its checklist */
    public function steps(): array
    {
        $list = collect($this->checklist ?? []);

        return [$list->where('done', true)->count(), $list->count()];
    }

    /** Where it belongs, in words: the room's client and project, the project, or nothing. */
    public function place(): ?string
    {
        if ($this->room) {
            return $this->room->client_name.' · '.($this->room->project?->name ?? 'room');
        }

        return $this->project?->name;
    }

    /** When the timeline bar begins: its start day, else when it was made — never after it is due. */
    public function startsAt(): CarbonInterface
    {
        $start = $this->starts_on?->copy()->shiftTimezone(self::TZ) ?? $this->created_at ?? now();

        return $start->min($this->due_at);
    }
}
