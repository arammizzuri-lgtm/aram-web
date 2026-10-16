<?php

namespace Tests\Feature;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Mail\NewEnquiry;
use App\Models\ContactMessage;
use App\Models\Sketch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * "Draw it for us": a sketch drawn on the projects page's blank sheet or on
 * the enquiry form, kept at an address no one could guess, and sent to the
 * studio with an enquiry or on WhatsApp.
 */
class SketchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Sketch::DISK);
    }

    /** A drawing as the paper makes it: a PNG, 1600 × 1200. */
    private function drawing(int $w = 1600, int $h = 1200, string $type = 'png'): UploadedFile
    {
        $gd = imagecreatetruecolor($w, $h);
        imagefilledrectangle($gd, 0, 0, $w, $h, imagecolorallocate($gd, 244, 241, 232));
        imageline($gd, 100, 100, 600, 400, imagecolorallocate($gd, 29, 29, 27));
        ob_start();
        $type === 'png' ? imagepng($gd) : imagejpeg($gd);
        $bytes = (string) ob_get_clean();
        imagedestroy($gd);

        return UploadedFile::fake()->createWithContent('sketch.'.$type, $bytes);
    }

    private function keep(): array
    {
        return $this->post('/sketches', ['image' => $this->drawing()], ['Accept' => 'application/json'])
            ->assertOk()->json();
    }

    public function test_a_sketch_is_kept_and_seen_only_at_its_own_address(): void
    {
        $kept = $this->keep();
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}$/', $kept['token']);
        $this->assertSame(url('/sketches/'.$kept['token']), $kept['url']);

        $sketch = Sketch::where('token', $kept['token'])->firstOrFail();
        Storage::disk(Sketch::DISK)->assertExists($sketch->path);
        $this->assertNull($sketch->contact_message_id);

        $this->get('/sketches/'.$kept['token'])->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Robots-Tag', 'noindex');
        $this->get('/sketches/'.str_repeat('a', 40))->assertNotFound();
    }

    public function test_only_a_drawing_is_kept(): void
    {
        $json = ['Accept' => 'application/json'];
        $this->post('/sketches', ['image' => $this->drawing(type: 'jpg')], $json)->assertStatus(422);
        $this->post('/sketches', ['image' => $this->drawing(40, 30)], $json)->assertStatus(422);       // a dot, not a sheet
        $this->post('/sketches', ['image' => $this->drawing(4000, 3000)], $json)->assertStatus(422);   // not from the paper
        $this->post('/sketches', ['image' => UploadedFile::fake()->createWithContent('sketch.png', '<?php echo 1;')], $json)->assertStatus(422);
        $this->post('/sketches', [], $json)->assertStatus(422);
        $this->assertSame(0, Sketch::count());
    }

    public function test_a_sketch_goes_with_the_enquiry_to_the_studio(): void
    {
        Mail::fake();
        $kept = $this->keep();

        $this->postJson('/contact', ['name' => 'Shilan', 'phone' => '0750 123 4567', 'message' => 'Like this', 'sketch' => $kept['token']])
            ->assertOk();

        $enquiry = ContactMessage::firstOrFail();
        $this->assertSame($kept['token'], $enquiry->sketch->token);

        Mail::assertSent(NewEnquiry::class, fn (NewEnquiry $mail) => str_contains($mail->render(), $kept['url']));

        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password']);
        $this->actingAs($admin)->get(ContactMessageResource::getUrl('edit', ['record' => $enquiry]))->assertOk()
            ->assertSee('Their sketch')->assertSee($kept['url'], false);

        // a token that is not one is turned away; one already sent is not taken again
        $this->postJson('/contact', ['name' => 'X', 'phone' => '0750 000 0000', 'sketch' => 'not-a-token'])->assertStatus(422);
        $this->postJson('/contact', ['name' => 'Y', 'phone' => '0750 000 0001', 'sketch' => $kept['token']])->assertOk();
        $this->assertSame($enquiry->id, Sketch::firstOrFail()->contact_message_id);
    }

    public function test_sketches_never_sent_are_let_go_after_three_months(): void
    {
        $old = Sketch::keep('png');
        $old->forceFill(['created_at' => now()->subDays(Sketch::KEEP_DAYS + 1)])->save();
        $sent = Sketch::keep('png');
        $sent->forceFill(['created_at' => now()->subDays(400), 'contact_message_id' => ContactMessage::create(['name' => 'A', 'phone' => '1', 'message' => ''])->id])->save();

        $this->keep();                                                      // the next one tidies up

        $this->assertNull(Sketch::find($old->id));
        Storage::disk(Sketch::DISK)->assertMissing($old->path);
        $this->assertNotNull(Sketch::find($sent->id));
        $this->assertSame(2, Sketch::count());
    }

    public function test_the_paper_is_offered_on_the_projects_page_and_the_enquiry_form(): void
    {
        $this->get('/projects')->assertOk()
            ->assertSee('<meta name="csrf-token"', false)
            ->assertSee('data-sketch-open="send"', false)
            ->assertSee('Draw it for us')
            ->assertSee('sketch.js');

        $this->get('/')->assertOk()
            ->assertSee('id="cf-sketch"', false)
            ->assertSee('data-sketch-open="attach"', false)
            ->assertSee('"attach":"Attach to my message"', false)
            ->assertSee('"attach":"بیخەرە سەر نامەکەم"', false)
            ->assertSee('"store":"'.url('/sketches').'"', false);
    }
}
