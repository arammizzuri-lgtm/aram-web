<?php

namespace Tests\Feature;

use App\Filament\Resources\VisitRequests\Pages\ListVisitRequests;
use App\Filament\Resources\VisitRequests\VisitRequestResource;
use App\Mail\VisitRequested;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\VisitRequest;
use App\Support\StudioMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/** Book a visit (/visit): asked for on the website, confirmed or moved in the admin, answered on WhatsApp. */
class VisitRequestsTest extends TestCase
{
    use RefreshDatabase;

    private function ask(string $lang = 'ku', array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->post(($lang === 'en' ? '' : '/'.$lang).'/visit', $data + [
            'kind' => 'site', 'day' => '2026-11-24', 'time' => '16:30',
            'name' => 'Shilan Ahmed', 'phone' => '0750 123 4567', 'about' => 'A house on a 300 m² plot in Ankawa',
        ]);
    }

    public function test_a_visitor_asks_for_a_day_and_time_and_the_studio_is_emailed(): void
    {
        Carbon::setTestNow('2026-11-20 09:00');
        Mail::fake();
        SiteSetting::put(StudioMail::TO, 'aram@example.com', 'alerts');

        $this->get('/ku/visit')->assertOk()
            ->assertSee('سەردانی ستۆدیۆ')->assertSee('min="2026-11-20"', false)->assertSee('/ku/visit', false);
        $this->get('/')->assertOk()->assertSee('Book a visit')->assertSee(url('/visit'), false);

        $this->ask()->assertRedirect('/ku/visit')->assertSessionHas('visit_sent');
        $this->get('/ku/visit')->assertSee('سوپاس — داواکارییەکەت گەیشت');

        $visit = VisitRequest::sole();
        $this->assertSame(['site', 'ku', VisitRequest::WAITING, '16:30', '2026-11-24'],
            [$visit->kind, $visit->lang, $visit->status, $visit->wanted_time, $visit->wanted_on->toDateString()]);
        Mail::assertSent(VisitRequested::class, fn (VisitRequested $m) => $m->hasTo('aram@example.com')
            && str_contains($m->render(), 'Site visit — Shilan Ahmed') && str_contains($m->render(), 'Tuesday 24 November 2026, 16:30'));
    }

    public function test_a_missing_or_past_day_is_refused_and_the_bot_trap_keeps_nothing(): void
    {
        Carbon::setTestNow('2026-11-20 09:00');
        Mail::fake();

        $this->ask('en', ['day' => '2026-11-19'])->assertSessionHasErrors('day');
        $this->ask('en', ['kind' => 'party', 'time' => '4pm'])->assertSessionHasErrors(['kind', 'time']);
        $this->ask('en', ['phone' => 'call me'])->assertSessionHasErrors('phone');
        $this->ask('en', ['website' => 'http://spam.example'])->assertRedirect('/visit')->assertSessionHas('visit_sent');

        $this->assertSame(0, VisitRequest::count());
        Mail::assertNothingSent();
    }

    public function test_the_studio_confirms_or_suggests_another_time_and_whatsapp_is_ready_in_their_language(): void
    {
        Carbon::setTestNow('2026-11-20 09:00');
        $ku = VisitRequest::create(['name' => 'Shilan', 'phone' => '0750 123 4567', 'kind' => 'studio', 'about' => 'A house',
            'wanted_on' => '2026-11-24', 'wanted_time' => '16:30', 'lang' => 'ku']);
        $en = VisitRequest::create(['name' => 'Dara', 'phone' => '+964 751 000 1111', 'kind' => 'call', 'about' => 'A villa',
            'wanted_on' => '2026-11-26', 'wanted_time' => '11:00', 'lang' => 'en']);
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password']));
        $this->assertSame('2', VisitRequestResource::getNavigationBadge());
        $this->get('/studio-aks-panel/visit-requests')->assertOk()->assertSee('Shilan')->assertSee('Phone call');
        $text = fn (VisitRequest $v) => rawurldecode(explode('?text=', $v->fresh()->whatsappUrl())[1]);

        // another time, then confirmed once they agree: the suggested time stays
        Livewire::test(ListVisitRequests::class)
            ->assertTableActionHidden('whatsapp', $ku)
            ->callTableAction('suggest', $ku, ['set_on' => '2026-11-25', 'set_time' => '10:00'])->assertNotified();
        $this->assertSame([VisitRequest::SUGGESTED, '2026-11-25', '10:00'], [$ku->fresh()->status, $ku->fresh()->set_on->toDateString(), $ku->fresh()->set_time]);
        $this->assertStringStartsWith('https://wa.me/9647501234567?text=', $ku->fresh()->whatsappUrl());
        $this->assertStringContainsString('سەبارەت بە سەردانی ستۆدیۆ', $text($ku));
        $this->assertStringContainsString(site_date(Carbon::parse('2026-11-25'), 'ku').' کاتژمێر 10:00', $text($ku));

        Livewire::test(ListVisitRequests::class)->callTableAction('confirm', $ku)->assertNotified()
            ->callTableAction('confirm', $en)
            ->assertTableActionHidden('confirm', $ku)->assertTableActionVisible('whatsapp', $ku);
        $this->assertSame([VisitRequest::CONFIRMED, '2026-11-25', '10:00'], [$ku->fresh()->status, $ku->fresh()->set_on->toDateString(), $ku->fresh()->set_time]);
        $this->assertStringContainsString('پشتڕاستکرایەوە — سەردانی ستۆدیۆ', $text($ku));
        $this->assertSame('Hello Dara, this is Aram Mizuri Architecture. Confirmed — Phone call on 26 November 2026 at 11:00. See you then.', $text($en));
        $this->assertNull(VisitRequestResource::getNavigationBadge());
    }
}
