<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Project Grid — a live stand-in for the "Selected Work" row on the public
 * site, arranged by hand.
 *
 * The public grid is a bento slider: tiles flow into three rows and overflow
 * sideways, so a tile's position is decided by the order of the projects and
 * the footprint of every tile before it. That makes it near-impossible to
 * picture from a table, which is why this page mirrors the real layout with
 * the same rows, spans and covers. Dragging a tile rewrites `sort_order` and
 * the size buttons rewrite `size`; nothing else about a project is touched.
 */
class ProjectGrid extends Page
{
    protected string $view = 'filament.pages.project-grid';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Manage';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Project Grid';

    protected static ?string $title = 'Project Grid';

    protected ?string $subheading = 'Arrange the Selected Work row exactly as visitors see it.';

    /** @var array<int, array<string, mixed>> tiles the public grid renders, in order */
    public array $tiles = [];

    /** @var array<int, array<string, mixed>> projects the grid leaves out, and why */
    public array $excluded = [];

    public function mount(): void
    {
        $this->loadGrid();
    }

    /**
     * Split every project into the tiles the public grid draws and the ones it
     * skips. The skip rules are the same three the portfolio view applies, so
     * what shows here is what ships.
     */
    private function loadGrid(): void
    {
        $tiles = [];
        $excluded = [];

        $projects = Project::query()->orderBy('sort_order')->orderBy('id')->get();

        foreach ($projects as $project) {
            $reason = match (true) {
                ! $project->is_published => 'Not published',
                (bool) $project->map_only => 'Map-only pin',
                $project->coverThumbUrl() === null => 'No image yet',
                default => null,
            };

            $row = [
                'id' => $project->id,
                'name' => $project->name,
                'num' => $project->num,
                'meta' => $project->metaLabel(),
                'size' => in_array($project->size, Project::SIZES, true) ? $project->size : 'default',
                'cover' => $project->coverThumbUrl(),
                'position' => $project->coverPosition(),
                'badge' => $project->statusBadge(),
                'tone' => $project->statusClass(),
                'editUrl' => ProjectResource::getUrl('edit', ['record' => $project]),
            ];

            if ($reason === null) {
                $tiles[] = $row;
            } else {
                $excluded[] = $row + ['reason' => $reason];
            }
        }

        $this->tiles = $tiles;
        $this->excluded = $excluded;
    }

    /**
     * Persist a rearranged grid.
     *
     * @param  array<int, array{id: int|string, size: string}>  $layout
     */
    public function saveLayout(array $layout): void
    {
        $sizes = [];
        foreach ($layout as $tile) {
            $id = (int) ($tile['id'] ?? 0);
            if ($id === 0) {
                continue;
            }
            // Never trust a size posted from the browser.
            $size = $tile['size'] ?? 'default';
            $sizes[$id] = in_array($size, Project::SIZES, true) ? $size : 'default';
        }

        if ($sizes === []) {
            return;
        }

        // Order first, then footprints. Both go through the base query so a
        // rearrange never re-runs the save hooks for every project.
        Project::applyOrder(array_keys($sizes));

        foreach ($sizes as $id => $size) {
            Project::query()->whereKey($id)->toBase()->update(['size' => $size]);
        }

        $this->loadGrid();

        Notification::make()->success()
            ->title('Grid saved')
            ->body('The Selected Work row now matches this layout.')
            ->send();
    }
}
