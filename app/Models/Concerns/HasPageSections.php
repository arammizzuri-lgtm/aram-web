<?php

namespace App\Models\Concerns;

use App\Support\HeritageImage;

/**
 * A page composed of sections (the landmarks' blocks: text, photographs,
 * galleries, …) with a cover photograph: every photograph is measured before
 * a save and resized after it (HeritageImage). The model has a sections()
 * of its own, an image / image_credit / image_source, and names the field
 * whose three languages are the cover's alt text (coverAltField).
 */
trait HasPageSections
{
    public static function bootHasPageSections(): void
    {
        static::saving(fn (self $m) => $m->measureImages());
        // the three sizes of every photograph, built once, after the save
        static::saved(fn (self $m) => HeritageImage::buildVariants($m->imageFiles()));
    }

    abstract public function sections(): array;

    abstract protected function coverAltField(): string;

    public function hasSection(string $type): bool
    {
        return collect($this->sections())->contains('type', $type);
    }

    /**
     * Every photograph on the page in order, the cover first: each with its
     * file, size, alt, caption and credit as the image partial wants them.
     *
     * @return array<int, array>
     */
    public function images(): array
    {
        $images = [];
        $f = $this->coverAltField();
        if ($this->image) {
            $images[] = ['file' => $this->image, 'w' => null, 'h' => null, 'alt_en' => $this->{$f.'_en'}, 'alt_ku' => $this->{$f.'_ku'}, 'alt_ar' => $this->{$f.'_ar'},
                'credit' => $this->image_credit, 'source' => $this->image_source];
        }
        foreach ($this->sections() as ['type' => $type, 'data' => $d]) {
            foreach (self::imagesIn($type, $d) as $img) {
                $images[] = $img;
            }
        }

        return $images;
    }

    /** The image arrays inside one block, by its kind. */
    private static function imagesIn(string $type, array $d): array
    {
        return match ($type) {
            'image', 'annotated' => filled($d['file'] ?? null) ? [$d] : [],
            'gallery' => array_values(array_filter((array) ($d['images'] ?? []), fn ($i) => filled($i['file'] ?? null))),
            'compare' => array_values(array_filter([$d['before'] ?? null, $d['after'] ?? null], fn ($i) => filled($i['file'] ?? null))),
            default => [],
        };
    }

    /** Every photograph's file reference, cover included (for the resizer). */
    public function imageFiles(): array
    {
        return array_values(array_unique(array_filter(array_map(fn ($i) => $i['file'] ?? null, $this->images()))));
    }

    /**
     * Before a save: the width and height of any photograph that does not
     * carry them yet, read from the file, so the page can reserve the right
     * space before the picture arrives.
     */
    protected function measureImages(): void
    {
        $sections = $this->sections;
        if (! is_array($sections)) {
            return;
        }
        $measure = function (array $img): array {
            if (filled($img['file'] ?? null) && (empty($img['w']) || empty($img['h']) || ($img['_file'] ?? null) !== $img['file'])) {
                if ($dims = HeritageImage::dimensions($img['file'])) {
                    [$img['w'], $img['h']] = $dims;
                }
            }
            unset($img['_file']);

            return $img;
        };
        foreach ($sections as &$block) {
            $type = $block['type'] ?? '';
            $d = &$block['data'];
            if (! is_array($d)) {
                continue;
            }
            if (in_array($type, ['image', 'annotated'], true)) {
                $d = $measure($d);
            } elseif ($type === 'gallery' && is_array($d['images'] ?? null)) {
                $d['images'] = array_map($measure, $d['images']);
            } elseif ($type === 'compare') {
                foreach (['before', 'after'] as $k) {
                    if (is_array($d[$k] ?? null)) {
                        $d[$k] = $measure($d[$k]);
                    }
                }
            }
        }
        unset($block, $d);
        $this->sections = $sections;
    }

    /* ── Images ──────────────────────────────────────────────────────── */

    /** The cover photograph at a size: lg (default), md or sm. */
    public function imageUrl(string $size = 'lg'): ?string
    {
        return HeritageImage::url($this->image, $size);
    }
}
