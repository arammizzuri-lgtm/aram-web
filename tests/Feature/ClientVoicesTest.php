<?php

namespace Tests\Feature;

use App\Filament\Resources\Testimonials\Pages\CreateTestimonial;
use App\Filament\Resources\Testimonials\Pages\EditTestimonial;
use App\Models\Client;
use App\Models\Project;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\PortraitDrawing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Client quotes with the client's own voice and a pencil portrait drawn from
 * their photo: the drawing (made of strokes, found in the admin's browser),
 * the files kept and dropped, the agreement that has to come first, and the
 * photo that is never published.
 */
class ClientVoicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake(Testimonial::PHOTO_DISK);
    }

    private function photoBytes(): string
    {
        $gd = imagecreatetruecolor(600, 600);
        imagefill($gd, 0, 0, imagecolorallocate($gd, 225, 222, 215));
        imagefilledellipse($gd, 300, 280, 260, 330, imagecolorallocate($gd, 205, 160, 130));
        ob_start();
        imagejpeg($gd, null, 90);

        return (string) ob_get_clean();
    }

    /** Strokes as portrait-drawer.js sends them: guide lines, lines, hatching. */
    private function strokes(): array
    {
        return ['size' => 512, 'strokes' => [
            ['k' => 'guide', 'w' => 1.3, 'o' => 0.3, 'p' => [256, 60, 256.4, 470]],
            ['k' => 'oval', 'w' => 2.4, 'o' => 0.85, 'p' => [150, 200, 160, 320.5, 200, 400, 256, 430]],
            ['k' => 'eyes', 'w' => 2, 'o' => 0.9, 'p' => [190, 230, 210, 222, 232, 230]],
            ['k' => 'hatch', 'w' => 1.2, 'o' => 0.4, 'p' => [300, 380, 288, 400]],
        ]];
    }

    private function quote(array $attrs = []): Testimonial
    {
        Storage::disk(Testimonial::PHOTO_DISK)->put('testimonials/photos/hawre.jpg', $this->photoBytes());
        Storage::disk('public')->put('testimonials/voices/hawre.opus', 'OggS fake voice');

        return Testimonial::create($attrs + [
            'quote_en' => 'They listened before they drew.', 'person_name' => 'Hawre Aziz', 'person_role_en' => 'Owner',
            'portrait' => 'testimonials/photos/hawre.jpg', 'voice' => 'testimonials/voices/hawre.opus',
            'media_consent' => true, 'is_published' => true,
        ]);
    }

    private function drawn(array $attrs = []): Testimonial
    {
        $quote = $this->quote($attrs);
        $quote->storeDrawing($this->strokes());

        return $quote;
    }

    /* ---- the drawing ---- */

    public function test_strokes_become_a_drawing_drawn_in_their_order(): void
    {
        $svg = PortraitDrawing::svg($this->strokes());

        $this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"', $svg);
        $this->assertSame(4, substr_count($svg, '<path '));
        // in order, each with its kind: a guide, two lines, hatching
        $this->assertMatchesRegularExpression('/data-k="g".*data-k="l".*data-k="l".*data-k="h"/', $svg);
        $this->assertStringContainsString('d="M256 60L256.4 470"', $svg);
        $this->assertStringContainsString('d="M150 200Q160 320.5 180 360.3Q200 400 228 415L256 430"', $svg);
        $this->assertStringContainsString('stroke-width="2.4"', $svg);
    }

    public function test_nothing_but_numbers_gets_into_a_drawing(): void
    {
        $bad = [
            'markup for a point' => ['k' => 'oval', 'w' => 1, 'o' => 1, 'p' => [1, 2, '3"/><script>alert(1)</script>', 4]],
            'an unknown kind' => ['k' => '"><script>', 'w' => 1, 'o' => 1, 'p' => [1, 2, 3, 4]],
            'a lone point' => ['k' => 'oval', 'w' => 1, 'o' => 1, 'p' => [1, 2]],
            'an odd count' => ['k' => 'oval', 'w' => 1, 'o' => 1, 'p' => [1, 2, 3]],
        ];
        foreach ($bad as $why => $stroke) {
            try {
                PortraitDrawing::svg(['size' => 512, 'strokes' => [$stroke]]);
                $this->fail("Accepted $why.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(InvalidArgumentException::class);
        PortraitDrawing::svg(['size' => 999, 'strokes' => $this->strokes()['strokes']]);
    }

    public function test_odd_widths_and_far_points_are_kept_in_bounds(): void
    {
        $svg = PortraitDrawing::svg(['size' => 512, 'strokes' => [
            ['k' => 'face', 'w' => 400, 'o' => 7, 'p' => [-9000, 10, 9000.75, 20]],
        ]]);

        $this->assertStringContainsString('d="M-64 10L576 20" stroke-width="6" stroke-opacity="1"', $svg);
    }

    /* ---- the files ---- */

    public function test_a_drawing_is_kept_and_the_photo_stays_private(): void
    {
        $quote = $this->quote();
        $this->assertTrue($quote->needsDrawing());
        $this->assertNull($quote->sketchUrl());

        $quote->storeDrawing($this->strokes(), 'strong');

        $this->assertFalse($quote->needsDrawing());
        $this->assertStringEndsWith('.svg', $quote->portrait_sketch);
        $this->assertSame('strong', $quote->sketch_detail);
        Storage::disk('public')->assertExists($quote->portrait_sketch);
        Storage::disk(Testimonial::PHOTO_DISK)->assertExists('testimonials/photos/hawre.jpg');
        Storage::disk('public')->assertMissing('testimonials/photos/hawre.jpg');
    }

    public function test_drawing_again_replaces_the_old_drawing(): void
    {
        $quote = $this->drawn();
        $first = $quote->portrait_sketch;

        $quote->storeDrawing($this->strokes(), 'soft');

        $this->assertNotSame($first, $quote->portrait_sketch);
        Storage::disk('public')->assertExists($quote->portrait_sketch);
        Storage::disk('public')->assertMissing($first);
    }

    public function test_a_new_photo_waits_to_be_drawn_and_the_old_drawing_goes(): void
    {
        $quote = $this->drawn();
        $drawing = $quote->portrait_sketch;
        Storage::disk(Testimonial::PHOTO_DISK)->put('testimonials/photos/new.jpg', $this->photoBytes());

        $quote->update(['portrait' => 'testimonials/photos/new.jpg']);

        $this->assertTrue($quote->needsDrawing());
        $this->assertNull($quote->sketchUrl());
        Storage::disk('public')->assertMissing($drawing);
        Storage::disk(Testimonial::PHOTO_DISK)->assertMissing('testimonials/photos/hawre.jpg');
    }

    public function test_files_removed_or_replaced_in_the_admin_are_deleted(): void
    {
        $quote = $this->drawn();
        $drawing = $quote->portrait_sketch;
        Storage::disk('public')->put('testimonials/voices/second.m4a', 'voice');

        $quote->update(['portrait' => null, 'voice' => 'testimonials/voices/second.m4a']);

        $this->assertNull($quote->portrait_sketch);
        Storage::disk('public')->assertMissing($drawing);
        Storage::disk(Testimonial::PHOTO_DISK)->assertMissing('testimonials/photos/hawre.jpg');
        Storage::disk('public')->assertMissing('testimonials/voices/hawre.opus');
        Storage::disk('public')->assertExists('testimonials/voices/second.m4a');
    }

    public function test_deleting_a_quote_deletes_its_voice_photo_and_drawing(): void
    {
        $quote = $this->drawn();
        $drawing = $quote->portrait_sketch;

        $quote->delete();

        Storage::disk('public')->assertMissing([$drawing, 'testimonials/voices/hawre.opus']);
        Storage::disk(Testimonial::PHOTO_DISK)->assertMissing('testimonials/photos/hawre.jpg');
    }

    /* ---- the pages ---- */

    public function test_nothing_is_shown_until_the_client_has_agreed(): void
    {
        $quote = $this->drawn(['media_consent' => false]);

        // not their voice, then: at most a computer voice reading their words
        $this->get('/')->assertOk()
            ->assertSee('They listened before they drew.')
            ->assertDontSee($quote->portrait_sketch)
            ->assertDontSee('hawre.opus')
            ->assertSee('data-read', false);

        $quote->update(['media_consent' => true]);

        $this->get('/')
            ->assertSee($quote->portrait_sketch)
            ->assertSee('testimonials/voices/hawre.opus')
            ->assertSee('audio/ogg; codecs=opus')
            ->assertSee('pencil.js')
            ->assertSee('voices.js')
            ->assertSee('Listen to Hawre Aziz')
            // the photo itself is never on the page
            ->assertDontSee('photos/hawre.jpg');
    }

    public function test_a_photo_not_yet_drawn_shows_no_portrait(): void
    {
        $this->quote();

        $this->get('/')->assertOk()->assertDontSee('cv__portrait', false)->assertSee('hawre.opus');
    }

    public function test_the_company_goes_round_the_ring_with_its_logo_on_the_seal(): void
    {
        $client = Client::create(['name' => 'Kar Group', 'logo_mono' => 'clients/mono/kar.png', 'is_published' => true]);
        $this->drawn(['client_id' => $client->id]);

        $this->get('/')
            ->assertSee('KAR GROUP · KAR GROUP', false)
            ->assertSee('clients/mono/kar.png')
            ->assertSee('cv__seal', false);

        // no company: a plain scale of ticks instead
        Testimonial::query()->update(['client_id' => null]);
        $this->get('/')->assertDontSee('cv__seal', false)->assertSee('cv__ticks', false);
    }

    public function test_the_project_page_puts_the_portrait_and_voice_beside_the_quote(): void
    {
        $project = Project::create([
            'name' => 'Mountain Villa', 'category' => 'residential', 'status' => 'Completed', 'size' => 'default',
            'imgs' => ['https://example.com/cover.jpg'], 'is_published' => true,
        ]);
        $quote = $this->drawn(['project_id' => $project->id]);

        $this->get('/projects/'.$project->slug)->assertOk()
            ->assertSee('pp-quote-card has-cv', false)
            ->assertSee($quote->portrait_sketch)
            ->assertSee('voices.js');
    }

    public function test_the_page_names_the_kind_of_recording_for_the_browser_to_check(): void
    {
        $types = [
            'a.opus' => 'audio/ogg; codecs=opus', 'a.ogg' => 'audio/ogg', 'a.mp3' => 'audio/mpeg',
            'a.m4a' => 'audio/mp4', 'a.wav' => 'audio/wav', 'a.xyz' => '',
        ];
        foreach ($types as $file => $type) {
            $this->assertSame($type, (new Testimonial(['voice' => $file]))->voiceType(), $file);
        }
    }

    /* ---- the admin ---- */

    public function test_the_photo_is_served_only_inside_the_admin(): void
    {
        $quote = $this->quote();
        $url = route('filament.studio-aks-panel.testimonial-photo', $quote);

        $this->get($url)->assertRedirect();

        $this->actingAs(User::factory()->create());
        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $quote->update(['portrait' => null]);
        $this->get($url)->assertNotFound();
    }

    public function test_the_admin_draws_an_uploaded_photo_and_keeps_the_strokes(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateTestimonial::class)
            ->fillForm([
                'quote_en' => 'Calm, precise and honest about cost.', 'person_name' => 'Karwan',
                'portrait' => UploadedFile::fake()->createWithContent('karwan.jpg', $this->photoBytes()),
                'media_consent' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $quote = Testimonial::sole();
        $this->assertStringStartsWith('testimonials/photos/', $quote->portrait);
        Storage::disk(Testimonial::PHOTO_DISK)->assertExists($quote->portrait);
        $this->assertTrue($quote->needsDrawing());

        // the edit page carries the drawing panel, pointed at the private photo
        Livewire::test(EditTestimonial::class, ['record' => $quote->getRouteKey()])
            ->assertSee('The drawing')
            ->assertSee('portrait-drawer-admin.js')
            ->assertSee(route('filament.studio-aks-panel.testimonial-photo', $quote, false))
            ->call('saveDrawing', $this->strokes(), 'strong')
            ->assertNotified('The portrait is drawn and saved');

        $quote->refresh();
        $this->assertFalse($quote->needsDrawing());
        $this->assertSame('strong', $quote->sketch_detail);
        Storage::disk('public')->assertExists($quote->portrait_sketch);

        // anything but strokes is turned away, and the drawing stays as it was
        $kept = $quote->portrait_sketch;
        Livewire::test(EditTestimonial::class, ['record' => $quote->getRouteKey()])
            ->call('saveDrawing', ['size' => 512, 'strokes' => [['k' => 'oval', 'w' => 1, 'o' => 1, 'p' => ['<svg onload=alert(1)>', 1, 2, 3]]]])
            ->assertNotified('That drawing could not be kept — draw it again');
        $this->assertSame($kept, $quote->fresh()->portrait_sketch);
    }
}
