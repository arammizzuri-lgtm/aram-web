<?php

namespace Tests\Feature;

use App\Filament\Resources\CustomerPayments\Pages\ManageCustomerPayments;
use App\Filament\Resources\Customers\Pages\CustomerAccount as AccountPage;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerPayment;
use App\Models\Deal;
use App\Models\DealLine;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Customers\CustomerAccount;
use App\Services\Deals\InvoiceWriter;
use App\Services\Deals\PaymentWriter;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The customer account, read the way a bank statement reads.
 *
 * The system's own arithmetic is a receivable — invoiced less received, so a
 * customer who owes you is a positive number. On this screen the sign is turned
 * over: what they paid counts up, what you invoiced counts down, and a balance
 * below zero means they owe you.
 *
 * The two must never disagree, which is most of what these tests are for.
 */
class CustomerAccountTest extends TestCase
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
            'code' => 'C-001', 'name' => 'Kavi Botique',
            'default_currency' => 'USD', 'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'code' => 'SUP-A', 'name' => 'Yiwu', 'default_currency' => 'CNY',
        ]);
    }

    private function account(): CustomerAccount
    {
        return app(CustomerAccount::class);
    }

    /** A deal worth billing, priced in dollars so the arithmetic is readable. */
    private function deal(string $number, float $unitPrice, float $quantity = 1): Deal
    {
        $deal = Deal::create([
            'number' => $number,
            'customer_id' => $this->customer->id,
            'deal_date' => today(),
            'sell_currency' => 'USD',
            'rmb_usd_rate' => 7.2,
        ]);

        DealLine::create([
            'deal_id' => $deal->id,
            'supplier_id' => $this->supplier->id,
            'description' => 'Crystal P07',
            'quantity' => $quantity,
            'unit_cost' => 1,
            'cost_currency' => 'CNY',
            'unit_price' => $unitPrice,
        ]);

        return $deal->fresh();
    }

    private function invoice(string $number, float $amount): CustomerInvoice
    {
        return app(InvoiceWriter::class)->issueGoods($this->deal($number, $amount));
    }

    private function pay(float $amount): CustomerPayment
    {
        return app(PaymentWriter::class)->receive(
            customer: $this->customer,
            amount: $amount,
            currency: 'USD',
            paidAt: today()->toDateString(),
        );
    }

    // ------------------------------------------------------------- the sign

    /** Below zero, they owe you. That is the whole convention. */
    #[Test]
    public function a_customer_who_owes_you_reads_below_zero(): void
    {
        $this->invoice('D-2026-0001', 2340);

        $balance = $this->account()->balance($this->customer);

        $this->assertSame('-2340.0000', $balance->amount);
        $this->assertTrue($balance->isNegative());
    }

    #[Test]
    public function money_held_for_a_customer_reads_above_zero(): void
    {
        $this->pay(500);

        $this->assertSame('500.0000', $this->account()->balance($this->customer)->amount);
    }

    /**
     * The account view and the receivable are one arithmetic, negated.
     *
     * Two ways of working out the same figure is two figures that can drift
     * apart, and the day they do the screen and the report disagree about what
     * a customer owes.
     */
    #[Test]
    public function the_account_view_is_the_receivable_turned_over(): void
    {
        $this->invoice('D-2026-0001', 1000);
        $this->pay(400);

        $this->assertSame(
            -$this->customer->fresh()->outstandingBalance(),
            $this->account()->balance($this->customer->fresh())->toFloat(),
        );
    }

    // -------------------------------------------------------- the statement

    #[Test]
    public function the_statement_runs_a_balance_down_the_page(): void
    {
        $this->invoice('D-2026-0001', 1000);
        $this->pay(400);

        $statement = $this->account()->statement($this->customer->fresh());

        $this->assertCount(2, $statement);

        // Invoiced first: a thousand out, so a thousand owed.
        $this->assertSame('spending', $statement[0]['kind']);
        $this->assertSame('-1000.0000', $statement[0]['balance']->amount);

        // Then four hundred in, leaving six hundred owed.
        $this->assertSame('deposit', $statement[1]['kind']);
        $this->assertSame('-600.0000', $statement[1]['balance']->amount);
    }

    #[Test]
    public function a_refund_counts_against_them_like_an_invoice_does(): void
    {
        $this->pay(500);

        app(PaymentWriter::class)->refund(
            customer: $this->customer,
            amount: 200,
            currency: 'USD',
            paidAt: today()->toDateString(),
        );

        $statement = $this->account()->statement($this->customer->fresh());

        $this->assertSame('withdrawal', $statement->last()['kind']);
        $this->assertSame('300.0000', $this->account()->balance($this->customer->fresh())->amount);
    }

    // ----------------------------------------------------------- how overdue

    #[Test]
    public function what_is_owed_is_split_by_how_old_it_is(): void
    {
        $fresh = $this->invoice('D-2026-0001', 100);
        $old = $this->invoice('D-2026-0002', 250);

        $old->update(['invoice_date' => today()->subDays(120)]);

        $ageing = $this->account()->ageing($this->customer->fresh());

        $this->assertSame('100.0000', $ageing['current']->amount);
        $this->assertSame('250.0000', $ageing['90']->amount);
        $this->assertSame('0.0000', $ageing['30']->amount);
        $this->assertNotNull($fresh);
    }

    // ------------------------------------------------- credit carried forward

    /**
     * The four dollars left over after a payment cleared its invoices.
     *
     * Not worth a decision, and asking for one every time is how it ends up
     * forgotten on an account for a year — so it goes onto the next invoice by
     * itself.
     */
    #[Test]
    public function leftover_credit_lands_on_the_next_invoice_by_itself(): void
    {
        $this->invoice('D-2026-0001', 95.23);

        // Paid a round hundred against it, leaving $4.77 spare.
        $payment = $this->pay(100);
        app(PaymentWriter::class)->autoAllocate($payment);

        $this->assertSame('4.7700', $payment->fresh()->load('allocations')->unallocatedBase()->amount);

        // The next invoice takes the remainder without being asked.
        $second = $this->invoice('D-2026-0002', 50);

        $this->assertSame('4.7700', $second->fresh()->load('allocations')->paidBase()->amount);
        $this->assertSame('45.2300', $second->fresh()->load('allocations')->outstandingBase()->amount);
        $this->assertFalse($payment->fresh()->load('allocations')->unallocatedBase()->isPositive());
    }

    /**
     * The same payment, twice, against the same invoice.
     *
     * The allocations table holds a unique key on the payment and invoice pair,
     * and allocate() always inserted — so the second time round it failed at the
     * database with an integrity violation and a stack trace. That sounds like
     * an edge case and is not: credit carried forward does exactly this the
     * moment a remainder lands on an invoice the payment already part-covers.
     * The second time is a top-up, not a second match.
     */
    #[Test]
    public function topping_up_an_existing_match_adds_to_it(): void
    {
        $invoice = $this->invoice('D-2026-0001', 100);
        $payment = $this->pay(100);

        app(PaymentWriter::class)->allocate($payment, [$invoice->id => 40]);
        app(PaymentWriter::class)->allocate($payment, [$invoice->id => 30]);

        // One row, holding both — not two rows and not an exception.
        $this->assertSame(1, $payment->fresh()->allocations()->count());
        $this->assertSame('70.0000', $invoice->fresh()->load('allocations')->paidBase()->amount);
        $this->assertSame('30.0000', $payment->fresh()->load('allocations')->unallocatedBase()->amount);
    }

    /**
     * And the way it actually happened: a shipping invoice raised later, on a
     * deal whose goods invoice the same payment is already sitting against.
     */
    #[Test]
    public function credit_can_land_on_an_invoice_the_payment_already_part_covers(): void
    {
        $deal = $this->deal('D-2026-0005', 355.23);
        $goods = app(InvoiceWriter::class)->issueGoods($deal);

        $payment = $this->pay(5050);
        app(PaymentWriter::class)->autoAllocate($payment);

        $this->assertTrue($goods->fresh()->load('allocations')->isPaid());

        // The shipping bill turns up afterwards and the credit reaches for it.
        $shipping = app(InvoiceWriter::class)->issueShipping($deal->fresh(), 25);

        $this->assertTrue($shipping->fresh()->load('allocations')->isPaid());
        $this->assertSame('4669.7700', $this->account()->credit($this->customer->fresh())->amount);
    }

    /** An advance paid before there was anything to pay for behaves the same. */
    #[Test]
    public function an_advance_is_spent_on_the_first_invoice_that_appears(): void
    {
        $this->pay(1000);

        $invoice = $this->invoice('D-2026-0001', 400);

        $this->assertTrue($invoice->fresh()->load('allocations')->isPaid());

        // Still six hundred of theirs in hand, and the balance says so.
        $this->assertSame('600.0000', $this->account()->credit($this->customer->fresh())->amount);
        $this->assertSame('600.0000', $this->account()->balance($this->customer->fresh())->amount);
    }

    /**
     * Matching moves no money.
     *
     * Worth pinning down, because it is the thing most easily misread on this
     * screen: the balance is what came in against what went out, whether or not
     * anybody has said which payment settles which invoice.
     */
    #[Test]
    public function matching_a_payment_does_not_change_the_balance(): void
    {
        $this->invoice('D-2026-0001', 300);
        $payment = $this->pay(300);

        $before = $this->account()->balance($this->customer->fresh())->amount;

        app(PaymentWriter::class)->autoAllocate($payment);

        $this->assertSame($before, $this->account()->balance($this->customer->fresh())->amount);
    }

    // ----------------------------------------------------------- unmatching

    /**
     * Taking money back off an invoice.
     *
     * `unallocate()` was written and never called from anywhere, so a payment
     * matched to the wrong invoice stayed matched to it — and an invoice with
     * money against it cannot be cancelled either.
     */
    #[Test]
    public function a_payment_can_be_taken_back_off_an_invoice(): void
    {
        $invoice = $this->invoice('D-2026-0001', 300);
        $payment = $this->pay(300);

        app(PaymentWriter::class)->autoAllocate($payment);

        $this->assertTrue($invoice->fresh()->load('allocations')->isPaid());

        Livewire::test(ManageCustomerPayments::class)
            ->callAction(TestAction::make('unmatch')->table($payment), [
                'allocations' => $payment->fresh()->allocations->pluck('id')->all(),
            ])
            ->assertHasNoFormErrors();

        $this->assertFalse($invoice->fresh()->load('allocations')->isPaid());

        // The money is not lost — it is credit again, and the balance is where
        // it was the whole time.
        $this->assertSame('300.0000', $payment->fresh()->load('allocations')->unallocatedBase()->amount);
        $this->assertSame('0.0000', $this->account()->balance($this->customer->fresh())->amount);
    }

    // --------------------------------------------------------------- the page

    #[Test]
    public function the_account_page_shows_the_balance_and_the_statement(): void
    {
        $this->invoice('D-2026-0001', 2340);
        $this->pay(1000);

        Livewire::test(AccountPage::class, ['record' => $this->customer->getRouteKey()])
            ->assertOk()
            ->assertSee('Kavi Botique')
            ->assertSee('Account balance')
            ->assertSee('they owe you')
            ->assertSee('Statement')
            ->assertSee('D-2026-0001');
    }

    #[Test]
    public function the_page_says_when_you_are_holding_their_money(): void
    {
        $this->pay(750);

        Livewire::test(AccountPage::class, ['record' => $this->customer->getRouteKey()])
            ->assertOk()
            ->assertSee('credit you are holding');
    }

    /** The chart needs twelve points and a zero line whatever the data. */
    #[Test]
    public function the_balance_chart_is_drawable_even_on_an_empty_account(): void
    {
        $page = Livewire::test(AccountPage::class, ['record' => $this->customer->getRouteKey()]);

        $chart = $page->instance()->chart();

        $this->assertCount(12, $chart['months']);
        $this->assertNotEmpty($chart['points']);
        $this->assertIsFloat($chart['zero']);
    }

    #[Test]
    public function a_payment_recorded_on_the_account_is_matched_straight_away(): void
    {
        $this->invoice('D-2026-0001', 200);

        Livewire::test(AccountPage::class, ['record' => $this->customer->getRouteKey()])
            ->callAction('receive', [
                'amount' => 250,
                'currency' => 'USD',
                'method' => 'cash',
                'paid_at' => today(),
            ])
            ->assertHasNoFormErrors();

        // Matched against what was outstanding, with the rest left as credit.
        $this->assertSame('50.0000', $this->account()->credit($this->customer->fresh())->amount);
        $this->assertSame('50.0000', $this->account()->balance($this->customer->fresh())->amount);
    }
}
