<?php

namespace Tests\Feature;

use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Projects\RelationManagers\UpdatesRelationManager;
use App\Models\Project;
use App\Models\ProjectUpdate;
use App\Models\User;
use App\Support\Embed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Release 3: what a project page can show beyond photographs — render vs.
 * built, film and 360° tours, construction progress — and the overlay links
 * that lead to them.
 */
class ProjectShowcaseTest extends TestCase
{
    use RefreshDatabase;

    private function project(array $attrs = []): Project
    {
        return Project::create($attrs + [
            'name' => 'Erbil Tower', 'name_ku' => 'تاوەری هەولێر',
            'category' => 'commercial', 'status' => 'Under Construction', 'size' => 'default',
            'imgs' => ['https://example.com/cover.jpg'], 'is_published' => true,
        ]);
    }

    private function jpeg(string $path): void
    {
        $gd = imagecreatetruecolor(1600, 1000);
        imagefilledrectangle($gd, 0, 0, 1600, 1000, imagecolorallocate($gd, 120, 110, 90));
        ob_start();
        imagejpeg($gd, null, 88);
        Storage::disk('public')->put($path, (string) ob_get_clean());
        imagedestroy($gd);
    }

    /* ---- links that can be played ---- */

    public function test_film_and_tour_links_become_player_addresses(): void
    {
        $yt = 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&rel=0&modestbranding=1';
        foreach ([
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=30s',
            'https://youtu.be/dQw4w9WgXcQ?si=abc',
            'https://m.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://www.youtube.com/shorts/dQw4w9WgXcQ',
        ] as $url) {
            $this->assertSame($yt, Embed::parse($url)['embed'] ?? null, $url);
        }
        $this->assertSame('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', Embed::parse('https://youtu.be/dQw4w9WgXcQ')['thumb']);

        $this->assertStringStartsWith('https://player.vimeo.com/video/76979871?', Embed::parse('https://vimeo.com/76979871')['embed']);
        $this->assertStringContainsString('h=8272103f6e', Embed::parse('https://vimeo.com/76979871/8272103f6e')['embed']);

        $m = Embed::parse('https://my.matterport.com/show/?m=SxQL3iGyoDo');
        $this->assertSame(['matterport', 'tour'], [$m['provider'], $m['kind']]);
        $this->assertSame('https://kuula.co/share/collection/7lVG7?logo=0&info=0&fs=1&vr=1&thumbs=1',
            Embed::parse('https://kuula.co/share/collection/7lVG7')['embed']);
        $this->assertSame('tour', Embed::parse('https://kuula.co/post/NzdMy')['kind']);

        // anything that is not one of the four players is refused
        foreach (['https://example.com/video.mp4', 'javascript:alert(1)', 'https://youtube.com/watch?v=short',
            'https://evil.test/www.youtube.com/watch?v=dQw4w9WgXcQ', ''] as $bad) {
            $this->assertNull(Embed::parse($bad), $bad);
        }
    }

    /* ---- the project page ---- */

    public function test_film_shows_as_a_poster_until_played_and_bad_links_are_left_out(): void
    {
        $project = $this->project(['media' => [
            ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'caption_en' => 'The site, a year in', 'caption_ku' => 'شوێنەکە، ساڵێک دواتر'],
            ['url' => 'https://example.com/not-a-player'],
        ]]);

        $page = $this->get('/projects/'.$project->slug)->assertOk();
        $page->assertSee('id="film"', false)
            ->assertSee('data-embed="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertSee('The site, a year in')
            ->assertDontSee('not-a-player')
            ->assertDontSee('<iframe', false);       // nothing loads from YouTube until pressed

        $this->get('/ku/projects/'.$project->slug)->assertSee('شوێنەکە، ساڵێک دواتر');
    }

    public function test_render_vs_built_needs_both_images(): void
    {
        Storage::fake('public');
        $this->jpeg('projects/compare/render.jpg');
        $this->jpeg('projects/compare/photo.jpg');

        $half = $this->project(['compare_before' => 'projects/compare/render.jpg']);
        $this->get('/projects/'.$half->slug)->assertDontSee('pp-compare__frame', false);

        $both = $this->project(['name' => 'Both', 'compare_before' => 'projects/compare/render.jpg', 'compare_after' => 'projects/compare/photo.jpg']);
        // saved like any project photo: light WebP copies are made of both
        $this->assertTrue(Storage::disk('public')->exists('projects/lg/render.webp'));
        $this->assertTrue(Storage::disk('public')->exists('projects/lg/photo.webp'));
        $this->get('/projects/'.$both->slug)
            ->assertSee('pp-compare__frame', false)
            ->assertSee('projects/lg/render.webp', false)
            ->assertSee('projects/lg/photo.webp', false);
    }

    public function test_progress_is_shown_newest_first_with_its_photos(): void
    {
        Storage::fake('public');
        $this->jpeg('projects/updates/slab.jpg');
        $project = $this->project();
        $project->updates()->create(['date' => '2026-03-02', 'title_en' => 'Foundations poured']);
        $project->updates()->create(['date' => '2026-09-14', 'title_en' => 'Roof slab cast', 'title_ku' => 'سەقف داڕێژرا',
            'images' => ['projects/updates/slab.jpg']]);

        $this->assertTrue(Storage::disk('public')->exists('projects/thumb/slab.webp'), 'update photos get derivatives too');

        $html = $this->get('/projects/'.$project->slug)->assertOk()
            ->assertSee('Updated September 2026')
            ->assertSee('14 September 2026')
            ->getContent();
        $this->assertLessThan(strpos($html, 'Foundations poured'), strpos($html, 'Roof slab cast'), 'newest first');
        $this->assertStringContainsString('class="pp-step__photos" data-images=', $html);

        $this->get('/ku/projects/'.$project->slug)
            ->assertSee('سەقف داڕێژرا')
            ->assertSee('١٤ی ئەیلوولی ٢٠٢٦');
    }

    public function test_dates_are_written_in_the_readers_language(): void
    {
        $d = Carbon::parse('2026-10-03');
        $this->assertSame('3 October 2026', site_date($d, 'en'));
        $this->assertSame('October 2026', site_date($d, 'en', true));
        $this->assertSame('٣ی تشرینی یەکەمی ٢٠٢٦', site_date($d, 'ku'));
        $this->assertSame('تشرینی یەکەمی ٢٠٢٦', site_date($d, 'ku', true));
    }

    /* ---- the homepage overlay ---- */

    public function test_the_overlay_knows_what_each_project_page_has(): void
    {
        $plain = $this->project(['name' => 'Plain']);
        $rich = $this->project(['name' => 'Rich', 'media' => [['url' => 'https://youtu.be/dQw4w9WgXcQ']],
            'compare_before' => 'projects/compare/a.jpg', 'compare_after' => 'projects/compare/b.jpg']);
        ProjectUpdate::create(['project_id' => $rich->id, 'date' => '2026-01-01']);

        $projects = collect($this->get('/')->assertOk()->viewData('payload')['projects'])->keyBy('slug');
        $this->assertSame(['film' => false, 'compare' => false, 'progress' => false], $projects[$plain->slug]['extras']);
        $this->assertSame(['film' => true, 'compare' => true, 'progress' => true], $projects[$rich->slug]['extras']);
    }

    /* ---- the admin ---- */

    public function test_the_project_editor_has_the_new_sections(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password']);
        $project = $this->project();
        $project->updates()->create(['date' => '2026-05-05', 'title_en' => 'Columns up']);

        $this->actingAs($admin)->get(ProjectResource::getUrl('edit', ['record' => $project]))
            ->assertOk()
            ->assertSee('Render vs. built')
            ->assertSee('Film &amp; 360° tours', false);

        // the progress table loads after the page (Filament relation managers are lazy)
        $this->actingAs($admin);
        Livewire::test(UpdatesRelationManager::class, ['ownerRecord' => $project, 'pageClass' => EditProject::class])
            ->assertSee('Columns up')
            ->assertSee('Add progress update');
    }
}
