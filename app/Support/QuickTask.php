<?php

namespace App\Support;

use App\Filament\Pages\Tasks;
use App\Models\ClientRoom;
use App\Models\Project;
use App\Models\Task;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * A task from one line, as it would be said: "Call Dara tomorrow 4pm #client
 * !high @Afrin". A day and a time make it due (Erbil time); #label, !high or
 * !low, and @client-or-project fill in the rest; the words left are its title.
 * English words only. Without a day or time it is due tomorrow at 17:00, like
 * a new task in the form.
 */
class QuickTask
{
    private const END = '(?=[\s,.;!?])';

    private const BEFORE_DAY = '(?:on |by |due |next |this )';

    private const MONTHS = '(jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*';

    private const NUMBERS = ['a' => 1, 'an' => 1, 'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7, 'ten' => 10];

    /**
     * @return array{title: string, due_at: CarbonInterface, labels: list<string>, priority: string,
     *               client_room_id: ?int, project_id: ?int, place: ?string}
     */
    public static function parse(string $text, ?CarbonInterface $now = null): array
    {
        $now = ($now ?? now())->copy()->timezone(Task::TZ);
        $s = ' '.preg_replace('/\s+/u', ' ', trim($text)).' ';

        // #label — by its name or key; an unknown one stays in the words
        $labels = [];
        $known = Tasks::labels();
        $s = preg_replace_callback('/(?<=\s)#([\p{L}\p{N}_-]+)'.self::END.'/u', function (array $m) use (&$labels, $known) {
            $key = $known->search(fn (array $l, string $k) => $k === Str::lower($m[1]) || Str::slug($l['name']) === Str::slug($m[1]));
            if ($key === false) {
                return $m[0];
            }
            $labels[] = (string) $key;

            return '';
        }, $s);

        // !high, !urgent, ! — or !low
        $priority = 'normal';
        $s = preg_replace_callback('/(?<=\s)!(high|urgent|low)?'.self::END.'/iu', function (array $m) use (&$priority) {
            $priority = strtolower($m[1] ?? '') === 'low' ? 'low' : 'high';

            return '';
        }, $s);

        // @client (a room, by its client's name) or @project (by a word of its name)
        $room = $project = $place = null;
        $s = preg_replace_callback('/(?<=\s)@([\p{L}\p{N}_-]+)'.self::END.'/u', function (array $m) use (&$room, &$project, &$place) {
            if ($room || $project) {
                return $m[0];
            }
            $word = $m[1];   // letters, digits, _ and - only: no % to escape
            if ($r = ClientRoom::query()->with('project:id,name')->where('client_name', 'like', $word.'%')->orderBy('id')->first()) {
                [$room, $place] = [$r->id, $r->client_name.' · '.($r->project?->name ?? 'room')];
            } elseif ($p = Project::query()->where('name', 'like', '%'.$word.'%')->orderBy('name')->first()) {
                [$project, $place] = [$p->id, $p->name];
            } else {
                return $m[0];
            }

            return '';
        }, $s);

        $tonight = (bool) preg_match('/\stonight'.self::END.'/i', $s);
        $time = self::first($s, [
            '/\s(?:at |by )?(\d{1,2})(?::([0-5]\d))?\s?(am|pm)'.self::END.'/i' => fn ($m) => $m[1] >= 1 && $m[1] <= 12
                ? [$m[1] % 12 + (strtolower($m[3]) === 'pm' ? 12 : 0), (int) ($m[2] ?: 0)] : null,
            '/\s(?:at |by )?([01]?\d|2[0-3]):([0-5]\d)'.self::END.'/' => fn ($m) => [(int) $m[1], (int) $m[2]],
            // "at 4" is in the afternoon: a studio's day ("by 3" is left for "by 3 Jan")
            '/\sat (\d{1,2})'.self::END.'/i' => fn ($m) => $m[1] <= 23 ? [$m[1] <= 7 ? $m[1] + 12 : (int) $m[1], 0] : null,
            '/\s(?:at )?(?:noon|midday)'.self::END.'/i' => fn () => [12, 0],
            '/\s(?:in the )?morning'.self::END.'/i' => fn () => [9, 0],
            '/\s(?:in the )?evening'.self::END.'/i' => fn () => [18, 0],
        ]);

        $today = $now->copy()->startOfDay();
        $weekday = function (string $name) use ($today) {
            $want = array_search(strtolower(substr($name, 0, 3)), ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'], true);
            $d = $today->copy();
            while ($d->dayOfWeek !== $want) {
                $d->addDay();
            }

            return $d;
        };
        // a date without a year that has passed this year is next year's
        $dated = function (int $day, int $month, ?string $year) use ($today) {
            $y = $year ? (int) (strlen($year) === 2 ? '20'.$year : $year) : $today->year;
            if (! checkdate($month, $day, $y)) {
                return null;
            }
            $d = $today->copy()->setDate($y, $month, $day);

            return ! $year && $d->lt($today) ? $d->addYear() : $d;
        };
        $month = fn (string $m) => array_search(strtolower(substr($m, 0, 3)), ['', 'jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'], true);

        $date = self::first($s, [
            '/\s'.self::BEFORE_DAY.'?(today|tonight)'.self::END.'/i' => fn () => $today->copy(),
            '/\s'.self::BEFORE_DAY.'?(tomorrow|tmrw|tmr)'.self::END.'/i' => fn () => $today->copy()->addDay(),
            '/\sin (\d{1,3}|an?|one|two|three|four|five|six|seven|ten) (day|week|month)s?'.self::END.'/i' => function ($m) use ($today) {
                $n = is_numeric($m[1]) ? (int) $m[1] : self::NUMBERS[strtolower($m[1])];

                return match (strtolower($m[2])) { 'day' => $today->copy()->addDays($n), 'week' => $today->copy()->addWeeks($n), default => $today->copy()->addMonthsNoOverflow($n) };
            },
            '/\snext (week|month)'.self::END.'/i' => fn ($m) => strtolower($m[1]) === 'week' ? $today->copy()->addWeek() : $today->copy()->addMonthNoOverflow(),
            // whole day names, and the short ones that are not also words (wed, sat, sun need "on")
            '/\s'.self::BEFORE_DAY.'?(monday|tuesday|wednesday|thursday|friday|saturday|sunday|mon|tues?|thu|thurs?|fri)'.self::END.'/i' => fn ($m) => $weekday($m[1]),
            '/\s'.self::BEFORE_DAY.'(wed|sat|sun)'.self::END.'/i' => fn ($m) => $weekday($m[1]),
            '/\s'.self::BEFORE_DAY.'?(\d{4})-(\d{2})-(\d{2})'.self::END.'/' => fn ($m) => $dated((int) $m[3], (int) $m[2], $m[1]),
            // 5/12 is the 5th of December, as it is written here
            '/\s'.self::BEFORE_DAY.'?(\d{1,2})\/(\d{1,2})(?:\/(\d{2}|\d{4}))?'.self::END.'/' => fn ($m) => $dated((int) $m[1], (int) $m[2], $m[3] ?? null),
            '/\s'.self::BEFORE_DAY.'?(\d{1,2})(?:st|nd|rd|th)? '.self::MONTHS.'(?: (\d{4}))?'.self::END.'/i' => fn ($m) => $dated((int) $m[1], $month($m[2]), $m[3] ?? null),
            '/\s'.self::BEFORE_DAY.'?'.self::MONTHS.' (\d{1,2})(?:st|nd|rd|th)?(?:,? (\d{4}))?'.self::END.'/i' => fn ($m) => $dated((int) $m[2], $month($m[1]), $m[3] ?? null),
        ]);

        $due = match (true) {
            $date && $time => $date->setTime(...$time),
            $date !== null => $date->setTime($tonight ? 20 : 17, 0),
            // a time alone: today's, or tomorrow's if it has passed
            $time !== null => $now->copy()->setTime(...$time)->lte($now) ? $now->copy()->addDay()->setTime(...$time) : $now->copy()->setTime(...$time),
            default => $today->copy()->addDay()->setTime(17, 0),
        };

        return [
            'title' => Str::ucfirst(trim(preg_replace('/\s+/u', ' ', $s), " \t,.;:-–—")),
            'due_at' => $due,
            'labels' => array_values(array_unique($labels)),
            'priority' => $priority,
            'client_room_id' => $room,
            'project_id' => $project,
            'place' => $place,
        ];
    }

    /** The first pattern that matches and makes a value: taken out of the words. */
    private static function first(string &$s, array $patterns): mixed
    {
        foreach ($patterns as $pattern => $make) {
            if (preg_match($pattern, $s, $m) && ($value = $make($m)) !== null) {
                $s = preg_replace($pattern, ' ', $s, 1);

                return $value;
            }
        }

        return null;
    }
}
