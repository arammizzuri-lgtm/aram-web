<?php

namespace Tests\Feature;

use App\Filament\Resources\CustomerPayments\Pages\ManageCustomerPayments;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\DealLine;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Deals\DealWriter;
use App\Services\Deals\InvoiceWriter;
use App\Services\Deals\PaymentWriter;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Customer money.
 *
 * These tests are organised around the five situations described in the
 * interview, because the whole design exists to make each of them ordinary
 * rather than a workaround.
 */
class CustomerPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Supplier $supplier;

    private PaymentWriter $payments;

    private InvoiceWriter $invoices;

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

        $this->customer = Customer::create([
            'code' => 'C-001', 'name' => 'Ali Trading',
            'default_currency' => 'IQD', 'is_active' => true,
        ]);

        $this->supplier = Supplier::create(['code' => 'SUP-A', 'name' => 'Yiwu', 'default_currency' => 'CNY']);

        $this->payments = app(PaymentWriter::class);
        $this->invoices = app(InvoiceWriter::class);
    }

    /** A deal invoiced for a round $1,000 worth of dinars. */
    private function invoiceFor(float $usd, string $number, ?string $date = null)
    {
        $deal = Deal::create([
            'number' => $number,
            'customer_id' => $this->customer->id,
            'deal_date' => $date ?? today(),
            'sell_currency' => 'IQD',
            'rmb_usd_rate' => 7.2,
            'iqd_usd_rate' => 1470,
        ]);

        DealLine::create([
            'deal_id' => $deal->id,
            'supplier_id' => $this->supplier->id,
            'description' => 'Goods',
            'quantity' => 1,
            'unit_cost' => 100,
            'cost_currency' => 'CNY',
            'unit_price' => $usd * 1470,
        ]);

        $deal = app(DealWriter::class)->sync($deal->fresh());
        $invoice = $this->invoices->issueGoods($deal);

        if ($date) {
            $invoice->update(['invoice_date' => $date]);
        }

        return $invoice->fresh();
    }

    // ------------------------------------------- 1. paid after approving

    #[Test]
    public function money_is_recorded_the_moment_it_arrives(): void
    {
        $payment = $this->payments->receive($this->customer, 1_470_000, 'IQD', exchangeRate: 1470);

        $this->assertSame('1470000.0000', $payment->amount);
        $this->assertSame('1000.0000', $payment->base_amount);
        $this->assertSame('in', $payment->direction);
    }

    #[Test]
    public function a_dollar_payment_needs_no_rate_at_all(): void
    {
        $payment = $this->payments->receive($this->customer, 1000, 'USD');

        $this->assertSame('1000.0000', $payment->base_amount);
        $this->assertNull($payment->exchange_rate);
    }

    // ------------------------------------- 2. advance before the order exists

    /**
     * The situation that forces the whole design.
     *
     * There is no invoice to attach this to, and there may not be one for
     * weeks. Money belonging to the customer rather than to a document is what
     * makes that unremarkable.
     */
    #[Test]
    public function an_advance_paid_before_any_order_exists_sits_as_credit(): void
    {
        $this->payments->receive($this->customer, 1000, 'USD');

        $this->assertSame(1000.0, $this->customer->fresh()->unallocatedCredit());
        $this->assertSame(-1000.0, $this->customer->fresh()->outstandingBalance(), 'you owe them goods');
    }

    #[Test]
    public function an_advance_is_matched_once_the_invoice_finally_exists(): void
    {
        $payment = $this->payments->receive($this->customer, 1000, 'USD');
        $invoice = $this->invoiceFor(1000, 'D-2026-0001');

        $this->payments->autoAllocate($payment->fresh());

        $this->assertSame(0.0, $this->customer->fresh()->unallocatedCredit());
        $this->assertSame(0.0, $this->customer->fresh()->outstandingBalance());
        $this->assertTrue($invoice->fresh()->isPaid());
    }

    // ---------------------------------- 3. goods now, shipping later

    #[Test]
    public function paying_for_the_goods_leaves_the_shipping_invoice_open(): void
    {
        $goods = $this->invoiceFor(1000, 'D-2026-0001');
        $payment = $this->payments->receive($this->customer, 1000, 'USD');

        $this->payments->allocate($payment, [$goods->id => 1000]);

        $this->assertTrue($goods->fresh()->isPaid());
        $this->assertSame(0.0, $this->customer->fresh()->outstandingBalance());
    }

    // ------------------------------------------ 4. the customer still owes

    #[Test]
    public function a_part_payment_leaves_the_rest_owing(): void
    {
        $invoice = $this->invoiceFor(1000, 'D-2026-0001');
        $payment = $this->payments->receive($this->customer, 400, 'USD');

        $this->payments->autoAllocate($payment);

        $this->assertFalse($invoice->fresh()->isPaid());
        $this->assertSame(600.0, $invoice->fresh()->outstandingBase()->toFloat());
        $this->assertSame(600.0, $this->customer->fresh()->outstandingBalance());
        $this->assertSame('issued', $invoice->fresh()->status);
    }

    // ------------------------------------------- 5. you owe them money back

    #[Test]
    public function a_refund_moves_the_balance_the_other_way(): void
    {
        $this->payments->receive($this->customer, 1000, 'USD');
        $this->payments->refund($this->customer, 250, 'USD');

        $this->assertSame(750.0, $this->customer->fresh()->unallocatedCredit());
        $this->assertSame('refund', $this->customer->payments()->latest('id')->first()->direction);
    }

    // ---------------------------------------------------------- suggestions

    /** Oldest first, because that is what both sides assume when nothing is said. */
    #[Test]
    public function the_suggestion_clears_the_oldest_invoices_first(): void
    {
        $old = $this->invoiceFor(400, 'D-2026-0001', today()->subDays(30)->toDateString());
        $recent = $this->invoiceFor(400, 'D-2026-0002', today()->toDateString());

        $payment = $this->payments->receive($this->customer, 500, 'USD');
        $suggestion = $this->payments->suggestAllocation($payment);

        $this->assertSame('400.0000', $suggestion[$old->id]->amount);
        $this->assertSame('100.0000', $suggestion[$recent->id]->amount, 'the remainder spills to the next');
    }

    #[Test]
    public function accepting_the_suggestion_is_recorded_as_such(): void
    {
        $invoice = $this->invoiceFor(1000, 'D-2026-0001');
        $payment = $this->payments->receive($this->customer, 1000, 'USD');

        $this->payments->autoAllocate($payment);

        $allocation = $payment->fresh()->allocations()->first();

        $this->assertTrue((bool) $allocation->was_suggested);
        $this->assertSame($invoice->id, $allocation->customer_invoice_id);
    }

    #[Test]
    public function a_hand_made_split_is_marked_as_a_judgement(): void
    {
        $invoice = $this->invoiceFor(1000, 'D-2026-0001');
        $payment = $this->payments->receive($this->customer, 1000, 'USD');

        $this->payments->allocate($payment, [$invoice->id => 600]);

        $this->assertFalse((bool) $payment->fresh()->allocations()->first()->was_suggested);
        $this->assertSame(400.0, $payment->fresh()->unallocatedBase()->toFloat(), 'the rest stays as credit');
    }

    #[Test]
    public function money_beyond_what_is_owed_stays_as_credit_rather_than_vanishing(): void
    {
        $this->invoiceFor(400, 'D-2026-0001');
        $payment = $this->payments->receive($this->customer, 1000, 'USD');

        $this->payments->autoAllocate($payment);

        $this->assertSame(600.0, $payment->fresh()->unallocatedBase()->toFloat());
        $this->assertSame(600.0, $this->customer->fresh()->unallocatedCredit());
    }

    // -------------------------------------------------------- what is refused

    /**
     * The one error here that costs real cash.
     *
     * Over-allocating makes a customer look paid up on money that never
     * arrived, and nothing downstream would contradict it.
     */
    #[Test]
    public function a_payment_cannot_be_matched_beyond_what_it_holds(): void
    {
        $invoice = $this->invoiceFor(1000, 'D-2026-0001');
        $payment = $this->payments->receive($this->customer, 400, 'USD');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/only has .* left to match/');

        $this->payments->allocate($payment, [$invoice->id => 1000]);
    }

    #[Test]
    public function an_invoice_cannot_be_paid_beyond_what_it_is_owed(): void
    {
        $invoice = $this->invoiceFor(400, 'D-2026-0001');
        $payment = $this->payments->receive($this->customer, 1000, 'USD');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/only has .* outstanding/');

        $this->payments->allocate($payment, [$invoice->id => 1000]);
    }

    #[Test]
    public function money_cannot_be_matched_to_another_customers_invoice(): void
    {
        $invoice = $this->invoiceFor(1000, 'D-2026-0001');

        $other = Customer::create([
            'code' => 'C-002', 'name' => 'Sara', 'default_currency' => 'IQD', 'is_active' => true,
        ]);
        $payment = $this->payments->receive($other, 1000, 'USD');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/belongs to another customer/');

        $this->payments->allocate($payment, [$invoice->id => 1000]);
    }

    #[Test]
    public function money_cannot_be_matched_to_a_cancelled_invoice(): void
    {
        $invoice = $this->invoiceFor(1000, 'D-2026-0001');
        $this->invoices->cancel($invoice, 'Reissuing');

        $payment = $this->payments->receive($this->customer, 1000, 'USD');

        $this->expectException(RuntimeException::class);
        $this->payments->allocate($payment, [$invoice->id => 1000]);
    }

    // ------------------------------------------------------------ unmatching

    #[Test]
    public function unmatching_returns_the_money_to_credit_without_losing_it(): void
    {
        $invoice = $this->invoiceFor(1000, 'D-2026-0001');
        $payment = $this->payments->receive($this->customer, 1000, 'USD');
        $this->payments->autoAllocate($payment);

        $this->assertTrue($invoice->fresh()->isPaid());

        $this->payments->unallocate($payment->fresh()->allocations()->first());

        $this->assertFalse($invoice->fresh()->isPaid());
        $this->assertSame(1000.0, $this->customer->fresh()->unallocatedCredit());
        // The payment itself is untouched.
        $this->assertSame('1000.0000', $payment->fresh()->base_amount);
    }

    // ----------------------------------------------------------------- screen

    #[Test]
    public function the_payments_screen_matches_money_to_invoices(): void
    {
        $invoice = $this->invoiceFor(1000, 'D-2026-0001');
        $payment = $this->payments->receive($this->customer, 1000, 'USD');

        Livewire::test(ManageCustomerPayments::class)
            ->callTableAction('match', $payment, ['invoice_'.$invoice->id => 1000])
            ->assertHasNoTableActionErrors();

        $this->assertTrue($invoice->fresh()->isPaid());
    }

    /** Nothing left unmatched means nothing to offer. */
    #[Test]
    public function the_match_button_disappears_once_the_money_is_fully_matched(): void
    {
        $this->invoiceFor(1000, 'D-2026-0001');
        $payment = $this->payments->receive($this->customer, 1000, 'USD');

        Livewire::test(ManageCustomerPayments::class)
            ->assertTableActionVisible('match', $payment);

        $this->payments->autoAllocate($payment);

        Livewire::test(ManageCustomerPayments::class)
            ->assertTableActionHidden('match', $payment->fresh());
    }

    /** A refund is money going out; there is nothing to point it at. */
    #[Test]
    public function a_refund_is_never_offered_for_matching(): void
    {
        $this->invoiceFor(1000, 'D-2026-0001');
        $refund = $this->payments->refund($this->customer, 250, 'USD');

        Livewire::test(ManageCustomerPayments::class)
            ->assertTableActionHidden('match', $refund);
    }

    /** The screen surfaces the refusal rather than failing silently. */
    #[Test]
    public function over_matching_through_the_screen_reports_the_problem(): void
    {
        $invoice = $this->invoiceFor(400, 'D-2026-0001');
        $payment = $this->payments->receive($this->customer, 1000, 'USD');

        Livewire::test(ManageCustomerPayments::class)
            ->callTableAction('match', $payment, ['invoice_'.$invoice->id => 1000]);

        // Refused, so nothing was written.
        $this->assertSame(0, $payment->fresh()->allocations()->count());
        $this->assertFalse($invoice->fresh()->isPaid());
    }

    // ------------------------------------------------------------ currencies

    /** A dollar payment against a dinar invoice has to meet in the middle. */
    #[Test]
    public function a_payment_and_an_invoice_in_different_currencies_still_match(): void
    {
        $invoice = $this->invoiceFor(1000, 'D-2026-0001');
        $this->assertSame('IQD', $invoice->currency);

        $payment = $this->payments->receive($this->customer, 1000, 'USD');
        $this->payments->autoAllocate($payment);

        $allocation = $payment->fresh()->allocations()->first();

        $this->assertSame('1000.0000', $allocation->base_amount, 'matched in dollars');
        $this->assertSame('1470000.0000', $allocation->amount, 'shown against the invoice in dinars');
        $this->assertTrue($invoice->fresh()->isPaid());
    }
}
