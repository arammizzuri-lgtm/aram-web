<?php

namespace Tests\Feature;

use App\Filament\Resources\JobApplications\JobApplicationResource;
use App\Filament\Resources\JobOpenings\JobOpeningResource;
use App\Mail\NewApplication;
use App\Models\JobApplication;
use App\Models\JobOpening;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\StudioMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The careers page, applying to it with a CV, and the CV staying private.
 */
class CareersTest extends TestCase
{
    use RefreshDatabase;

    private function opening(array $attrs = []): JobOpening
    {
        return JobOpening::create($attrs + [
            'title_en' => 'Junior Architect', 'title_ku' => 'تەڵارسازی نوێ', 'type' => 'full-time',
            'location_en' => 'Erbil studio', 'summary_en' => 'Design with us.',
            'description_en' => "What you will do\n\n- Concept design\n- Drawings", 'is_open' => true,
        ]);
    }

    private function pdf(string $name = 'My CV.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << >>\n%%EOF");
    }

    public function test_the_page_lists_open_positions_in_both_languages(): void
    {
        $this->opening();
        $this->opening(['title_en' => 'Closed Role', 'is_open' => false]);
        $this->opening(['title_en' => 'Expired Role', 'closes_at' => now()->subDay()]);

        $this->get('/careers')->assertOk()
            ->assertSee('Junior Architect')->assertSee('Concept design')
            ->assertDontSee('Closed Role')->assertDontSee('Expired Role')
            ->assertSee('"@type":"JobPosting"', false)
            ->assertSee('"employmentType":"FULL_TIME"', false);

        $this->get('/ku/careers')->assertOk()
            ->assertSee('<html lang="ku" dir="rtl">', false)
            ->assertSee('تەڵارسازی نوێ');

        $this->get('/sitemap.xml')->assertSee('<loc>'.url('/careers').'</loc>', false);
    }

    public function test_an_application_keeps_the_cv_private_and_tells_the_studio(): void
    {
        Storage::fake('local');
        Mail::fake();
        SiteSetting::put(StudioMail::TO, 'hiring@example.com', 'alerts');
        $job = $this->opening();

        $this->post('/careers/apply', [
            'position' => $job->id, 'name' => 'Rozh', 'email' => 'rozh@example.com', 'phone' => '0750 123 4567',
            'portfolio' => 'https://rozh.design', 'message' => 'Hello', 'lang' => 'ku', 'cv' => $this->pdf(),
        ], ['Accept' => 'application/json'])->assertOk();

        $a = JobApplication::sole();
        $this->assertSame([$job->id, 'Rozh', 'My CV.pdf', 'ku'], [$a->job_opening_id, $a->name, $a->cv_name, $a->lang]);
        Storage::disk('local')->assertExists($a->cv_path);
        $this->assertStringStartsWith('cvs/', $a->cv_path);
        $this->assertFileDoesNotExist(public_path('storage/'.$a->cv_path));

        Mail::assertSent(NewApplication::class, fn (NewApplication $m) => $m->hasTo('hiring@example.com')
            && $m->hasReplyTo('rozh@example.com') && count($m->attachments()) === 1);
    }

    public function test_only_a_real_pdf_under_8mb_is_accepted(): void
    {
        Storage::fake('local');
        Mail::fake();
        $this->withoutMiddleware(ThrottleRequests::class);   // many tries in a row, on purpose
        $send = fn ($cv) => $this->post('/careers/apply', ['name' => 'X', 'email' => 'x@example.com', 'cv' => $cv], ['Accept' => 'application/json']);

        $send(UploadedFile::fake()->createWithContent('cv.pdf', 'not really a pdf'))->assertStatus(422)->assertJsonValidationErrors('cv');
        $send(UploadedFile::fake()->create('cv.exe', 10, 'application/x-msdownload'))->assertStatus(422)->assertJsonValidationErrors('cv');
        $send(UploadedFile::fake()->create('huge.pdf', 9000, 'application/pdf'))->assertStatus(422)->assertJsonValidationErrors('cv');
        $this->post('/careers/apply', ['name' => 'X', 'email' => 'x@example.com'], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('cv');

        // an open application (no position) is fine
        $send($this->pdf())->assertOk();
        $this->assertNull(JobApplication::sole()->job_opening_id);
    }

    public function test_the_spam_trap_keeps_nothing(): void
    {
        Storage::fake('local');
        $this->post('/careers/apply', ['name' => 'Bot', 'email' => 'b@x.test', 'website' => 'spam', 'cv' => $this->pdf()],
            ['Accept' => 'application/json'])->assertOk();
        $this->assertSame(0, JobApplication::count());
    }

    public function test_only_the_admin_can_open_a_cv_and_deleting_removes_it(): void
    {
        Storage::fake('local');
        Mail::fake();
        $this->post('/careers/apply', ['name' => 'Rozh', 'email' => 'rozh@example.com', 'cv' => $this->pdf()], ['Accept' => 'application/json'])->assertOk();
        $a = JobApplication::sole();
        $url = route('filament.studio-aks-panel.application-cv', $a);

        $this->get($url)->assertRedirect();             // a visitor is sent to the login

        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password']);
        $this->actingAs($admin)->get($url)->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="My CV.pdf"');
        $this->actingAs($admin)->get($url.'?download=1')->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="My CV.pdf"');

        $this->actingAs($admin)->get(JobApplicationResource::getUrl())->assertOk()->assertSee('Rozh');
        $this->actingAs($admin)->get(JobApplicationResource::getUrl('edit', ['record' => $a]))->assertOk()->assertSee('Open CV');
        $this->actingAs($admin)->get(JobOpeningResource::getUrl())->assertOk();
        $this->actingAs($admin)->get(JobOpeningResource::getUrl('create'))->assertOk();

        $path = $a->cv_path;
        $a->delete();
        Storage::disk('local')->assertMissing($path);
    }
}
