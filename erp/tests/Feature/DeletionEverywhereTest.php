<?php

namespace Tests\Feature;

use App\Filament\Pages\RecentlyDeleted;
use App\Filament\Resources\CollectionPoints\Pages\ManageCollectionPoints;
use App\Filament\Resources\Consignments\Pages\ManageConsignments;
use App\Filament\Resources\Currencies\Pages\ManageCurrencies;
use App\Filament\Resources\CustomerPayments\Pages\ManageCustomerPayments;
use App\Filament\Resources\Customers\Pages\ManageCustomers;
use App\Filament\Resources\ProductCategories\Pages\ManageProductCategories;
use App\Filament\Resources\Suppliers\Pages\ManageSuppliers;
use App\Livewire\UndoDelete;
use App\Models\CollectionPoint;
use App\Models\Consignment;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\DealLine;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Deals\PaymentWriter;
use App\Services\Deletion\DeletionImpact;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Deleting works the same way on every screen, and none of it is final.
 *
 * Three screens could delete anything at all: a tracking number typed wrong, a
 * supplier created twice and a payment that never arrived were all permanent,
 * and the only way to blunt a wrong payment was to edit the amount — which
 * rewrites history rather than correcting it.
 *
 * What makes a delete button frightening is that it cannot be taken back, so
 * that is the thing these tests are really about: the row goes, and it comes
 * back.
 */
class DeletionEverywhereTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([FoundationSeeder::class, ReferenceDataSeeder::class, RolePermissionSeeder::class]);

        $this->owner = User::create([
            'name' => 'Owner', 'email' => 'owner@test.local',
            'password' => 'password', 'is_active' => true,
        ]);
        $this->owner->assignRole('owner');
        $this->actingAs($this->owner);
    }

    // ------------------------------------------------------------- fixtures

    private function customer(string $code = 'C-001'): Customer
    {
        return Customer::create([
            'code' => $code, 'name' => "Trader {$code}",
            'default_currency' => 'IQD', 'is_active' => true,
        ]);
    }

    private function supplier(string $code = 'SUP-A'): Supplier
    {
        return Supplier::create([
            'code' => $code, 'name' => "Factory {$code}",
            'default_currency' => 'CNY', 'is_active' => true,
        ]);
    }

    private function consignment(string $tracking = '16940'): Consignment
    {
        return Consignment::create([
            'tracking_number' => $tracking, 'mode' => 'sea',
            'boxes' => 1, 'gross_weight_kg' => 18.5, 'cbm' => 0.11, 'status' => 'awaiting',
        ]);
    }

    // ------------------------------------------------- every screen forgives

    /**
     * @return array<string, array{0: class-string, 1: string}>
     */
    public static function screens(): array
    {
        return [
            'customers' => [ManageCustomers::class, 'customer'],
            'suppliers' => [ManageSuppliers::class, 'supplier'],
            'consignments' => [ManageConsignments::class, 'consignment'],
            'collection points' => [ManageCollectionPoints::class, 'collectionPoint'],
            'currencies' => [ManageCurrencies::class, 'currency'],
            'categories' => [ManageProductCategories::class, 'category'],
        ];
    }

    private function collectionPoint(): CollectionPoint
    {
        return CollectionPoint::create([
            'name' => 'Guangzhou point', 'city' => 'Guangzhou', 'is_active' => true,
        ]);
    }

    private function currency(): Currency
    {
        return Currency::create([
            'code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2,
        ]);
    }

    private function category(): ProductCategory
    {
        return ProductCategory::create([
            'name' => 'Lighting', 'slug' => 'lighting', 'is_active' => true,
        ]);
    }

    /**
     * @param  class-string  $page
     */
    #[Test]
    #[DataProvider('screens')]
    public function every_screen_can_delete_and_put_back(string $page, string $fixture): void
    {
        $record = $this->{$fixture}();

        Livewire::test($page)
            ->callAction(TestAction::make('delete')->table($record))
            ->assertHasNoFormErrors();

        $this->assertSoftDeleted($record);

        // And the row is gone from the screen it was deleted on, but findable
        // once the filter is asked for it.
        Livewire::test($page)
            ->assertCanNotSeeTableRecords([$record])
            ->filterTable('trashed', true)
            ->assertCanSeeTableRecords([$record])
            ->callAction(TestAction::make('restore')->table($record))
            ->assertHasNoFormErrors();

        $this->assertNotSoftDeleted($record);
    }

    // ------------------------------------------------------ the money screens

    /**
     * A payment recorded that never arrived.
     *
     * This was the sharpest edge of having no delete: the row could not be
     * removed, so the only way to correct it was to edit the amount — which
     * leaves a payment on the customer's account that says it happened.
     */
    #[Test]
    public function a_payment_that_never_arrived_can_be_removed(): void
    {
        $customer = $this->customer();

        $payment = app(PaymentWriter::class)->receive(
            customer: $customer,
            amount: 500,
            currency: 'USD',
            paidAt: today()->toDateString(),
        );

        $this->assertSame(-500.0, $customer->fresh()->outstandingBalance());

        Livewire::test(ManageCustomerPayments::class)
            ->callAction(TestAction::make('delete')->table($payment))
            ->assertHasNoFormErrors();

        $this->assertSoftDeleted($payment);
        $this->assertSame(0.0, $customer->fresh()->outstandingBalance());
    }

    /** The figure moving is the thing you most need told before you click. */
    #[Test]
    public function deleting_a_payment_says_whose_balance_moves_and_by_how_much(): void
    {
        $customer = $this->customer();

        $payment = app(PaymentWriter::class)->receive(
            customer: $customer,
            amount: 500,
            currency: 'USD',
            paidAt: today()->toDateString(),
        );

        $warning = app(DeletionImpact::class)->describe($payment->fresh());

        $this->assertStringContainsString('Trader C-001 owing goes from', $warning);
        $this->assertStringContainsString('$500.00', $warning);
    }

    // ------------------------------------------------- what it tells you first

    #[Test]
    public function a_customer_with_history_says_what_hangs_off_them(): void
    {
        $customer = $this->customer();

        Deal::create([
            'number' => 'D-2026-0001', 'customer_id' => $customer->id,
            'deal_date' => today(), 'sell_currency' => 'IQD', 'iqd_usd_rate' => 1470,
        ]);

        $impact = app(DeletionImpact::class);

        $this->assertStringContainsString('1 deal on it', $impact->describe($customer));

        // And offers the gentler move rather than only warning.
        $this->assertStringContainsString('Deactivating it instead', $impact->alternative($customer));

        // Nothing can be erased for good while something still points at it.
        $this->assertFalse($impact->canBeErased($customer));
    }

    #[Test]
    public function an_untouched_record_can_be_erased_for_good(): void
    {
        $this->assertTrue(app(DeletionImpact::class)->canBeErased($this->supplier()));
    }

    /**
     * A deleted record is hidden, not gone — and its foreign key is as real as
     * any other.
     *
     * Deleting a customer's every deal made them look untouched, so "Delete
     * permanently" appeared and ran straight into a constraint that had never
     * moved. Counting only the living rows is what made a 500 out of a button.
     */
    #[Test]
    public function a_customer_whose_deals_are_all_deleted_still_cannot_be_erased(): void
    {
        $customer = $this->customer();

        $deal = Deal::create([
            'number' => 'D-2026-0001', 'customer_id' => $customer->id,
            'deal_date' => today(), 'sell_currency' => 'IQD', 'iqd_usd_rate' => 1470,
        ]);

        $deal->delete();
        $customer->delete();

        $impact = app(DeletionImpact::class);

        $this->assertFalse(
            $impact->canBeErased($customer->fresh()),
            'a deleted deal still holds the customer id',
        );

        $this->assertStringContainsString('1 deal on it', $impact->describe($customer->fresh()));

        Livewire::test(RecentlyDeleted::class)
            ->assertActionHidden(TestAction::make('erase')->table(Customer::class.':'.$customer->id));
    }

    /** The same guard on the screen the customer was deleted from. */
    #[Test]
    public function the_customers_own_screen_hides_it_too(): void
    {
        $customer = $this->customer();

        Deal::create([
            'number' => 'D-2026-0001', 'customer_id' => $customer->id,
            'deal_date' => today(), 'sell_currency' => 'IQD', 'iqd_usd_rate' => 1470,
        ])->delete();

        $customer->delete();

        Livewire::test(ManageCustomers::class)
            ->filterTable('trashed', true)
            ->assertActionHidden(TestAction::make('forceDelete')->table($customer));
    }

    // ------------------------------------------------------- codes come free

    /**
     * A deleted record gives its code up.
     *
     * Otherwise deleting the supplier you created twice leaves "SUP-A" taken by
     * something you can no longer see, and the form refuses a code that appears
     * to belong to nobody.
     */
    #[Test]
    public function a_deleted_records_code_can_be_used_again(): void
    {
        $this->supplier('SUP-A')->delete();

        Livewire::test(ManageSuppliers::class)
            ->callAction(TestAction::make('create'), [
                'code' => 'SUP-A',
                'name' => 'The real one',
                'default_currency' => 'CNY',
                'default_incoterm' => 'FOB',
            ])
            ->assertHasNoFormErrors();

        $this->assertSame('The real one', Supplier::where('code', 'SUP-A')->value('name'));
    }

    /** And the one that gave it up says so rather than throwing a driver error. */
    #[Test]
    public function restoring_onto_a_taken_code_explains_itself(): void
    {
        $original = $this->supplier('SUP-A');
        $original->delete();

        $this->supplier('SUP-A');

        Livewire::test(ManageSuppliers::class)
            ->filterTable('trashed', true)
            ->callAction(TestAction::make('restore')->table($original))
            ->assertNotified('That one cannot come back as it is');

        $this->assertSoftDeleted($original);
    }

    // ------------------------------------------------------------ the undo

    #[Test]
    public function the_undo_button_puts_a_record_straight_back(): void
    {
        $supplier = $this->supplier();
        $supplier->delete();

        Livewire::test(UndoDelete::class)
            ->dispatch('undo-delete', ['model' => Supplier::class, 'key' => $supplier->id]);

        $this->assertNotSoftDeleted($supplier);
    }

    /** The event comes from the browser, so the class in it is not trusted. */
    #[Test]
    public function undo_ignores_anything_not_on_the_allowlist(): void
    {
        $line = DealLine::create([
            'deal_id' => Deal::create([
                'number' => 'D-2026-0001', 'customer_id' => $this->customer()->id,
                'deal_date' => today(), 'sell_currency' => 'USD',
            ])->id,
            'description' => 'Crystal P07', 'quantity' => 1,
            'unit_cost' => 1, 'cost_currency' => 'CNY', 'unit_price' => 2,
        ]);

        Livewire::test(UndoDelete::class)
            ->dispatch('undo-delete', ['model' => DealLine::class, 'key' => $line->id])
            ->dispatch('undo-delete', ['model' => 'App\Models\NotAThing', 'key' => 1])
            ->assertOk();

        $this->assertDatabaseHas('deal_lines', ['id' => $line->id]);
    }

    // -------------------------------------------------- one place to look

    #[Test]
    public function everything_deleted_turns_up_on_one_screen(): void
    {
        $supplier = $this->supplier();
        $customer = $this->customer();
        $consignment = $this->consignment();

        $supplier->delete();
        $customer->delete();
        $consignment->delete();

        Livewire::test(RecentlyDeleted::class)
            ->assertOk()
            ->assertSee('Factory SUP-A')
            ->assertSee('Trader C-001')
            ->assertSee('16940');
    }

    #[Test]
    public function a_record_can_be_restored_from_there(): void
    {
        $supplier = $this->supplier();
        $supplier->delete();

        Livewire::test(RecentlyDeleted::class)
            ->callAction(TestAction::make('restore')->table(Supplier::class.':'.$supplier->id));

        $this->assertNotSoftDeleted($supplier);
    }

    /**
     * The commercial boundary holds on this screen too.
     *
     * A purchase is cost from end to end, so listing a deleted one — supplier,
     * number and all — would be a leak through the back of a screen the
     * assistant cannot open from the front.
     */
    #[Test]
    public function an_assistant_is_not_shown_deleted_purchases(): void
    {
        $deal = Deal::create([
            'number' => 'D-2026-0001', 'customer_id' => $this->customer()->id,
            'deal_date' => today(), 'sell_currency' => 'USD', 'rmb_usd_rate' => 7.2,
        ]);

        $purchase = $deal->purchases()->create([
            'supplier_id' => $this->supplier()->id,
            'number' => 'P-2026-0001', 'currency' => 'CNY', 'status' => 'draft',
        ]);

        $purchase->delete();

        $assistant = User::create([
            'name' => 'Assistant', 'email' => 'assistant@test.local',
            'password' => 'password', 'is_active' => true,
        ]);
        $assistant->assignRole('assistant');
        $this->actingAs($assistant);

        Livewire::test(RecentlyDeleted::class)
            ->assertOk()
            ->assertDontSee('P-2026-0001');
    }

    /** Erasing for good is the owner's alone, everywhere. */
    #[Test]
    public function an_assistant_cannot_erase_anything_permanently(): void
    {
        $supplier = $this->supplier();

        $assistant = User::create([
            'name' => 'Assistant', 'email' => 'assistant@test.local',
            'password' => 'password', 'is_active' => true,
        ]);
        $assistant->assignRole('assistant');
        $this->actingAs($assistant);

        $this->assertFalse($assistant->can('delete_record'));

        $supplier->delete();

        Livewire::test(ManageSuppliers::class)
            ->filterTable('trashed', true)
            ->assertActionHidden(TestAction::make('forceDelete')->table($supplier));
    }
}
