<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Something a visitor did with a partner on /partners — anonymous, like a
 * page view (a daily visitor hash, never who they are). Each kind is
 * counted at most once per visitor per partner per day, so the numbers
 * are people, not clicks.
 */
class PartnerEvent extends Model
{
    public const UPDATED_AT = null;

    /** kind => label, in the order the admin and the weekly email show them. */
    public const TYPES = [
        'open' => 'Opened',
        'call' => 'Calls',
        'whatsapp' => 'WhatsApp',
        'website' => 'Website visits',
        'map' => 'Directions',
        'badge' => 'From their badge',
    ];

    /** The ones that are a client reaching the partner. */
    public const CONTACTS = ['call', 'whatsapp', 'website', 'map'];

    protected $fillable = ['partner_id', 'type', 'visitor_hash', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /** Record one, unless this visitor already did the same with this partner today. */
    public static function record(int $partnerId, string $type, ?string $visitorHash): void
    {
        if (! isset(self::TYPES[$type])) {
            return;
        }
        $seen = $visitorHash && static::query()->where('partner_id', $partnerId)->where('type', $type)
            ->where('visitor_hash', $visitorHash)->where('created_at', '>=', now()->startOfDay())->exists();
        if (! $seen) {
            static::create(['partner_id' => $partnerId, 'type' => $type, 'visitor_hash' => $visitorHash, 'created_at' => now()]);
        }
    }

    /**
     * Counts per partner and kind since a moment: [partner_id => [type => n]].
     *
     * @return array<int, array<string, int>>
     */
    public static function countsSince(Carbon $since, ?Carbon $until = null, ?int $partnerId = null): array
    {
        $rows = static::query()->where('created_at', '>=', $since)
            ->when($until, fn ($q) => $q->where('created_at', '<', $until))
            ->when($partnerId, fn ($q) => $q->where('partner_id', $partnerId))
            ->selectRaw('partner_id, type, count(*) as c')->groupBy('partner_id', 'type')->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->partner_id][$r->type] = (int) $r->c;
        }

        return $out;
    }
}
