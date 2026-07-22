<?php

namespace App\Console\Commands;

use App\Models\Project;
use Illuminate\Console\Command;

/**
 * Build the WebP grid thumbnails for every existing uploaded project image.
 * Run once after deploying the thumbnail feature (new uploads are handled
 * automatically on save):
 *     php artisan projects:thumbs
 */
class GenerateProjectThumbs extends Command
{
    protected $signature = 'projects:thumbs {--force : Rebuild thumbnails that already exist}';

    protected $description = 'Generate optimised WebP thumbnails for uploaded project images';

    public function handle(): int
    {
        $projects = Project::all();
        $made = 0;

        foreach ($projects as $project) {
            if ($this->option('force')) {
                $this->clearThumbs($project);
            }
            $before = $this->thumbCount($project);
            $project->generateThumbnails();
            $made += max(0, $this->thumbCount($project) - $before);
            $this->line("<info>✓</info> {$project->name}");
        }

        $this->info("Done — {$made} thumbnail(s) generated across {$projects->count()} project(s).");

        return self::SUCCESS;
    }

    private function thumbCount(Project $project): int
    {
        $disk = \Illuminate\Support\Facades\Storage::disk('public');

        return collect($project->imgs ?? [])
            ->map(fn ($img) => Project::thumbRel($img))
            ->filter(fn ($rel) => $rel && $disk->exists($rel))
            ->count();
    }

    private function clearThumbs(Project $project): void
    {
        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        foreach ((array) $project->imgs as $img) {
            $rel = Project::thumbRel($img);
            if ($rel && $disk->exists($rel)) {
                $disk->delete($rel);
            }
        }
    }
}
