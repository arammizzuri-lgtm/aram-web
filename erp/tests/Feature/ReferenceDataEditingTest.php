<?php

namespace Tests\Feature;

use App\Filament\Resources\CollectionPoints\Pages\ManageCollectionPoints;
use App\Filament\Resources\Currencies\Pages\ManageCurrencies;
use App\Filament\Resources\Customers\Pages\ManageCustomers;
use App\Filament\Resources\ExchangeRates\Pages\ManageExchangeRates;
use App\Filament\Resources\Expenses\Pages\ManageExpenses;
use App\Filament\Resources\ProductCategories\Pages\ManageProductCategories;
use App\Filament\Resources\Suppliers\Pages\ManageSuppliers;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Reference data has to be correctable.
 *
 * Every one of these screens could create a record and then never touch it
 * again: a customer's phone number, a supplier's address, a mistyped rate were
 * all permanent from the moment they were saved. The screens that handle
 * documents had an Edit action; the ones holding the data underneath them did
 * not, which is the sort of gap that is invisible until the day something needs
 * correcting.
 */
class ReferenceDataEditingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([FoundationSeeder::class, ReferenceDataSeeder::class, RolePermissionSeeder::class]);

        $owner = User::create([
            'name' => 'Owner', 'email' => 'owner@test.local',
            'password' => 'password', 'is_active' => true,
        ]);
        $owner->assignRole('owner');
        $this->actingAs($owner);
    }

    /**
     * The screens that hold reference data, each of which must offer a way to
     * correct a row.
     *
     * @return array<string, array{class-string}>
     */
    public static function screens(): array
    {
        return [
            'customers' => [ManageCustomers::class],
            'suppliers' => [ManageSuppliers::class],
            'collection points' => [ManageCollectionPoints::class],
            'product categories' => [ManageProductCategories::class],
            'currencies' => [ManageCurrencies::class],
            'exchange rates' => [ManageExchangeRates::class],
            'expenses' => [ManageExpenses::class],
        ];
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('screens')]
    public function every_reference_screen_offers_a_way_to_correct_a_row(string $page): void
    {
        $actions = collect(Livewire::test($page)->instance()->getTable()->getActions())
            ->map(fn ($action) => $action->getName());

        $this->assertContains('edit', $actions->all(), class_basename($page).' has no edit action');
    }

    /** And the edit has to actually save what was typed into it. */
    #[Test]
    public function correcting_a_customer_saves_the_correction(): void
    {
        $customer = Customer::create([
            'code' => 'C-001', 'name' => 'Azheen Kareem',
            'phone' => '07504953019', 'city' => 'Erbil',
            'default_currency' => 'USD', 'document_language' => 'en', 'is_active' => true,
        ]);

        Livewire::test(ManageCustomers::class)
            ->callTableAction('edit', $customer, [
                'code' => 'C-001',
                'name' => 'Azheen Kareem Trading',
                'phone' => '07701234567',
                'city' => 'Sulaymaniyah',
                'default_currency' => 'USD',
                'document_language' => 'ckb',
                'is_active' => true,
            ])
            ->assertHasNoTableActionErrors();

        $customer->refresh();

        $this->assertSame('Azheen Kareem Trading', $customer->name);
        $this->assertSame('07701234567', $customer->phone);
        $this->assertSame('Sulaymaniyah', $customer->city);
        $this->assertSame('ckb', $customer->document_language);
    }
}
