<?php

namespace App\Filament\Pages;

use App\Models\Project;
use App\Support\Rooms;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Photos by room — every project's photographs on one page, each with the
 * rooms to choose from: one tap says "this is a kitchen". Visitors can then
 * see every kitchen, every façade the studio has made, together (/photos).
 *
 * It opens on the photographs not yet said; each one said leaves the list,
 * so the next is always where the last was.
 */
class PhotoRooms extends Page
{
    protected string $view = 'filament.pages.photo-rooms';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Manage';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Photos by room';

    protected static ?string $title = 'Photos by room';

    protected ?string $subheading = 'Say which room each photograph shows — one tap — and visitors can see every kitchen, every façade you have made, together.';

    /** untagged | all */
    public string $show = 'untagged';

    public function setRoom(int $project, string $img, string $room): void
    {
        $p = Project::query()->find($project);
        if (! $p || ! in_array($img, array_map('strval', (array) $p->imgs), true)) {
            return;
        }

        $rooms = (array) $p->photo_rooms;
        // the same room again takes it off
        if (Rooms::valid($room) && ($rooms[$img] ?? null) !== $room) {
            $rooms[$img] = $room;
        } else {
            unset($rooms[$img]);
        }

        // nothing else about the project changes, so nothing else needs redoing
        $p->forceFill(['photo_rooms' => $rooms ?: null])->saveQuietly();
    }

    protected function getViewData(): array
    {
        $photos = [];
        $total = 0;
        $tagged = 0;

        $projects = Project::query()->orderBy('sort_order')->orderBy('id')->get()
            ->filter(fn (Project $p) => ! $p->map_only && ! empty($p->imgs));
        foreach ($projects as $p) {
            foreach ($p->orderedImages() as $img) {
                $room = $p->roomOf($img);
                $total++;
                if ($room) {
                    $tagged++;
                }
                if ($this->show === 'untagged' && $room) {
                    continue;
                }
                $photos[] = [
                    'project' => $p->id, 'name' => $p->name, 'published' => (bool) $p->is_published,
                    'img' => (string) $img, 'thumb' => $p->variantUrl($img, 'sm'), 'room' => $room,
                ];
            }
        }

        return [
            'photos' => $photos,
            'total' => $total,
            'tagged' => $tagged,
            'rooms' => Rooms::options('en'),
            'publicUrl' => site_url('/photos', 'en'),
        ];
    }
}
