<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Filament\Pages\EnquiryAlerts;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\Testimonials\TestimonialResource;
use App\Mail\NewEnquiry;
use App\Models\ContactMessage;
use App\Models\Project;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\StudioMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Release 2: the enquiry form and the leads it creates, the alert e-mail,
 * WhatsApp, and client quotes.
 */
class EnquiriesTest extends TestCase
{
    use RefreshDatabase;

    private function project(array $attrs = []): Project
    {
        return Project::create($attrs + [
            'name' => 'Mountain Villa', 'name_ku' => 'ڤیلای شاخ',
            'category' => 'residential', 'status' => 'Completed', 'size' => 'default',
            'imgs' => ['https://example.com/cover.jpg'], 'is_published' => true,
        ]);
    }

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password']);
    }

    /* ---- the form ---- */

    public function test_an_enquiry_keeps_everything_the_visitor_told_us(): void
    {
        Mail::fake();
        $project = $this->project();

        $this->postJson('/contact', [
            'name' => 'Karwan', 'phone' => '0750 123 4567', 'email' => 'karwan@example.com',
            'type' => 'residential', 'city' => 'Erbil', 'plot_area' => 600,
            'budget' => '250-500k', 'timeline' => '6m',
            'about' => $project->slug, 'message' => 'A family house.',
            'lang' => 'ku', 'page' => '/ku',
        ])->assertOk()->assertJson(['ok' => true]);

        $e = ContactMessage::sole();
        $this->assertSame(['Karwan', '0750 123 4567', 'residential', 'Erbil', 600, '250-500k', '6m', $project->id, 'ku', '/ku'],
            [$e->name, $e->phone, $e->project_type, $e->city, $e->plot_area, $e->budget, $e->timeline, $e->project_id, $e->lang, $e->page]);
        $this->assertSame(LeadStatus::New, $e->status);
        $this->assertSame('$250,000 – $500,000', $e->budgetLabel());
        $this->assertSame('https://wa.me/9647501234567', $e->whatsappUrl());
    }

    public function test_a_phone_alone_is_enough_but_one_way_to_reply_is_required(): void
    {
        Mail::fake();

        $this->postJson('/contact', ['name' => 'Shno', 'phone' => '+964 750 111 2222'])->assertOk();
        $this->assertSame(1, ContactMessage::count());

        $this->postJson('/contact', ['name' => 'Nobody'])
            ->assertStatus(422)->assertJsonValidationErrors(['email', 'phone']);
        $this->postJson('/contact', ['name' => 'X', 'email' => 'x@example.com', 'budget' => 'a-billion'])
            ->assertStatus(422)->assertJsonValidationErrors(['budget']);
    }

    public function test_the_spam_trap_answers_ok_and_keeps_nothing(): void
    {
        Mail::fake();

        $this->postJson('/contact', ['name' => 'Bot', 'email' => 'bot@spam.test', 'website' => 'http://spam.test'])
            ->assertOk();

        $this->assertSame(0, ContactMessage::count());
        Mail::assertNothingSent();
    }

    /* ---- the alert ---- */

    public function test_the_studio_is_emailed_with_a_reply_to_the_visitor(): void
    {
        Mail::fake();
        SiteSetting::put(StudioMail::TO, 'aram@example.com, office@example.com', 'alerts');

        $this->postJson('/contact', ['name' => 'Dara', 'email' => 'dara@example.com', 'phone' => '07501234567'])->assertOk();

        Mail::assertSent(NewEnquiry::class, function (NewEnquiry $mail) {
            $html = $mail->render();

            return $mail->hasTo('aram@example.com') && $mail->hasTo('office@example.com')
                && $mail->hasReplyTo('dara@example.com')
                && str_contains($html, 'https://wa.me/9647501234567')
                && str_contains($html, 'mailto:dara@example.com');
        });
    }

    public function test_alerts_fall_back_to_the_site_email_and_can_be_switched_off(): void
    {
        Mail::fake();
        SiteSetting::put('contact_email_new', 'studio@example.com');

        $this->postJson('/contact', ['name' => 'A', 'email' => 'a@example.com'])->assertOk();
        Mail::assertSent(NewEnquiry::class, fn ($m) => $m->hasTo('studio@example.com'));

        SiteSetting::put(StudioMail::ENABLED, '0', 'alerts');
        $this->postJson('/contact', ['name' => 'B', 'email' => 'b@example.com'])->assertOk();
        Mail::assertSent(NewEnquiry::class, 1);
    }

    public function test_the_admin_mailbox_is_used_with_its_password_kept_encrypted(): void
    {
        SiteSetting::put(StudioMail::HOST, 'mail.example.com', 'alerts');
        SiteSetting::put(StudioMail::PORT, '465', 'alerts');
        SiteSetting::put(StudioMail::USERNAME, 'website@example.com', 'alerts');
        StudioMail::storePassword('s3cret-pass');

        $this->assertNotSame('s3cret-pass', SiteSetting::get(StudioMail::PASSWORD));

        StudioMail::mailer();
        $this->assertSame(['smtps', 'mail.example.com', 465, 'website@example.com', 's3cret-pass'], [
            config('mail.mailers.studio.scheme'), config('mail.mailers.studio.host'), config('mail.mailers.studio.port'),
            config('mail.mailers.studio.username'), config('mail.mailers.studio.password'),
        ]);
        $this->assertSame('website@example.com', StudioMail::fromAddress());

        // none of it ever reaches the public page
        $this->get('/')->assertOk()
            ->assertDontSee('mail.example.com')
            ->assertDontSee(SiteSetting::get(StudioMail::PASSWORD));
    }

    /* ---- WhatsApp ---- */

    public function test_whatsapp_buttons_appear_once_a_number_is_set(): void
    {
        $project = $this->project();
        $this->get('/')->assertDontSee('wa.me');

        SiteSetting::put('contact_whatsapp', '+964 (0) 750 123 4567');

        $this->get('/')->assertSee('https://wa.me/9647501234567?text=', false)->assertSee('id="waFab"', false);
        // a project's page opens the chat already naming the project
        $this->get('/projects/'.$project->slug)
            ->assertSee(rawurlencode('Mountain Villa'), false)
            ->assertSee(url('/').'?project='.$project->slug.'#contact', false);
        $this->get('/ku/projects/'.$project->slug)->assertSee(rawurlencode('ڤیلای شاخ'), false);
    }

    public function test_phone_numbers_become_international_digits(): void
    {
        $this->assertSame('9647501234567', phone_digits('+964 (0) 750 123 4567'));
        $this->assertSame('9647501234567', phone_digits('00964 750 123 4567'));
        $this->assertSame('9647501234567', phone_digits('0750 123 4567'));
        $this->assertNull(phone_digits(''));
    }

    /* ---- client quotes ---- */

    public function test_quotes_show_on_the_homepage_and_their_project_page_in_their_own_language(): void
    {
        $project = $this->project();
        Testimonial::create([
            'quote_en' => 'They listened before they drew.', 'person_name' => 'Hawre Aziz',
            'person_role_en' => 'Owner', 'project_id' => $project->id, 'is_published' => true,
        ]);
        Testimonial::create(['quote_en' => 'Hidden one.', 'person_name' => 'X', 'is_published' => false]);

        $this->get('/')->assertSee('They listened before they drew.')->assertSee('data-has-en', false)->assertDontSee('Hidden one.');
        $this->get('/projects/'.$project->slug)->assertSee('They listened before they drew.');
        // no Kurdish words were given, so the Kurdish page shows none in their place
        $this->get('/ku/projects/'.$project->slug)->assertDontSee('They listened before they drew.');
    }

    /* ---- the admin ---- */

    public function test_the_admin_screens_render(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $this->postJson('/contact', ['name' => 'Rebin', 'phone' => '07501234567', 'email' => 'r@example.com'])->assertOk();
        $enquiry = ContactMessage::sole();

        $this->actingAs($admin)->get(ContactMessageResource::getUrl())
            ->assertOk()->assertSee('Rebin')->assertSee('In progress');
        $this->actingAs($admin)->get(ContactMessageResource::getUrl('edit', ['record' => $enquiry]))
            ->assertOk()->assertSee('Follow-up')->assertSee('https://wa.me/9647501234567', false);
        $this->assertTrue($enquiry->fresh()->is_read, 'opening an enquiry marks it read');

        $this->actingAs($admin)->get(EnquiryAlerts::getUrl())->assertOk()->assertSee('Send a test e-mail');
        $this->actingAs($admin)->get(TestimonialResource::getUrl())->assertOk();
        $this->actingAs($admin)->get(TestimonialResource::getUrl('create'))->assertOk();
    }
}
