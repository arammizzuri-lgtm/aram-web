<?php

namespace Tests\Feature;

use App\Actions\Sales\AllocatePayment;
use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class PaymentAllocationTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private AllocatePayment $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            FoundationSeeder::class,
            ReferenceDataSeeder::class,
            RolePermissionSeeder::class,
            DemoDataSeeder::class,
        ]);

        $user = User::create([
            'name' => 'Accountant', 'email' => 'acc@test.local',
            'password' => 'password', 'is_active' => true,
        ]);
        $user->assignRole('accountant');
        $this->actingAs($user);

        // A clean customer, so the demo invoices do not interfere.
        $this->customer = Customer::create([
            'code' => 'CUS-TEST', 'name' => 'Test Shop',
            'credit_limit' => 50000, 'payment_terms_days' => 30, 'is_active' => true,
        ]);

        $this->action = app(AllocatePayment::class);
    }

    private function invoice(float $total, int $daysAgo = 10): Invoice
    {
        $product = Product::first();
        $date = now()->subDays($daysAgo);

        $invoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'invoice_date' => $date->toDateString(),
            'due_date' => $date->copy()->addDays(30)->toDateString(),
            'status' => 'posted',
            'invoice_type' => 'standard',
            'currency' => 'USD',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => $total,
        ]);

        $invoice->recalculateTotals();

        return $invoice->fresh();
    }

    private function payment(float $amount): Payment
    {
        return Payment::create([
            'customer_id' => $this->customer->id,
            'payment_date' => today(),
            'amount' => $amount,
            'method' => 'bank_transfer',
            'currency' => 'USD',
            'exchange_rate' => 1,
            'base_amount' => $amount,
            'unallocated_amount' => $amount,
        ]);
    }

    /** The whole reason the join table exists. */
    #[Test]
    public function one_payment_settles_several_invoices(): void
    {
        $a = $this->invoice(1000);
        $b = $this->invoice(2000);
        $c = $this->invoice(1500);

        $payment = $this->payment(5000);

        $this->action->handle($payment, [$a->id => 1000, $b->id => 2000, $c->id => 1500]);

        $this->assertSame('paid', $a->fresh()->status);
        $this->assertSame('paid', $b->fresh()->status);
        $this->assertSame('paid', $c->fresh()->status);
        $this->assertSame(3, PaymentAllocation::where('payment_id', $payment->id)->count());
    }

    #[Test]
    public function a_partial_allocation_marks_the_invoice_partially_paid(): void
    {
        $invoice = $this->invoice(1000);

        $this->action->handle($this->payment(400), [$invoice->id => 400]);

        $this->assertSame('partially_paid', $invoice->fresh()->status);
        $this->assertSame('400.00', $invoice->fresh()->amount_paid);
        $this->assertSame(600.0, $invoice->fresh()->amountDue());
    }

    /** Over-payment is credit, not something to force onto an invoice. */
    #[Test]
    public function money_left_over_stays_as_customer_credit(): void
    {
        $invoice = $this->invoice(1000);

        $payment = $this->action->handle($this->payment(1500), [$invoice->id => 1000]);

        $this->assertSame(500.0, $payment->unallocated());
        $this->assertSame('500.0000', $payment->unallocated_amount);
    }

    #[Test]
    public function an_invoice_cannot_be_over_allocated(): void
    {
        $invoice = $this->invoice(1000);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('outstanding');

        $this->action->handle($this->payment(5000), [$invoice->id => 1200]);
    }

    #[Test]
    public function more_cannot_be_allocated_than_was_paid(): void
    {
        $a = $this->invoice(1000);
        $b = $this->invoice(1000);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('of a $1,500.00 payment');

        $this->action->handle($this->payment(1500), [$a->id => 1000, $b->id => 1000]);
    }

    #[Test]
    public function a_payment_cannot_settle_another_customers_invoice(): void
    {
        $other = Customer::where('code', 'CUS-0002')->firstOrFail();
        $theirs = Invoice::where('customer_id', $other->id)->outstanding()->first()
            ?? Invoice::where('customer_id', $other->id)->first();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('another customer');

        $this->action->handle($this->payment(500), [$theirs->id => 100]);
    }

    #[Test]
    public function auto_allocation_settles_the_oldest_first(): void
    {
        $old = $this->invoice(1000, daysAgo: 90);
        $mid = $this->invoice(1000, daysAgo: 45);
        $new = $this->invoice(1000, daysAgo: 5);

        $plan = $this->action->autoAllocate($this->payment(1500));

        $this->assertSame(1000.0, $plan[$old->id]);
        $this->assertSame(500.0, $plan[$mid->id]);
        $this->assertArrayNotHasKey($new->id, $plan, 'the newest invoice is left untouched');
    }

    #[Test]
    public function clearing_puts_the_invoices_back(): void
    {
        $invoice = $this->invoice(1000);
        $payment = $this->payment(1000);

        $this->action->handle($payment, [$invoice->id => 1000]);
        $this->assertSame('paid', $invoice->fresh()->status);

        $this->action->clear($payment->fresh());

        $this->assertSame('posted', $invoice->fresh()->status);
        $this->assertSame('0.00', $invoice->fresh()->amount_paid);
        $this->assertSame(0, PaymentAllocation::where('payment_id', $payment->id)->count());
    }

    /** Re-allocating replaces rather than stacking on top of the previous set. */
    #[Test]
    public function re_allocating_replaces_the_previous_split(): void
    {
        $a = $this->invoice(1000);
        $b = $this->invoice(1000);
        $payment = $this->payment(1000);

        $this->action->handle($payment, [$a->id => 1000]);
        $this->action->handle($payment->fresh(), [$b->id => 1000]);

        $this->assertSame('posted', $a->fresh()->status);
        $this->assertSame('paid', $b->fresh()->status);
        $this->assertSame(1, PaymentAllocation::where('payment_id', $payment->id)->count());
    }

    #[Test]
    public function an_exact_settlement_is_not_rejected_by_a_rounding_fraction(): void
    {
        $invoice = $this->invoice(333.33);

        $this->action->handle($this->payment(333.33), [$invoice->id => 333.33]);

        $this->assertSame('paid', $invoice->fresh()->status);
    }

    #[Test]
    public function the_payments_screen_renders(): void
    {
        $this->invoice(500);

        Livewire::test(ManagePayments::class)->assertOk();
    }
}
