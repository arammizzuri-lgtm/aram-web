<?php

namespace App\Support;

use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * A visitor's "Saved" list: the projects they hearted. It lives in their own
 * browser (saved.js); the server only ever sees it as a list of slugs — in
 * the address of a /saved page, or sent along with an enquiry.
 */
class SavedProjects
{
    /** The most a list can hold — far more than anyone shortlists. */
    public const MAX = 24;

    /**
     * Clean slugs from "a.b.c" (as saved.js writes them into an address —
     * dots, which no link detector stops at; commas also accepted) or
     * ['a', 'b'], in order, without repeats.
     *
     * @param  string|array<mixed>|null  $raw
     * @return list<string>
     */
    public static function slugs(string|array|null $raw): array
    {
        $list = is_array($raw) ? $raw : preg_split('/[.,\s]+/', (string) $raw);

        return collect($list)
            ->filter(fn ($s) => is_string($s))
            ->map(fn (string $s) => strtolower(trim($s)))
            ->filter(fn (string $s) => preg_match('/^[a-z0-9-]{1,191}$/', $s) === 1)
            ->unique()->take(self::MAX)->values()->all();
    }

    /**
     * The published projects with a page among $slugs, in the list's order;
     * anything unknown or since unpublished is dropped.
     *
     * @param  list<string>  $slugs
     * @return Collection<int, Project>
     */
    public static function projects(array $slugs): Collection
    {
        if ($slugs === []) {
            return collect();
        }

        $found = Project::query()->whereIn('slug', $slugs)->where('is_published', true)->get()
            ->filter->hasPage()->keyBy('slug');

        return collect($slugs)->map(fn ($s) => $found[$s] ?? null)->filter()->values();
    }
}
