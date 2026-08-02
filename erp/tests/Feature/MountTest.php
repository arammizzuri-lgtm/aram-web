<?php

namespace Tests\Feature;

use App\Models\User;
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

    /** The URL Livewire hands the browser for every interaction on every page. */
    #[Test]
    public function livewire_posts_back_to_this_application(): void
    {
        $this->assertSame($this->mount.'/livewire/update', app('livewire')->getUpdateUri());

        $this->assertSame($this->mount.'/livewire/upload-file', route('livewire.upload-file', [], false));
    }
}
