<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A single public page load. Provides the analytics helpers the dashboard uses.
 */
class PageView extends Model
{
    public const UPDATED_AT = null; // we only record created_at

    protected $fillable = ['visitor_hash', 'path', 'referrer', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    /** Unique visitors between two moments (inclusive). */
    public static function visitorsBetween(Carbon $start, Carbon $end): int
    {
        return static::query()->whereBetween('created_at', [$start, $end])
            ->distinct('visitor_hash')->count('visitor_hash');
    }

    /** Total page loads between two moments (inclusive). */
    public static function pageViewsBetween(Carbon $start, Carbon $end): int
    {
        return static::query()->whereBetween('created_at', [$start, $end])->count();
    }

    /**
     * [bucket => unique-visitor-count] between two moments, zero-filled.
     * Buckets by day for windows of 92 days or less, otherwise by month.
     */
    public static function seriesBetween(Carbon $start, Carbon $end): array
    {
        $monthly = $start->diffInDays($end) > 92;
        $bucketExpr = $monthly ? self::monthBucketExpr() : 'date(created_at)';

        $rows = static::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("{$bucketExpr} as b, count(distinct visitor_hash) as c")
            ->groupBy('b')->pluck('c', 'b');

        $out = [];
        $cursor = $monthly ? $start->copy()->startOfMonth() : $start->copy()->startOfDay();
        $last = $monthly ? $end->copy()->startOfMonth() : $end->copy()->startOfDay();

        while ($cursor->lte($last)) {
            $key = $monthly ? $cursor->format('Y-m') : $cursor->toDateString();
            $out[$key] = (int) ($rows[$key] ?? 0);
            $monthly ? $cursor->addMonth() : $cursor->addDay();
        }

        return $out;
    }

    /** Distinct years that have recorded page views, descending, always including the current year. */
    public static function availableYears(): array
    {
        $yearExpr = DB::getDriverName() === 'sqlite' ? "strftime('%Y', created_at)" : 'year(created_at)';

        return static::query()
            ->selectRaw("distinct {$yearExpr} as y")
            ->pluck('y')
            ->map(fn ($year) => (int) $year)
            ->push(now()->year)
            ->unique()->sortDesc()->values()->all();
    }

    private static function monthBucketExpr(): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "date_format(created_at, '%Y-%m')";
    }
}
