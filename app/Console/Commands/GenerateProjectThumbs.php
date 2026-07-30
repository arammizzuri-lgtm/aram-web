<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Support\ProjectImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Build the WebP grid thumbnails for every existing uploaded project image.
 * Run once after deploying the thumbnail feature (new uploads are handled
 * automatically on save):
 *     php artisan projects:thumbs
 */
class GenerateProjectThumbs extends Command
{
    protected $signature = 'projects:thumbs
        {--force : Rebuild derivatives that already exist}
        {--limit=0 : Process at most this many projects, then stop (0 = all)}';

    protected $description = 'Generate optimised WebP derivatives for uploaded project images';

    public function handle(): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $made = 0;
        $touched = 0;

        foreach (Project::all() as $project) {
            if ($this->option('force')) {
                $this->clearVariants($project);
            }

            // Resumability: a project whose derivatives are all present is
            // skipped outright, so re-running after a timeout continues where
            // the last run stopped instead of starting over. Shared hosts cut
            // long-running commands off, and these are the slow part.
            $before = $this->variantCount($project);
            if ($before >= $this->expectedCount($project)) {
                continue;
            }

            $project->generateThumbnails();
            $made += max(0, $this->variantCount($project) - $before);
            $touched++;
            $this->line("  <info>✓</info> {$project->name}");

            if ($limit > 0 && $touched >= $limit) {
                $this->warn("Stopped after {$limit} project(s) — run the command again to continue.");
                break;
            }
        }

        $this->info("Done — {$made} derivative(s) generated across {$touched} project(s).");

        return self::SUCCESS;
    }

    /** How many derivative files this project should end up with. */
    private function expectedCount(Project $project): int
    {
        $uploads = collect($project->imgs ?? [])
            ->filter(fn ($img) => Project::variantRel($img, 'thumb') !== null)
            ->count();

        return $uploads * count(ProjectImage::SIZES);
    }

    /** How many of them exist on disk right now. */
    private function variantCount(Project $project): int
    {
        $disk = Storage::disk('public');
        $found = 0;

        foreach ((array) $project->imgs as $img) {
            foreach (array_keys(ProjectImage::SIZES) as $size) {
                $rel = Project::variantRel($img, $size);
                if ($rel !== null && $disk->exists($rel)) {
                    $found++;
                }
            }
        }

        return $found;
    }

    private function clearVariants(Project $project): void
    {
        $disk = Storage::disk('public');

        foreach ((array) $project->imgs as $img) {
            foreach (array_keys(ProjectImage::SIZES) as $size) {
                $rel = Project::variantRel($img, $size);
                if ($rel !== null && $disk->exists($rel)) {
                    $disk->delete($rel);
                }
            }
        }
    }
}
