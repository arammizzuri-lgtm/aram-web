<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A holiday card: Newroz, Eid, the New Year (Manage → Holiday cards).
 * Between its dates the homepage offers it, and /card makes it — one of the
 * studio's projects, the greeting, and the visitor's own name, to send on
 * WhatsApp. A yearly one comes back on the same dates every year.
 */
class Greeting extends Model
{
    protected $fillable = [
        'name', 'title_en', 'title_ku', 'text_en', 'text_ku',
        'starts_on', 'ends_on', 'yearly', 'project_id', 'is_published',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'yearly' => 'boolean',
        'is_published' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Whether the card is offered on a day (today by default). */
    public function isOn(?CarbonInterface $day = null): bool
    {
        if (! $this->is_published || ! $this->starts_on || ! $this->ends_on) {
            return false;
        }
        $day = Carbon::instance($day ?? now())->startOfDay();

        if (! $this->yearly) {
            return $day->betweenIncluded($this->starts_on->copy()->startOfDay(), $this->ends_on->copy()->startOfDay());
        }

        // the same month and day every year — over New Year's Eve too
        $md = fn (CarbonInterface $d) => $d->month * 100 + $d->day;
        $from = $md($this->starts_on);
        $to = $md($this->ends_on);
        $today = $md($day);

        return $from <= $to ? $today >= $from && $today <= $to : $today >= $from || $today <= $to;
    }

    /** The card offered today, if there is one. */
    public static function current(?CarbonInterface $day = null): ?self
    {
        return static::query()->where('is_published', true)
            ->whereNotNull('starts_on')->whereNotNull('ends_on')
            ->orderBy('starts_on')->get()
            ->first(fn (self $g) => $g->isOn($day));
    }

    public function title(string $lang): string
    {
        return $lang === 'ku' && filled($this->title_ku) ? $this->title_ku : $this->title_en;
    }

    public function text(string $lang): string
    {
        return (string) ($lang === 'ku' && filled($this->text_ku) ? $this->text_ku : $this->text_en);
    }

    /**
     * The photographs a visitor can choose from: the chosen project's, or
     * else the cover of each of the studio's first projects.
     *
     * @return array<int, array{url: string, pos: string, name_en: string, name_ku: string}>
     */
    public function photos(int $limit = 6): array
    {
        $project = $this->project && $this->project->hasPage() ? $this->project : null;
        if ($project) {
            return collect($project->orderedVariantUrls('lg'))->take($limit)
                ->map(fn ($url, $i) => ['url' => $url, 'pos' => $i === 0 ? $project->coverPosition() : '50% 50%',
                    'name_en' => (string) $project->name, 'name_ku' => (string) ($project->name_ku ?: $project->name)])
                ->values()->all();
        }

        return Project::publishedOrdered()->get()->filter(fn (Project $p) => $p->hasPage())->take($limit)
            ->map(fn (Project $p) => ['url' => (string) $p->variantUrl($p->coverImage(), 'lg'), 'pos' => $p->coverPosition(),
                'name_en' => (string) $p->name, 'name_ku' => (string) ($p->name_ku ?: $p->name)])
            ->values()->all();
    }
}
