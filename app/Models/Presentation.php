<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * A project presentation: the document the editor works on (pages, project
 * information, design), kept as the JSON the editor sent — never re-encoded
 * here, so an empty object stays an object.
 */
class Presentation extends Model
{
    protected $fillable = ['name', 'project_name', 'client_name', 'page_count', 'doc'];

    protected static function booted(): void
    {
        // one by one, so each file goes when its last presentation does
        static::deleting(fn (Presentation $p) => $p->assets->each->delete());
    }

    public function assets(): HasMany
    {
        return $this->hasMany(PresentationAsset::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PresentationRevision::class);
    }

    /** The document a new presentation starts from; the editor adds its first pages. */
    public static function blank(string $name, string $theme, string $size, string $orient, string $lang = 'en'): string
    {
        return json_encode([
            'v' => 1, 'size' => $size, 'orient' => $orient, 'theme' => $theme, 'lang' => $lang,
            'info' => ['project' => $name], 'pages' => [],
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * A new presentation made for a project on the website: the project's details
     * in the presentation's language, and its uploaded pictures taken in as the
     * presentation's own files. The editor makes the first pages from them
     * (`seed`) when it first opens, and the pictures' previews with them.
     * Pictures linked from other websites are left out.
     */
    public function seedFrom(Project $project): void
    {
        $doc = json_decode($this->doc, true);
        $lang = $doc['lang'] ?? 'en';
        $doc['info'] = array_filter([
            'project' => $doc['info']['project'] ?? null,
            'client' => $project->rooms()->latest('id')->value('client_name'),
            'location' => $project->tr('location', $lang) ?: $project->cityLabel($lang),
            'type' => $project->tr('typology', $lang),
            'plotArea' => $project->plotArea() ? (string) $project->plotArea() : null,
            'stage' => $project->status ? $project->statusLabel($lang) : null,
            'date' => now()->toDateString(),
        ], fn ($v) => filled($v));

        $hashes = [];
        foreach (array_values(array_unique(array_filter((array) $project->imgs, 'is_string'))) as $img) {
            if ($asset = $this->takePicture($img)) {
                $hashes[$img] = $asset->hash;
            }
        }
        $cover = $hashes[$project->coverImage()] ?? null;
        $doc['seed'] = ['cover' => $cover, 'images' => array_values($hashes), 'body' => (string) $project->tr('desc', $lang)];

        $this->update(['doc' => json_encode($doc, JSON_UNESCAPED_UNICODE), 'client_name' => $doc['info']['client'] ?? null]);
    }

    /** One of a project's uploaded pictures as a file of this presentation; WebP and GIF are turned into JPEG and PNG. */
    private function takePicture(string $img): ?PresentationAsset
    {
        $disk = Storage::disk('public');
        if (preg_match('#^(https?:)?//#', $img) || str_starts_with($img, '/') || ! $disk->exists($img)) {
            return null;
        }
        $path = $disk->path($img);
        $name = basename($img);
        $made = null;
        try {
            if (! PresentationAsset::sniff($path)) {   // not JPEG or PNG: converted, when GD can read it
                $image = match (strtolower(pathinfo($img, PATHINFO_EXTENSION))) {
                    'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
                    'gif' => @imagecreatefromgif($path),
                    default => false,
                };
                if (! $image) {
                    return null;
                }
                $png = str_ends_with(strtolower($img), '.gif');
                $made = tempnam(sys_get_temp_dir(), 'pm');
                $png ? imagepng($image, $made) : imagejpeg($image, $made, 92);
                imagedestroy($image);
                $path = $made;
                $name = pathinfo($img, PATHINFO_FILENAME).($png ? '.png' : '.jpg');
            }

            return PresentationAsset::import($path, $name, $this->id, move: (bool) $made);
        } catch (InvalidArgumentException) {
            return null;
        } finally {
            if ($made) {
                @unlink($made);
            }
        }
    }

    /** A copy to rework for another project: same pages, sharing the stored files. */
    public function duplicate(): self
    {
        $copy = $this->replicate();
        $copy->name = $this->name.' (copy)';
        $copy->save();
        foreach ($this->assets as $asset) {
            $copy->assets()->create($asset->only(['hash', 'kind', 'ext', 'name', 'size', 'meta']));
        }

        return $copy;
    }

    public function editorUrl(): string
    {
        return route('filament.studio-aks-panel.presentation-editor', $this);
    }
}
