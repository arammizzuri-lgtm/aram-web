<?php

namespace Tests\Feature;

use App\Filament\Resources\Drawings\DrawingResource;
use App\Filament\Resources\Drawings\Pages\CreateDrawing;
use App\Filament\Resources\Drawings\Pages\EditDrawing;
use App\Models\Drawing;
use App\Models\Essay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Drawings traced in the admin (App\Models\Drawing): a correction to an essay's walls, live or
 * taken back, a new plan shown by an essay's "Drawing" section, and the scan kept private.
 */
class DrawingsTest extends TestCase
{
    use RefreshDatabase;

    private const ORIGINAL = 'M1314 8L1314 34';   // the start of House 318's traced walls

    private function admin(): void
    {
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password']));
    }

    public function test_the_walls_read_back_as_they_were_written(): void
    {
        $wall = Drawing::code()['h318']['d']['wall'];
        $outlines = Drawing::outlines($wall);
        $this->assertCount(10, $outlines);
        $this->assertSame($wall, (new Drawing(['data' => ['walls' => $outlines]]))->layers()['wall']);
        $this->assertSame([[0.0, 0.0], [10.0, 0.0], [10.0, 5.0]], Drawing::outlines('M0 0H10V5Z')[0]);
    }

    public function test_a_live_correction_replaces_the_walls_and_keeps_the_doors_until_it_is_taken_back(): void
    {
        $fix = Drawing::create(['title_en' => 'House 318, walls again', 'base' => 'h318', 'is_live' => true, 'data' => [
            'walls' => [[[0, 0], [500, 0], [500, 400], [0, 400]], [[40, 40], [460, 40], [460, 360], [40, 360]]],
            'openings' => [['k' => 'window', 'a' => [100, 20], 'b' => [180, 20], 't' => 40]],
            'rooms' => [['x' => 250, 'y' => 200, 'en' => 'Store', 'ku' => 'کۆگا', 'ar' => '']],
        ]]);

        $html = $this->get('/journal/citadel-house-plans')->assertOk()->getContent();
        $this->assertStringContainsString('d="M0 0L500 0L500 400L0 400ZM40 40L460 40L460 360L40 360Z"', $html);
        $this->assertStringNotContainsString(self::ORIGINAL, $html);
        // the original's doors stay, the new window is cut through the new wall
        $this->assertStringContainsString('M76.5 602L76.5 507', $html);
        $this->assertStringContainsString('<path class="d-cut" d="M100 -3L180 -3L180 43L100 43Z"/>', $html);
        $this->assertStringContainsString('>Store</text>', $html);
        $this->get('/ku/journal/citadel-house-plans')->assertOk()->assertSee('>کۆگا</text>', false);
        $this->get('/ar/journal/citadel-house-plans')->assertOk()->assertSee('>Store</text>', false);   // English where a name is not given

        $fix->update(['is_live' => false]);
        $this->get('/journal/citadel-house-plans')->assertOk()->assertSee(self::ORIGINAL, false)->assertDontSee('>Store</text>', false);
    }

    public function test_an_essay_shows_a_traced_plan_or_one_of_the_essays_drawings(): void
    {
        $plan = Drawing::create(['title_en' => 'A courtyard house', 'data' => [
            'walls' => [[[0, 0], [800, 0], [800, 600], [0, 600]], [[50, 50], [750, 50], [750, 550], [50, 550]]],
            'openings' => [['k' => 'door', 'a' => [300, 575], 'b' => [390, 575], 't' => 50], ['k' => 'gap', 'a' => [25, 200], 'b' => [25, 300], 't' => 50]],
            'lines' => [['g' => 'stair', 'p' => [[600, 100], [700, 100]]], ['g' => 'nope', 'p' => [[0, 0], [1, 1]]]],
            'rooms' => [['x' => 400, 'y' => 300, 'en' => '@court'], ['x' => 600, 'y' => 200, 'en' => 'Diwan', 'ku' => 'دیوەخان']],
            'north' => 0,
        ]]);
        $this->assertArrayHasKey('db:'.$plan->id, Drawing::options());
        $this->assertArrayHasKey('code:h318', Drawing::options());
        $this->assertNull(Drawing::resolve('db:999'));
        $this->assertNull((new Drawing(['data' => []]))->toDwg());

        Essay::create(['slug' => 'two-houses', 'date' => '2026-11-17', 'title_en' => 'Two houses', 'is_published' => true, 'sections' => [
            ['type' => 'drawing', 'data' => ['drawing' => 'db:'.$plan->id, 'caption_en' => 'Our tracing', 'caption_ku' => 'نەخشەکەمان']],
            ['type' => 'drawing', 'data' => ['drawing' => 'code:h318']],
            ['type' => 'drawing', 'data' => ['drawing' => 'db:999']],
        ]]);

        $html = $this->get('/journal/two-houses')->assertOk()->assertSee('Our tracing')->getContent();
        $this->assertSame(2, substr_count($html, '<svg class="ja-dwg"'));
        $this->assertStringContainsString('class="d-leaf dl"', $html);
        $this->assertStringContainsString('class="d-stair dl"', $html);
        $this->assertStringContainsString('>COURT</text>', $html);
        $this->assertStringContainsString('>Diwan</text>', $html);
        $this->assertStringContainsString('class="d-north dl"', $html);
        $this->assertStringContainsString(self::ORIGINAL, $html);
        $this->assertStringContainsString('journal-drawing.js', $html);
        $this->get('/ku/journal/two-houses')->assertOk()->assertSee('نەخشەکەمان')->assertSee('>دیوەخان</text>', false)->assertSee('>حەوشە</text>', false);
    }

    public function test_the_scan_is_only_for_the_admin(): void
    {
        Storage::fake(Drawing::DISK);
        Storage::disk(Drawing::DISK)->put('drawing-scans/h318.png', UploadedFile::fake()->image('h318.png', 40, 30)->getContent());
        $d = Drawing::create(['title_en' => 'House 318', 'base' => 'h318', 'scan' => 'drawing-scans/h318.png']);
        $url = route('filament.studio-aks-panel.drawing-scan', $d);

        $this->get($url)->assertRedirect();
        $this->assertStringNotContainsString('drawing-scans', $this->get('/journal/citadel-house-plans')->getContent());

        $this->admin();
        $res = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString('private', $res->headers->get('Cache-Control'));

        // the tracer, with the scan and the original's walls to start from
        $this->get(DrawingResource::getUrl('edit', ['record' => $d]))->assertOk()
            ->assertSee('drawingTracer(', false)->assertSee('Line up the scan')->assertSee('Open the essay');

        $d->delete();
        Storage::disk(Drawing::DISK)->assertMissing('drawing-scans/h318.png');
    }

    public function test_the_admin_makes_a_drawing_and_takes_a_correction_back(): void
    {
        $this->admin();
        $this->get(DrawingResource::getUrl('index'))->assertOk();
        $this->get(DrawingResource::getUrl('create'))->assertOk()->assertSee('Survey scan (private)');

        Livewire::test(CreateDrawing::class)
            ->fillForm(['title_en' => 'House 318, walls again', 'base' => 'h318'])
            ->call('create')->assertHasNoFormErrors();
        $d = Drawing::sole();
        $this->assertSame('h318', $d->base);

        $d->update(['is_live' => true]);
        Livewire::test(EditDrawing::class, ['record' => $d->getKey()])
            ->callAction('original');
        $this->assertFalse($d->fresh()->is_live);
        $this->assertSame('h318', $d->fresh()->base);   // the base stays what it was made for
    }
}
