<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A single public page load. Provides the analytics helpers the dashboard uses.
 */
class PageView extends Model
{
    public const UPDATED_AT = null; // we only record created_at

    protected $fillable = ['visitor_hash', 'path', 'referrer', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    /** Unique visitors since a given moment. */
    public static function uniqueVisitors(Carbon $since): int
    {
        return static::query()->where('created_at', '>=', $since)
            ->distinct('visitor_hash')->count('visitor_hash');
    }

    /** Total page loads since a given moment. */
    public static function pageViews(Carbon $since): int
    {
        return static::query()->where('created_at', '>=', $since)->count();
    }

    /** [date => unique-visitor-count] for the last $days days (zero-filled). */
    public static function dailyVisitors(int $days = 30): array
    {
        $rows = static::query()
            ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->selectRaw('date(created_at) as d, count(distinct visitor_hash) as c')
            ->groupBy('d')->pluck('c', 'd');

        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $out[$day] = (int) ($rows[$day] ?? 0);
        }

        return $out;
    }
}
