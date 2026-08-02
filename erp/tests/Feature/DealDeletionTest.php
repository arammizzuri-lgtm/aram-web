<?php

namespace Tests\Feature;

use App\Filament\Resources\Deals\Pages\EditDeal;
use App\Filament\Resources\Deals\Pages\ListDeals;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\Deal;
use App\Models\DealLine;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Deals\DealWriter;
use App\Services\Deals\InvoiceWriter;
use App\Services\Deals\PaymentWriter;
use App\Services\Deletion\DeletionImpact;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Getting rid of a deal that should not be there.
 *
 * There was one delete, inside the deal, behind a menu, and it disappeared
 * entirely the moment anything had been invoiced — with nothing on the screen
 * saying why and no alternative offered. So a deal you needed gone was simply
 * stuck, and the deleted ones, which the model has always kept, could never be
 * looked at or brought back.
 */
class DealDeletionTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Supplier $supplier;

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

        $this->customer = Customer::create([
            'code' => 'C-001', 'name' => 'Ali Trading',
            'default_currency' => 'USD', 'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'code' => 'SUP-A', 'name' => 'Yiwu', 'default_currency' => 'CNY',
        ]);
    }

    private function deal(): Deal
    {
        $deal = Deal::create([
            'number' => 'D-2026-'.str_pad((string) (Deal::withTrashed()->count() + 1), 4, '0', STR_PAD_LEFT),
            'customer_id' => $this->customer->id,
            'deal_date' => today(),
            'sell_currency' => 'USD',
            'rmb_usd_rate' => 7.2,
        ]);

        DealLine::create([
            'deal_id' => $deal->id,
            'supplier_id' => $this->supplier->id,
            'description' => 'Crystal P07',
            'quantity' => 10,
            'unit_cost' => 12.5,
            'cost_currency' => 'CNY',
            'unit_price' => 40,
        ]);

        return app(DealWriter::class)->sync($deal->fresh());
    }

    /** A deal billed and part paid — the case the old delete refused outright. */
    private function billedDeal(): Deal
    {
        $deal = $this->deal();

        $invoice = app(InvoiceWriter::class)->issueGoods($deal->fresh());

        $payment = app(PaymentWriter::class)->receive(
            customer: $this->customer,
            amount: 100,
            currency: 'USD',
            paidAt: today()->toDateString(),
        );

        app(PaymentWriter::class)->autoAllocate($payment);

        $this->assertNotNull($invoice);

        return $deal->fresh();
    }

    // ---------------------------------------------------------- from the list

    #[Test]
    public function a_deal_can_be_deleted_from_the_list(): void
    {
        $deal = $this->deal();

        Livewire::test(ListDeals::class)
            ->callAction(TestAction::make('delete')->table($deal))
            ->assertHasNoFormErrors();

        $this->assertSoftDeleted($deal);
    }

    /**
     * Deleting is a warning, not a wall — as approval is everywhere else here.
     *
     * The old rule hid the button once anything was invoiced, which is the
     * moment a deal is most likely to have been raised for the wrong customer.
     */
    #[Test]
    public function a_deal_that_has_been_invoiced_can_still_be_deleted(): void
    {
        $deal = $this->billedDeal();

        Livewire::test(ListDeals::class)
            ->assertActionVisible(TestAction::make('delete')->table($deal))
            ->callAction(TestAction::make('delete')->table($deal))
            ->assertHasNoFormErrors();

        $this->assertSoftDeleted($deal);
    }

    /**
     * The money stays where it was.
     *
     * A soft delete hides the deal; the invoice the customer is holding and the
     * payment they made are separate records and go on existing, so what they
     * owe does not move because of a decision about tidiness.
     */
    #[Test]
    public function deleting_a_deal_does_not_touch_the_money_recorded_against_it(): void
    {
        $deal = $this->billedDeal();

        $invoices = CustomerInvoice::count();
        $owed = $this->customer->fresh()->outstandingBalance();

        $deal->delete();

        $this->assertSame($invoices, CustomerInvoice::count());
        $this->assertSame($owed, $this->customer->fresh()->outstandingBalance());
    }

    // ------------------------------------------------------------- and back

    #[Test]
    public function a_deleted_deal_leaves_the_list_but_not_the_system(): void
    {
        $deal = $this->deal();
        $deal->delete();

        Livewire::test(ListDeals::class)->assertCanNotSeeTableRecords([$deal]);

        $this->assertDatabaseHas('deals', ['id' => $deal->id]);
    }

    #[Test]
    public function the_deleted_filter_finds_it_and_it_can_be_restored(): void
    {
        $deal = $this->deal();
        $deal->delete();

        Livewire::test(ListDeals::class)
            ->filterTable('trashed', true)
            ->assertCanSeeTableRecords([$deal])
            ->callAction(TestAction::make('restore')->table($deal))
            ->assertHasNoFormErrors();

        $this->assertNotSoftDeleted($deal);
    }

    // -------------------------------------------------------- for good, or not

    /**
     * Permanent deletion is walled where the database itself would refuse:
     * customer invoices point at the deal with `restrict on delete`.
     */
    #[Test]
    public function a_billed_deal_can_never_be_erased_for_good(): void
    {
        $deal = $this->billedDeal();
        $deal->delete();

        Livewire::test(ListDeals::class)
            ->filterTable('trashed', true)
            ->assertActionHidden(TestAction::make('forceDelete')->table($deal));
    }

    #[Test]
    public function an_unbilled_deal_can_be_erased_for_good_by_the_owner(): void
    {
        $deal = $this->deal();
        $deal->delete();

        Livewire::test(ListDeals::class)
            ->filterTable('trashed', true)
            ->callAction(TestAction::make('forceDelete')->table($deal))
            ->assertHasNoFormErrors();

        $this->assertDatabaseMissing('deals', ['id' => $deal->id]);

        // The lines and the purchase go with it, as the confirmation says.
        $this->assertDatabaseMissing('deal_lines', ['deal_id' => $deal->id]);
        $this->assertDatabaseMissing('deal_purchases', ['deal_id' => $deal->id]);
    }

    /**
     * An assistant may delete — they can already empty a deal line by line, and
     * the delete is restorable. Erasing one for good is the owner's alone.
     */
    #[Test]
    public function an_assistant_may_delete_but_never_permanently(): void
    {
        $assistant = User::create([
            'name' => 'Assistant', 'email' => 'assistant@test.local',
            'password' => 'password', 'is_active' => true,
        ]);
        $assistant->assignRole('assistant');

        $deal = $this->deal();
        $deal->delete();

        $this->actingAs($assistant);

        $this->assertFalse($assistant->can('delete_deal'));

        Livewire::test(ListDeals::class)
            ->filterTable('trashed', true)
            ->assertActionVisible(TestAction::make('restore')->table($deal))
            ->assertActionHidden(TestAction::make('forceDelete')->table($deal));
    }

    // ---------------------------------------------------------- the alternative

    /**
     * Cancelling, which the old delete button pointed at without ever offering.
     */
    #[Test]
    public function a_deal_can_be_cancelled_instead_with_the_reason_kept(): void
    {
        $deal = $this->deal();

        Livewire::test(EditDeal::class, ['record' => $deal->getRouteKey()])
            ->callAction('cancel', ['reason' => 'Customer went elsewhere'])
            ->assertHasNoFormErrors();

        $deal->refresh();

        $this->assertSame('cancelled', $deal->status);
        $this->assertStringContainsString('Customer went elsewhere', (string) $deal->internal_notes);

        // Cancelled is not deleted: it stays on file and stays findable.
        $this->assertNotSoftDeleted($deal);
    }

    #[Test]
    public function cancelling_is_not_offered_twice(): void
    {
        $deal = $this->deal();
        $deal->update(['status' => 'cancelled']);

        Livewire::test(EditDeal::class, ['record' => $deal->getRouteKey()])
            ->assertActionHidden('cancel');
    }

    // ------------------------------------------------------- what it tells you

    #[Test]
    public function an_untouched_deal_says_plainly_that_nothing_is_at_stake(): void
    {
        $warning = app(DeletionImpact::class)->describe($this->deal());

        $this->assertStringContainsString('Nothing else depends on this', $warning);
        $this->assertStringContainsString('Recently deleted', $warning);
    }

    /** The figures are named, because "are you sure?" is not a question. */
    #[Test]
    public function a_deal_with_money_on_it_says_exactly_what_is_at_stake(): void
    {
        $deal = $this->billedDeal();
        $impact = app(DeletionImpact::class);

        $warning = $impact->describe($deal);

        $this->assertStringContainsString('1 invoice issued to the customer', $warning);
        $this->assertStringContainsString('$100.00 received', $warning);

        // And points at the alternative rather than only refusing.
        $this->assertStringContainsString('Cancelling it instead', $impact->alternative($deal));
    }
}
