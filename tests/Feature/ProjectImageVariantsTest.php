<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Support\ProjectImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The portfolio used to hand the browser the multi-MB originals for every
 * preview — the thumb strip alone pulled one per photo — which is what made
 * opening a project slow. These cover the derivative pipeline that replaced
 * that: every size actually gets built, each is bounded by its configured
 * maximum, and the page serves the light copies while keeping the originals
 * reachable for zoom and download.
 */
class ProjectImageVariantsTest extends TestCase
{
    use RefreshDatabase;

    /** A detailed JPEG, large enough that every derivative has to downscale. */
    private function bigJpeg(int $w = 3000, int $h = 2000): string
    {
        $gd = imagecreatetruecolor($w, $h);
        // Gradient plus variation — a flat fill would compress to almost nothing
        // and make the byte-size assertions meaningless.
        for ($y = 0; $y < $h; $y += 4) {
            for ($x = 0; $x < $w; $x += 4) {
                $c = imagecolorallocate($gd, (int) ($x * 255 / $w), (int) ($y * 255 / $h), ($x + $y) % 255);
                imagefilledrectangle($gd, $x, $y, $x + 3, $y + 3, $c);
            }
        }
        ob_start();
        imagejpeg($gd, null, 92);
        $bytes = (string) ob_get_clean();
        imagedestroy($gd);

        return $bytes;
    }

    private function makeProject(): Project
    {
        Storage::disk('public')->put('projects/render.jpg', $this->bigJpeg());

        return Project::create([
            'name' => 'Variant Test', 'category' => 'cultural',
            'status' => 'Completed', 'size' => 'default',
            'imgs' => ['projects/render.jpg'],
            'is_published' => true,   // the public page only renders published rows
        ]);
    }

    public function test_every_configured_size_is_generated_and_bounded(): void
    {
        Storage::fake('public');
        $disk = Storage::disk('public');
        $this->makeProject();

        foreach (ProjectImage::SIZES as $size => [$max, $quality]) {
            $rel = "projects/{$size}/render.webp";
            $this->assertTrue($disk->exists($rel), "missing derivative for size [{$size}]");

            [$w, $h] = getimagesize($disk->path($rel));
            $this->assertLessThanOrEqual($max, max($w, $h), "[{$size}] exceeds its {$max}px maximum");

            // aspect ratio preserved (3:2 source)
            $this->assertEqualsWithDelta(3 / 2, $w / $h, 0.01, "[{$size}] distorted the aspect ratio");
        }
    }

    public function test_derivatives_are_substantially_smaller_than_the_original(): void
    {
        Storage::fake('public');
        $disk = Storage::disk('public');
        $this->makeProject();

        $original = strlen((string) $disk->get('projects/render.jpg'));
        $strip = strlen((string) $disk->get('projects/sm/render.webp'));
        $hero = strlen((string) $disk->get('projects/lg/render.webp'));

        // The strip thumbnail is the one that used to be an entire original.
        $this->assertLessThan($original / 10, $strip, 'strip thumbnail is not meaningfully smaller');
        $this->assertLessThanOrEqual($original, $hero, 'hero derivative should never exceed the original');
        $this->assertGreaterThan($strip, $hero, 'hero should carry more detail than the strip thumbnail');
    }

    public function test_page_serves_derivatives_for_previews_and_originals_for_zoom(): void
    {
        Storage::fake('public');
        $this->makeProject();

        // Js::from() emits the payload inside a JS string literal, so its URLs
        // arrive double-escaped ("projects\\/sm\\/…"). Dropping backslashes
        // normalises that back to a plain path to compare against.
        $html = str_replace('\\', '', (string) $this->get('/')->assertOk()->getContent());

        // the payload carries all three index-aligned lists
        $this->assertStringContainsString('projects/sm/render.webp', $html, 'strip thumbnail missing from payload');
        $this->assertStringContainsString('projects/lg/render.webp', $html, 'hero derivative missing from payload');
        $this->assertStringContainsString('projects/render.jpg', $html, 'original missing — zoom and download need it');
    }

    public function test_variant_falls_back_to_the_original_when_not_yet_generated(): void
    {
        Storage::fake('public');
        $project = $this->makeProject();

        // simulate a deploy where the backfill has not reached this size yet
        Storage::disk('public')->delete('projects/lg/render.webp');

        $this->assertStringContainsString(
            'projects/render.jpg',
            (string) $project->variantUrl('projects/render.jpg', 'lg'),
            'a missing derivative must degrade to the original, not to a broken URL'
        );

        // external URLs have no derivative to fall back from
        $this->assertNull(Project::variantRel('https://example.com/a.jpg', 'sm'));
        $this->assertSame(
            'https://example.com/a.jpg',
            $project->variantUrl('https://example.com/a.jpg', 'sm')
        );
    }
}
