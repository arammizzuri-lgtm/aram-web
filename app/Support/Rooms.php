<?php

namespace App\Support;

/**
 * The rooms a photograph can show (Manage → Photos by room), in the order
 * they are offered: from the street in, then out to the garden. Their names
 * are in Site Content (room_{key}), so the studio can call them what it
 * likes; the keys are what the photographs and the addresses keep.
 */
class Rooms
{
    public const KEYS = ['facade', 'entrance', 'living', 'guest', 'kitchen', 'dining', 'bedroom', 'bathroom', 'office', 'outdoor'];

    public static function label(string $key, ?string $lang = null): string
    {
        $lang ??= site_lang();

        return $lang === 'ku' ? SiteText::ku('room_'.$key) : SiteText::en('room_'.$key);
    }

    /** @return array<string, string> key => name, in the page's language */
    public static function options(?string $lang = null): array
    {
        return collect(self::KEYS)->mapWithKeys(fn ($k) => [$k => self::label($k, $lang)])->all();
    }

    public static function valid(?string $key): bool
    {
        return in_array($key, self::KEYS, true);
    }

    /**
     * Every photograph whose room has been said, of the projects with a page
     * of their own: in the order of the rooms, and within a room in the
     * studio's order of its projects.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Project>|null  $projects  published, in order (fetched if not given)
     * @return \Illuminate\Support\Collection<int, array{room: string, img: string, project: \App\Models\Project}>
     */
    public static function photos($projects = null)
    {
        $projects ??= \App\Models\Project::publishedOrdered()->get();
        $order = array_flip(self::KEYS);

        return $projects->filter(fn ($p) => $p->hasPage())->values()
            ->flatMap(fn ($p, $at) => collect($p->orderedImages())
                ->map(fn ($img) => ['room' => $p->roomOf($img), 'img' => (string) $img, 'project' => $p, 'at' => $at])
                ->filter(fn ($photo) => $photo['room'] !== null))
            ->sortBy(fn ($photo) => [$order[$photo['room']], $photo['at']])
            ->values();
    }
}
