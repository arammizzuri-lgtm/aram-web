<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectThumbnailTest extends TestCase
{
    use RefreshDatabase;

    public function test_thumbnail_is_generated_on_save_and_used_for_the_grid_cover(): void
    {
        Storage::fake('public');

        // a real 1600×1000 JPEG on the (faked) public disk
        $gd = imagecreatetruecolor(1600, 1000);
        imagefilledrectangle($gd, 0, 0, 1600, 1000, imagecolorallocate($gd, 90, 120, 160));
        ob_start();
        imagejpeg($gd, null, 90);
        $bytes = (string) ob_get_clean();
        imagedestroy($gd);
        Storage::disk('public')->put('projects/render.jpg', $bytes);

        $project = Project::create([
            'name' => 'Test', 'category' => 'cultural', 'status' => 'Completed', 'size' => 'default',
            'imgs' => ['projects/render.jpg', 'https://example.com/external.jpg'],
        ]);

        // saved hook created a downscaled WebP thumbnail for the upload
        $this->assertTrue(Storage::disk('public')->exists('projects/thumb/render.webp'));
        [$w] = getimagesize(Storage::disk('public')->path('projects/thumb/render.webp'));
        $this->assertLessThanOrEqual(1200, $w, 'thumbnail should be downscaled to the max width');

        // grid cover points at the thumbnail; full-res helper still points at the original
        $this->assertStringContainsString('projects/thumb/render.webp', (string) $project->coverThumbUrl());
        $this->assertStringContainsString('projects/render.jpg', (string) $project->coverUrl());

        // external URLs are never thumbnailed (can't resize a remote file)
        $this->assertNull(Project::thumbRel('https://example.com/external.jpg'));
        $this->assertSame('https://example.com/external.jpg', $project->thumbUrl('https://example.com/external.jpg'));
    }
}
