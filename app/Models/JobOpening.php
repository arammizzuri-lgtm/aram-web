<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A position the studio is hiring for. Listed on /careers while it is open
 * and its closing date (if any) has not passed.
 */
class JobOpening extends Model
{
    protected $fillable = [
        'slug', 'title_en', 'title_ku', 'type', 'location_en', 'location_ku',
        'summary_en', 'summary_ku', 'description_en', 'description_ku',
        'is_open', 'closes_at', 'sort_order',
    ];

    protected $casts = [
        'is_open' => 'boolean',
        'closes_at' => 'date',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        // made once from the title, kept through renames (it is the anchor
        // a shared link to the position points at)
        static::saving(function (JobOpening $job) {
            $base = Str::slug((string) $job->slug) ?: (Str::slug($job->title_en) ?: 'position');
            $slug = $base;
            for ($n = 2; static::query()->where('slug', $slug)->when($job->exists, fn ($q) => $q->whereKeyNot($job->getKey()))->exists(); $n++) {
                $slug = $base.'-'.$n;
            }
            $job->slug = $slug;
        });
    }

    /** The bilingual labels for each kind of position. */
    public static function types(): array
    {
        return [
            'full-time' => ['en' => 'Full-time', 'ku' => 'کاتی تەواو'],
            'part-time' => ['en' => 'Part-time', 'ku' => 'نیوەکات'],
            'internship' => ['en' => 'Internship', 'ku' => 'ڕاهێنان'],
            'contract' => ['en' => 'Contract', 'ku' => 'بە گرێبەست'],
        ];
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    /** Open, and not past its closing date — what /careers lists. */
    public function scopeListed($query)
    {
        return $query->where('is_open', true)
            ->where(fn ($q) => $q->whereNull('closes_at')->orWhereDate('closes_at', '>=', today()))
            ->orderBy('sort_order')->orderByDesc('id');
    }

    public function field(string $name, string $lang): ?string
    {
        $ku = $this->{$name.'_ku'};

        return $lang === 'ku' && filled($ku) ? $ku : $this->{$name.'_en'};
    }

    public function typeLabel(string $lang = 'en'): string
    {
        return self::types()[$this->type][$lang] ?? (string) $this->type;
    }

    /**
     * The description as blocks to render: paragraphs, and runs of lines
     * starting with "-" or "•" as bullet lists. Plain text in the admin,
     * so nothing typed there can turn into markup on the page.
     *
     * @return array<int, array{type: 'p'|'ul', text?: string, items?: array<int, string>}>
     */
    public function descriptionBlocks(string $lang): array
    {
        $blocks = [];
        foreach (preg_split('/\R{2,}/', trim((string) $this->field('description', $lang))) as $chunk) {
            $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $chunk)), 'strlen'));
            if ($lines === []) {
                continue;
            }
            $bullets = array_filter($lines, fn ($l) => preg_match('/^[-•*]\s+/u', $l));
            if (count($bullets) === count($lines)) {
                $blocks[] = ['type' => 'ul', 'items' => array_map(fn ($l) => preg_replace('/^[-•*]\s+/u', '', $l), $lines)];
            } else {
                $blocks[] = ['type' => 'p', 'text' => implode("\n", $lines)];
            }
        }

        return $blocks;
    }
}
