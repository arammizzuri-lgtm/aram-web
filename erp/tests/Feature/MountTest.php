<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Deal;
use App\Models\DealLine;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Deals\DealWriter;
use App\Services\Reporting\BusinessMetrics;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The ERP answers on a path of the public site rather than on a host of its own,
 * and the public site is a Laravel application too. So the addresses this one
 * publishes are not a detail of its configuration: a route left at the root of
 * the domain belongs to the other application, and requests for it never arrive
 * here at all.
 *
 * See app/Providers/MountServiceProvider.php.
 */
class MountTest extends TestCase
{
    use RefreshDatabase;

    private string $mount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mount = '/'.config('erp.mount');
    }

    /** Every screen is behind the login, so this is the whole front door. */
    #[Test]
    public function the_mount_opens_onto_the_login_screen(): void
    {
        $this->get($this->mount)->assertRedirect($this->mount.'/login');

        $this->get($this->mount.'/login')->assertOk();
    }

    #[Test]
    public function a_signed_in_owner_reaches_the_dashboard(): void
    {
        $this->seed([FoundationSeeder::class, ReferenceDataSeeder::class, RolePermissionSeeder::class]);

        $owner = User::create([
            'name' => 'Owner', 'email' => 'owner@test.local',
            'password' => 'password', 'is_active' => true,
        ]);
        $owner->assignRole('owner');

        $this->actingAs($owner)->get($this->mount)->assertOk();
    }

    /** The root of the domain belongs to the public site. */
    #[Test]
    public function nothing_is_published_at_the_root(): void
    {
        $this->get('/')->assertNotFound();
    }

    /**
     * Livewire registers its endpoints at the application root, and the public
     * site's own admin panel already owns those addresses. Every one of them
     * has to have been moved under the mount, or the panel is a set of screens
     * that render once and then answer nothing.
     */
    #[Test]
    public function no_route_is_left_at_the_root_of_the_domain(): void
    {
        $stranded = collect(app('router')->getRoutes()->getRoutes())
            ->map(fn ($route) => $route->uri())
            ->reject(fn (string $uri) => str_starts_with($uri, ltrim($this->mount, '/')))
            ->values();

        $this->assertSame([], $stranded->all());
    }

    /**
     * The dashboard's alerts are links, and a link written out by hand is wrong
     * the moment the panel moves — silently, because a string cannot fail until
     * somebody clicks it. Every one of these pointed at /admin, and every one
     * of them was a 404.
     */
    #[Test]
    public function the_dashboard_alerts_link_inside_the_mount(): void
    {
        $this->seed([FoundationSeeder::class, ReferenceDataSeeder::class, RolePermissionSeeder::class]);

        $owner = User::create([
            'name' => 'Owner', 'email' => 'owner@test.local',
            'password' => 'password', 'is_active' => true,
        ]);
        $owner->assignRole('owner');
        $this->actingAs($owner);

        /*
         * An empty dashboard raises no alerts and would assert nothing, so the
         * state that raises one is built: goods ordered from a supplier on a
         * deal nobody has approved. That is money spent at your own risk, and
         * it is the first card on the screen.
         */
        $customer = Customer::create([
            'code' => 'C-001', 'name' => 'Ali Trading',
            'default_currency' => 'USD', 'is_active' => true,
        ]);
        $supplier = Supplier::create(['code' => 'SUP-A', 'name' => 'Yiwu', 'default_currency' => 'CNY']);

        $deal = Deal::create([
            'number' => 'D-2026-0001',
            'customer_id' => $customer->id,
            'deal_date' => today(),
            'sell_currency' => 'USD',
            'rmb_usd_rate' => 7.2,
        ]);

        DealLine::create([
            'deal_id' => $deal->id,
            'supplier_id' => $supplier->id,
            'description' => 'Crystal P07',
            'quantity' => 10,
            'unit_cost' => 12.5,
            'cost_currency' => 'CNY',
            'unit_price' => 3,
        ]);

        app(DealWriter::class)->sync($deal);

        $urls = app(BusinessMetrics::class)->needsAttention()->pluck('url');

        $this->assertNotEmpty($urls, 'expected a deal bought without approval to raise an alert');

        foreach ($urls as $url) {
            $this->assertStringStartsWith(
                $this->mount.'/',
                parse_url((string) $url, PHP_URL_PATH) ?? '',
                "alert link {$url} points outside the mount"
            );
        }
    }

    /** The URL Livewire hands the browser for every interaction on every page. */
    #[Test]
    public function livewire_posts_back_to_this_application(): void
    {
        $this->assertSame($this->mount.'/livewire/update', app('livewire')->getUpdateUri());

        $this->assertSame($this->mount.'/livewire/upload-file', route('livewire.upload-file', [], false));
    }
}
