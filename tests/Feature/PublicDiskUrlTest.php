<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicDiskUrlTest extends TestCase
{
    /**
     * The public disk URL must be root-relative so Filament's FileUpload image
     * previews (fetched over XHR, which is CORS/mixed-content sensitive) always
     * load from the panel's own origin instead of hanging on "Loading".
     */
    public function test_public_disk_url_is_root_relative(): void
    {
        $url = Storage::disk('public')->url('projects/example.jpg');

        $this->assertSame('/storage/projects/example.jpg', $url);
        $this->assertStringStartsWith('/', $url);
        $this->assertStringNotContainsString('://', $url);
    }
}
