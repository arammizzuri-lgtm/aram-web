<?php

namespace App\Support;

use App\Filament\Pages\Tasks;
use App\Models\SiteSetting;
use App\Models\Task;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * The tasks as a calendar a phone subscribes to (iPhone, Google): every open
 * task at its due time, kept up to date by the phone itself. A calendar app
 * cannot log in, so the address carries a long secret; a new one (admin →
 * Tasks → Set up → Phone calendar) shuts the old out.
 */
class TaskCalendar
{
    public const TOKEN = 'tasks_calendar_token';

    /** The address to subscribe to — its secret made the first time it is asked for. */
    public static function url(): string
    {
        return route('tasks.calendar', setting(self::TOKEN) ?: self::renew());
    }

    public static function renew(): string
    {
        SiteSetting::put(self::TOKEN, $token = Str::random(40), 'tasks');
        SiteSetting::flushCache();

        return $token;
    }

    public static function allows(string $token): bool
    {
        $secret = (string) setting(self::TOKEN);

        return $secret !== '' && hash_equals($secret, $token);
    }

    /** The feed (RFC 5545). */
    public static function ics(): string
    {
        $text = fn (?string $s) => str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], (string) $s);
        $utc = fn (CarbonInterface $d) => $d->copy()->utc()->format('Ymd\THis\Z');
        $link = Tasks::getUrl(['show' => 'today'], panel: 'studio-aks-panel');
        $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'arammizuri.com';

        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Aram Mizuri//Tasks//EN', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
            'X-WR-CALNAME:Aram Mizuri tasks', 'X-WR-TIMEZONE:'.Task::TZ, 'REFRESH-INTERVAL;VALUE=DURATION:PT1H', 'X-PUBLISHED-TTL:PT1H'];
        foreach (Task::query()->open()->with(['project', 'room.project'])->orderBy('due_at')->get() as $t) {
            array_push($lines,
                'BEGIN:VEVENT',
                'UID:task-'.$t->id.'@'.$host,
                'DTSTAMP:'.$utc($t->updated_at ?? now()),
                'DTSTART:'.$utc($t->due_at),
                'DURATION:PT30M',
                'SUMMARY:'.$text(($t->pinned ? '📌 ' : '').$t->title),
                'DESCRIPTION:'.$text(implode("\n", array_filter([$t->place(), $t->notes, $link]))),
                'URL:'.$link,
                'BEGIN:VALARM', 'ACTION:DISPLAY', 'DESCRIPTION:'.$text($t->title), 'TRIGGER:-PT1H', 'END:VALARM',
                'END:VEVENT');
        }
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    /** A line over 75 bytes goes on in pieces, each after a space — never through a letter's bytes. */
    private static function fold(string $line): string
    {
        $out = '';
        $max = 75;
        while (strlen($line) > $max) {
            $cut = $max;
            while ((ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;   // inside a multi-byte letter (Kurdish, Arabic, an emoji)
            }
            $out .= substr($line, 0, $cut)."\r\n ";
            $line = substr($line, $cut);
            $max = 74;   // the space counts
        }

        return $out.$line;
    }
}
