<?php

namespace Tests\Feature;

use App\Filament\Pages\CompanyProfile;
use App\Filament\Resources\Currencies\Pages\ManageCurrencies;
use App\Filament\Resources\ExchangeRates\Pages\ManageExchangeRates;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $this->seed([FoundationSeeder::class, RolePermissionSeeder::class]);

        $owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@test.local',
            'password' => 'password',
            'is_active' => true,
        ]);

        return $owner->assignRole('owner');
    }

    #[Test]
    public function the_login_page_renders(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    #[Test]
    public function guests_are_sent_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    #[Test]
    public function the_dashboard_renders_for_a_signed_in_owner(): void
    {
        $this->actingAs($this->owner())->get('/admin')->assertOk();
    }

    #[Test]
    public function a_deactivated_user_cannot_reach_the_panel(): void
    {
        $user = $this->owner();
        $user->update(['is_active' => false]);

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    #[Test]
    public function the_settings_pages_render(): void
    {
        $this->actingAs($this->owner());

        Livewire::test(ListUsers::class)->assertOk();
        Livewire::test(ManageCurrencies::class)->assertOk();
        Livewire::test(ManageExchangeRates::class)->assertOk();
        Livewire::test(CompanyProfile::class)->assertOk();
    }

    #[Test]
    public function the_users_table_lists_seeded_users(): void
    {
        $owner = $this->owner();

        Livewire::actingAs($owner)
            ->test(ListUsers::class)
            ->assertCanSeeTableRecords([$owner]);
    }

    #[Test]
    public function a_user_can_be_created_with_a_role_and_a_hashed_password(): void
    {
        $this->actingAs($this->owner());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Warehouse Lead',
                'email' => 'warehouse@test.local',
                'password' => 'secret-password',
                'roles' => [Role::findByName('warehouse')->getKey()],
                'is_active' => true,
                'locale' => 'en',
                'theme_preference' => 'system',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::where('email', 'warehouse@test.local')->firstOrFail();

        $this->assertTrue($created->hasRole('warehouse'));
        $this->assertNotSame('secret-password', $created->password, 'the password must be hashed');
        $this->assertFalse($created->can('view_cost'), 'warehouse must not see cost data');
    }

    #[Test]
    public function editing_a_user_without_a_password_keeps_the_existing_one(): void
    {
        $this->actingAs($this->owner());

        $user = User::create([
            'name' => 'Sales Rep',
            'email' => 'sales@test.local',
            'password' => 'original-password',
            'is_active' => true,
        ]);
        $user->assignRole('sales');
        $originalHash = $user->password;

        Livewire::test(EditUser::class, ['record' => $user->getKey()])
            ->fillForm(['name' => 'Sales Rep Renamed', 'password' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();

        $this->assertSame('Sales Rep Renamed', $user->name);
        $this->assertSame($originalHash, $user->password);
    }

    #[Test]
    public function the_company_profile_saves(): void
    {
        $this->actingAs($this->owner());

        Livewire::test(CompanyProfile::class)
            ->fillForm(['name' => 'Aram Imports', 'city' => 'Erbil', 'country' => 'IQ'])
            ->call('save');

        $this->assertDatabaseHas('companies', ['name' => 'Aram Imports', 'city' => 'Erbil']);
    }
}
