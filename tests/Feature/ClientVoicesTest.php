<?php

namespace Tests\Feature;

use App\Filament\Resources\Testimonials\Pages\CreateTestimonial;
use App\Filament\Resources\Testimonials\Pages\EditTestimonial;
use App\Models\Client;
use App\Models\Project;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\PortraitSketch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Client quotes with the client's own voice and a pencil portrait drawn from
 * their photo: the drawing, the files kept and dropped, the agreement that
 * has to come first, and the photo that is never published.
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

    /** A photo with something to draw: a dark head and shoulders on a light wall. */
    private function photoBytes(): string
    {
        $gd = imagecreatetruecolor(800, 800);
        imagefill($gd, 0, 0, imagecolorallocate($gd, 225, 222, 215));
        imagefilledellipse($gd, 400, 820, 640, 420, imagecolorallocate($gd, 40, 45, 70));     // shoulders
        imagefilledellipse($gd, 400, 360, 300, 380, imagecolorallocate($gd, 205, 160, 130));  // face
        imagefilledellipse($gd, 400, 210, 310, 160, imagecolorallocate($gd, 35, 28, 22));     // hair
        foreach ([340, 460] as $x) {
            imagefilledellipse($gd, $x, 340, 44, 24, imagecolorallocate($gd, 30, 30, 30));     // eyes
        }
        imagefilledrectangle($gd, 360, 450, 440, 462, imagecolorallocate($gd, 120, 50, 50)); // mouth
        ob_start();
        imagejpeg($gd, null, 90);

        return (string) ob_get_clean();
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

    /* ---- the drawing ---- */

    public function test_a_photo_becomes_a_pencil_drawing_on_a_transparent_disc(): void
    {
        $dir = sys_get_temp_dir().'/sketch-'.uniqid();
        @mkdir($dir);
        file_put_contents("$dir/in.jpg", $this->photoBytes());

        $this->assertTrue(PortraitSketch::generate("$dir/in.jpg", "$dir/out.png"));
        $out = imagecreatefrompng("$dir/out.png");
        $this->assertSame([PortraitSketch::SIZE, PortraitSketch::SIZE], [imagesx($out), imagesy($out)]);

        // beyond the disc the paper is untouched
        foreach ([[2, 2], [557, 2], [2, 557], [557, 557]] as [$x, $y]) {
            $this->assertSame(127, (imagecolorat($out, $x, $y) >> 24) & 0x7F);
        }

        // within it there are lines, and they are graphite, not white
        $inked = 0;
        for ($y = 120; $y < 440; $y += 2) {
            for ($x = 120; $x < 440; $x += 2) {
                $c = imagecolorat($out, $x, $y);
                if ((($c >> 24) & 0x7F) < 64) {
                    $inked++;
                    $this->assertLessThan(80, ($c >> 16) & 0xFF);
                }
            }
        }
        $this->assertGreaterThan(500, $inked);

        // a harder pencil puts down more
        $this->assertTrue(PortraitSketch::generate("$dir/in.jpg", "$dir/soft.png", 'soft'));
        $this->assertTrue(PortraitSketch::generate("$dir/in.jpg", "$dir/strong.png", 'strong'));
        $this->assertGreaterThan($this->ink("$dir/soft.png"), $this->ink("$dir/strong.png"));

        array_map('unlink', glob("$dir/*"));
        rmdir($dir);
    }

    public function test_something_that_is_not_a_photo_gives_no_drawing(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'sketch');
        file_put_contents($path, 'not an image');

        $this->assertFalse(PortraitSketch::generate($path, $path.'.png'));
        $this->assertFileDoesNotExist($path.'.png');
        unlink($path);
    }

    private function ink(string $png): int
    {
        $im = imagecreatefrompng($png);
        $sum = 0;
        for ($y = 0; $y < imagesy($im); $y += 3) {
            for ($x = 0; $x < imagesx($im); $x += 3) {
                $sum += 127 - ((imagecolorat($im, $x, $y) >> 24) & 0x7F);
            }
        }

        return $sum;
    }

    /* ---- the files ---- */

    public function test_saving_a_photo_draws_it_and_keeps_the_photo_private(): void
    {
        $quote = $this->quote();

        $this->assertNotNull($quote->portrait_sketch);
        Storage::disk('public')->assertExists($quote->portrait_sketch);
        Storage::disk(Testimonial::PHOTO_DISK)->assertExists('testimonials/photos/hawre.jpg');
        Storage::disk('public')->assertMissing('testimonials/photos/hawre.jpg');
    }

    public function test_another_pencil_redraws_it_and_the_old_drawing_goes(): void
    {
        $quote = $this->quote();
        $first = $quote->portrait_sketch;

        $quote->update(['sketch_detail' => 'strong']);

        $this->assertNotSame($first, $quote->portrait_sketch);
        Storage::disk('public')->assertExists($quote->portrait_sketch);
        Storage::disk('public')->assertMissing($first);
    }

    public function test_files_removed_or_replaced_in_the_admin_are_deleted(): void
    {
        $quote = $this->quote();
        $sketch = $quote->portrait_sketch;
        Storage::disk('public')->put('testimonials/voices/second.m4a', 'voice');

        $quote->update(['portrait' => null, 'voice' => 'testimonials/voices/second.m4a']);

        $this->assertNull($quote->portrait_sketch);
        Storage::disk('public')->assertMissing($sketch);
        Storage::disk(Testimonial::PHOTO_DISK)->assertMissing('testimonials/photos/hawre.jpg');
        Storage::disk('public')->assertMissing('testimonials/voices/hawre.opus');
        Storage::disk('public')->assertExists('testimonials/voices/second.m4a');
    }

    public function test_deleting_a_quote_deletes_its_voice_photo_and_drawing(): void
    {
        $quote = $this->quote();
        $sketch = $quote->portrait_sketch;

        $quote->delete();

        Storage::disk('public')->assertMissing([$sketch, 'testimonials/voices/hawre.opus']);
        Storage::disk(Testimonial::PHOTO_DISK)->assertMissing('testimonials/photos/hawre.jpg');
    }

    /* ---- the pages ---- */

    public function test_nothing_is_shown_until_the_client_has_agreed(): void
    {
        $quote = $this->quote(['media_consent' => false]);

        $this->get('/')->assertOk()
            ->assertSee('They listened before they drew.')
            ->assertDontSee($quote->portrait_sketch)
            ->assertDontSee('hawre.opus')
            ->assertDontSee('voices.js');

        $quote->update(['media_consent' => true]);

        $this->get('/')
            ->assertSee($quote->portrait_sketch)
            ->assertSee('testimonials/voices/hawre.opus')
            ->assertSee('audio/ogg; codecs=opus')
            ->assertSee('voices.js')
            ->assertSee('Listen to Hawre Aziz')
            // the photo itself is never on the page
            ->assertDontSee('photos/hawre.jpg');
    }

    public function test_the_company_goes_round_the_ring_with_its_logo_on_the_seal(): void
    {
        $client = Client::create(['name' => 'Kar Group', 'logo_mono' => 'clients/mono/kar.png', 'is_published' => true]);
        $this->quote(['client_id' => $client->id]);

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
        $quote = $this->quote(['project_id' => $project->id]);

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

    public function test_a_photo_uploaded_in_the_admin_is_drawn_and_can_be_redrawn(): void
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
        Storage::disk('public')->assertExists($quote->portrait_sketch);
        $first = $quote->portrait_sketch;

        Livewire::test(EditTestimonial::class, ['record' => $quote->getRouteKey()])
            ->assertSee('The drawing')
            ->fillForm(['sketch_detail' => 'soft'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNotSame($first, $quote->fresh()->portrait_sketch);
        Storage::disk('public')->assertMissing($first);
    }
}
