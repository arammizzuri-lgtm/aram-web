<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /** The document a new presentation starts from; the editor adds its first pages. */
    public static function blank(string $name, string $theme, string $size, string $orient): string
    {
        return json_encode([
            'v' => 1, 'size' => $size, 'orient' => $orient, 'theme' => $theme,
            'info' => ['project' => $name], 'pages' => [],
        ], JSON_UNESCAPED_UNICODE);
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
