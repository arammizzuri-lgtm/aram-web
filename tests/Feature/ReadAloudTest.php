<?php

namespace Tests\Feature;

use App\Filament\Pages\ReadAloudSettings;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\ReadAloud;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Read aloud" for quotes without a voice note: English is left to the
 * visitor's device, Kurdish is read once on save by kurdishtts.com — and the
 * key to that service never leaves the server.
 */
class ReadAloudTest extends TestCase
{
    use RefreshDatabase;

    private const MP3 = "ID3\x03\x00\x00\x00\x00\x00\x00";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake(Testimonial::PHOTO_DISK);
        Http::preventStrayRequests();
    }

    private function withKey(): void
    {
        ReadAloud::storeKey('ktts-secret-123');
        SiteSetting::put(ReadAloud::SPEAKER, 'sorani_1559', 'reader');
        SiteSetting::flushCache();
    }

    private function fakeService(int $status = 200): void
    {
        Http::fake([
            ReadAloud::ENDPOINT => $status === 200
                ? Http::response(self::MP3.str_repeat("\xFF\xFB\x90\x00", 200), 200, ['Content-Type' => 'audio/mpeg'])
                : Http::response(['error' => 'Invalid API key'], $status),
        ]);
    }

    private function quote(array $attrs = []): Testimonial
    {
        return Testimonial::create($attrs + [
            'quote_en' => 'Clear drawings and honest numbers.',
            'quote_ku' => 'نەخشەی ڕوون و ژمارەی ڕاستگۆ.',
            'person_name' => 'Dilan', 'is_published' => true,
        ]);
    }

    public function test_kurdish_words_are_read_once_when_the_quote_is_saved(): void
    {
        $this->withKey();
        $this->fakeService();

        $quote = $this->quote();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r->url() === ReadAloud::ENDPOINT
            && $r->hasHeader('x-api-key', 'ktts-secret-123')
            && $r['text'] === 'نەخشەی ڕوون و ژمارەی ڕاستگۆ.'
            && $r['speaker_id'] === 'sorani_1559' && $r['format'] === 'mp3' && $r['dialect'] === 'sorani');
        Storage::disk('public')->assertExists($quote->reading_ku);
        $this->assertStringEndsWith('.mp3', $quote->reading_ku);

        // saved again with the same words: nothing more to read
        $quote->update(['person_role_en' => 'Client']);
        $quote->update(['quote_en' => 'Clear drawings, honest numbers.']);
        Http::assertSentCount(1);

        // new Kurdish words: read again, and the old reading goes
        $first = $quote->reading_ku;
        $quote->update(['quote_ku' => 'نەخشەی ڕوون.']);
        Http::assertSentCount(2);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($quote->reading_ku);
    }

    public function test_the_page_offers_reading_and_says_it_is_a_computer_voice(): void
    {
        $this->withKey();
        $this->fakeService();
        $quote = $this->quote();

        // the homepage carries both languages for its toggle; the recording is
        // only fetched if "Read aloud" is pressed in Kurdish
        $this->get('/')->assertOk()
            ->assertSee('data-read', false)
            ->assertSee('computer voice')
            ->assertSee('voices.js');
        $this->get('/ku')->assertOk()
            ->assertSee('data-src-ku="'.asset('storage/'.$quote->reading_ku).'"', false)
            ->assertSee('دەنگی کۆمپیوتەر')
            ->assertDontSee('ktts-secret-123');
    }

    public function test_without_a_key_english_is_still_offered_and_nothing_is_sent(): void
    {
        $quote = $this->quote();

        Http::assertNothingSent();
        $this->assertNull($quote->reading_ku);
        $this->get('/')->assertSee('data-read', false);
        $this->get('/ku')->assertSee('data-read', false)->assertDontSee('data-src-ku', false);
    }

    public function test_a_refused_request_leaves_the_quote_saved_with_a_reason(): void
    {
        $this->withKey();
        $this->fakeService(401);

        $quote = $this->quote();

        $this->assertTrue($quote->exists);
        $this->assertNull($quote->reading_ku);
        $this->assertStringContainsString('API key was not accepted', (string) $quote->refreshReading());
        $this->assertSame([], Storage::disk('public')->allFiles('testimonials/readings'));
    }

    public function test_the_clients_own_voice_is_never_read_over(): void
    {
        $this->withKey();
        $this->fakeService();
        $quote = $this->quote();
        $reading = $quote->reading_ku;

        Storage::disk('public')->put('testimonials/voices/dilan.m4a', 'voice');
        $quote->update(['voice' => 'testimonials/voices/dilan.m4a', 'media_consent' => true]);

        $this->assertNull($quote->reading_ku);
        Storage::disk('public')->assertMissing($reading);
        $this->assertFalse($quote->canReadAloud());
        $this->get('/')->assertDontSee('data-read', false)->assertSee('dilan.m4a');
    }

    public function test_a_reading_of_other_words_is_never_played(): void
    {
        $this->withKey();
        $this->fakeService();
        $quote = $this->quote();
        $this->assertNotNull($quote->readingUrl());

        // the words changed while the service could not be reached
        Http::fake([ReadAloud::ENDPOINT => Http::failedConnection()]);
        $quote->update(['quote_ku' => 'وشەی نوێ.']);

        $this->assertNull($quote->fresh()->readingUrl());
        $this->get('/ku')->assertDontSee('data-src-ku', false);

        // a new voice, though, still reads the same words: kept until read again
        $quote->update(['quote_ku' => 'نەخشەی ڕوون و ژمارەی ڕاستگۆ.']);
        SiteSetting::put(ReadAloud::SPEAKER, 'sorani_2', 'reader');
        SiteSetting::flushCache();
        $this->assertNotNull($quote->fresh()->readingUrl());
    }

    public function test_switched_off_there_is_no_button(): void
    {
        SiteSetting::put(ReadAloud::ENABLED, '0', 'reader');
        SiteSetting::flushCache();
        $this->quote();

        $this->get('/')->assertDontSee('data-read', false)->assertDontSee('voices.js');
    }

    public function test_the_key_is_kept_encrypted_and_out_of_public_settings(): void
    {
        $this->withKey();

        $stored = SiteSetting::where('key', ReadAloud::API_KEY)->value('value');
        $this->assertNotSame('ktts-secret-123', $stored);
        $this->assertStringNotContainsString('ktts-secret-123', $stored);
        $this->assertArrayNotHasKey(ReadAloud::API_KEY, SiteSetting::publicKeyed());
        $this->assertArrayHasKey(ReadAloud::SPEAKER, SiteSetting::publicKeyed());
    }

    public function test_the_sorani_voices_are_listed_women_first(): void
    {
        Http::fake([ReadAloud::SPEAKERS_ENDPOINT.'*' => Http::response(['speakers' => [
            ['id' => 'sorani_1', 'name' => 'Male 1', 'dialect' => 'sorani', 'gender' => 'male'],
            ['id' => 'kurmanji_6', 'name' => 'Male 6', 'dialect' => 'kurmanji', 'gender' => 'male'],
            ['id' => 'sorani_1559', 'name' => 'Female 62', 'dialect' => 'sorani', 'gender' => 'female'],
            ['id' => 'sorani_1500', 'name' => 'Female 2', 'dialect' => 'sorani', 'gender' => 'female'],
        ]])]);
        Cache::flush();

        $this->assertSame(
            ['sorani_1500' => 'Female 2', 'sorani_1559' => 'Female 62', 'sorani_1' => 'Male 1'],
            ReadAloud::speakers(),
        );
    }

    public function test_the_admin_page_saves_the_voice_and_reads_every_kurdish_quote(): void
    {
        $this->actingAs(User::factory()->create());
        Http::fake([
            ReadAloud::SPEAKERS_ENDPOINT.'*' => Http::response(['speakers' => [
                ['id' => 'sorani_1500', 'name' => 'Female 2', 'dialect' => 'sorani', 'gender' => 'female'],
            ]]),
            ReadAloud::ENDPOINT => Http::response(self::MP3.str_repeat("\xFF\xFB", 300), 200, ['Content-Type' => 'audio/mpeg']),
        ]);
        $this->quote();                     // saved before there was a key: not read yet
        $this->quote(['quote_ku' => null, 'person_name' => 'Only English']);

        $this->get(ReadAloudSettings::getUrl())->assertOk()->assertSee('The Kurdish voice');

        Livewire::test(ReadAloudSettings::class)
            ->fillForm(['enabled' => true, 'api_key' => 'ktts-secret-123', 'speaker' => 'sorani_1500', 'speed' => '0.95'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->callAction('readAll');

        $this->assertTrue(ReadAloud::kurdishReady());
        $this->assertSame('sorani_1500', ReadAloud::speaker());
        $this->assertSame(0.95, ReadAloud::speed());
        $this->assertNotNull(Testimonial::where('person_name', 'Dilan')->value('reading_ku'));
        $this->assertNull(Testimonial::where('person_name', 'Only English')->value('reading_ku'));
        Http::assertSent(fn (Request $r) => $r->url() === ReadAloud::ENDPOINT && $r['speaker_id'] === 'sorani_1500' && $r['speed'] == 0.95);
    }
}
