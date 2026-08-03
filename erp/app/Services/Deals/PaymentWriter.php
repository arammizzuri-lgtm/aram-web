<?php

namespace App\Services\Deals;

use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentAllocation;
use App\Services\Customers\CustomerAccount;
use App\Services\Documents\DocumentNumberGenerator;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Money in from customers, and matching it to what they owe.
 *
 * The design decision that makes all five of your payment situations ordinary:
 * money belongs to the *customer*, not to an invoice. It is recorded the moment
 * it arrives and matched afterwards — so an advance taken before the order
 * exists has somewhere to sit, and nothing is ever stuck waiting on bookkeeping.
 *
 * Everything is matched in USD. A payment in dollars against an invoice in
 * dinars has to meet somewhere, and the currency the business measures itself
 * in is the only sensible place.
 */
class PaymentWriter
{
    public function __construct(private readonly DocumentNumberGenerator $numbers) {}

    /**
     * Record money arriving, before deciding what it is for.
     *
     * The rate is asked for rather than inferred: a payment can land weeks
     * after the deal, at a rate that has nothing to do with the one the deal
     * was priced at.
     */
    public function receive(
        Customer $customer,
        float|string $amount,
        string $currency,
        ?float $exchangeRate = null,
        ?string $paidAt = null,
        string $method = 'cash',
        ?string $reference = null,
        ?string $notes = null,
        string $direction = 'in',
    ): CustomerPayment {
        $money = Money::of($amount, $currency);

        return CustomerPayment::create([
            'customer_id' => $customer->id,
            'number' => $this->numbers->next('customer_payment'),
            'amount' => $money->amount,
            'currency' => $currency,
            'exchange_rate' => $exchangeRate,
            'base_amount' => $this->toBase($money, $exchangeRate)->amount,
            'direction' => $direction,
            'method' => $method,
            'reference' => $reference,
            'paid_at' => $paidAt ?? today(),
            'notes' => $notes,
            'recorded_by' => auth()->id(),
        ]);
    }

    /**
     * Money going back out to a customer.
     *
     * Stored in the same table with a negative base amount, so one running
     * balance covers both directions. Two ledgers that must agree with each
     * other is two chances to disagree.
     */
    public function refund(
        Customer $customer,
        float|string $amount,
        string $currency,
        ?float $exchangeRate = null,
        ?string $paidAt = null,
        ?string $notes = null,
        string $method = 'cash',
        ?string $reference = null,
    ): CustomerPayment {
        $payment = $this->receive(
            $customer, $amount, $currency, $exchangeRate, $paidAt,
            method: $method, reference: $reference, notes: $notes, direction: 'refund',
        );

        $payment->update([
            'amount' => Money::of($amount, $currency)->negated()->amount,
            'base_amount' => $this->toBase(Money::of($amount, $currency), $exchangeRate)->negated()->amount,
        ]);

        return $payment->refresh();
    }

    /**
     * Correct a payment that has already been recorded.
     *
     * The base amount is recomputed rather than carried across, because it is
     * derived from the amount, the currency and the rate — so an edit to any of
     * the three leaves it stale. It is also the figure every balance in this
     * system is measured in, which makes a stale one wrong money everywhere at
     * once, and quietly: nothing fails, the totals are simply not true.
     *
     * The amount is taken as a magnitude and the direction decides its sign,
     * which is the same bargain the form makes with whoever fills it in.
     *
     * @param  array<string, mixed>  $data
     */
    public function amend(CustomerPayment $payment, array $data): CustomerPayment
    {
        $currency = $data['currency'] ?? $payment->currency;
        $rate = $this->rate($data['exchange_rate'] ?? $payment->exchange_rate);
        $direction = $data['direction'] ?? $payment->direction;

        $money = Money::of(abs((float) ($data['amount'] ?? $payment->amount)), $currency);
        $base = $this->toBase($money, $rate);

        if ($direction === 'refund') {
            $money = $money->negated();
            $base = $base->negated();
        }

        $payment->update([
            ...$data,
            'exchange_rate' => $rate,
            'amount' => $money->amount,
            'base_amount' => $base->amount,
        ]);

        return $payment->refresh();
    }

    /**
     * A rate of zero, or an empty box where the form hid the field because the
     * payment was in dollars, means "no rate" rather than a rate of nothing.
     */
    public function rate(mixed $value): ?float
    {
        return ((float) $value) ?: null;
    }

    /**
     * Which invoices this payment probably covers.
     *
     * Oldest first, because that is what both sides usually assume when nothing
     * is said. It is a *suggestion* — the customer may have meant a specific
     * invoice, particularly if one of them is disputed, so it arrives as
     * something to accept rather than something already done.
     *
     * @return array<int, Money> invoice id => amount in USD
     */
    public function suggestAllocation(CustomerPayment $payment): array
    {
        $remaining = $payment->unallocatedBase();

        if (! $remaining->isPositive()) {
            return [];
        }

        $suggestion = [];

        foreach ($this->openInvoices($payment->customer) as $invoice) {
            if (! $remaining->isPositive()) {
                break;
            }

            $due = $invoice->outstandingBase();

            if (! $due->isPositive()) {
                continue;
            }

            $take = $due->isGreaterThan($remaining) ? $remaining : $due;

            $suggestion[$invoice->id] = $take;
            $remaining = $remaining->minus($take);
        }

        return $suggestion;
    }

    /**
     * Match money to invoices.
     *
     * Refuses to allocate more than the payment holds or more than an invoice
     * is owed. Over-allocation is not a rounding nuisance — it makes a customer
     * look paid up on money that was never received, which is the one error in
     * this system that costs you real cash.
     *
     * @param  array<int, float|string>  $allocations  invoice id => USD amount
     */
    public function allocate(CustomerPayment $payment, array $allocations, bool $wasSuggested = false): CustomerPayment
    {
        return DB::transaction(function () use ($payment, $allocations, $wasSuggested) {
            foreach ($allocations as $invoiceId => $amount) {
                $share = Money::of($amount, 'USD');

                if (! $share->isPositive()) {
                    continue;
                }

                $invoice = CustomerInvoice::query()
                    ->with('allocations')
                    ->findOrFail($invoiceId);

                if ($invoice->customer_id !== $payment->customer_id) {
                    throw new RuntimeException("Invoice {$invoice->number} belongs to another customer.");
                }

                if ($invoice->status === 'cancelled') {
                    throw new RuntimeException("Invoice {$invoice->number} has been cancelled.");
                }

                $due = $invoice->outstandingBase();

                if ($share->isGreaterThan($due)) {
                    throw new RuntimeException(sprintf(
                        'Invoice %s only has %s outstanding.',
                        $invoice->number,
                        $due->display(),
                    ));
                }

                $available = $payment->fresh()->load('allocations')->unallocatedBase();

                if ($share->isGreaterThan($available)) {
                    throw new RuntimeException(sprintf(
                        'This payment only has %s left to match.',
                        $available->display(),
                    ));
                }

                CustomerPaymentAllocation::create([
                    'customer_payment_id' => $payment->id,
                    'customer_invoice_id' => $invoice->id,
                    // Shown against the invoice, so recorded in its own currency.
                    'amount' => $this->inInvoiceCurrency($invoice, $share)->amount,
                    'base_amount' => $share->amount,
                    'was_suggested' => $wasSuggested,
                ]);

                $this->refreshInvoice($invoice->fresh());
            }

            return $payment->fresh()->load('allocations');
        });
    }

    /** Accept the suggestion wholesale — the one-click path. */
    public function autoAllocate(CustomerPayment $payment): CustomerPayment
    {
        $suggestion = array_map(
            fn (Money $m) => $m->amount,
            $this->suggestAllocation($payment),
        );

        return $this->allocate($payment, $suggestion, wasSuggested: true);
    }

    /**
     * Spend a customer's spare credit on a new invoice.
     *
     * The four dollars left over after a payment cleared three invoices is not
     * worth a decision, and asking for one every time is how it ends up
     * forgotten on an account for a year. So it carries forward on its own: the
     * moment there is a new invoice, the oldest credit goes against it first.
     *
     * The customer's balance does not move — the money was already theirs and
     * already counted. What moves is which invoice is settled, and therefore
     * what shows as overdue.
     *
     * Nothing here is irreversible: every allocation it makes can be undone
     * from the payment it came from.
     */
    public function applyCreditTo(CustomerInvoice $invoice): Money
    {
        $customer = $invoice->customer;

        if ($customer === null || $invoice->status === 'cancelled') {
            return Money::zero('USD');
        }

        $applied = Money::zero('USD');

        foreach (app(CustomerAccount::class)->paymentsWithCredit($customer) as $payment) {
            $due = $invoice->fresh()->load('allocations')->outstandingBase();

            if (! $due->isPositive()) {
                break;
            }

            $spare = $payment->unallocatedBase();
            $take = min($spare->toFloat(), $due->toFloat());

            // Below half a cent there is nothing to move and a rounding
            // argument to be had, so leave it where it is.
            if ($take < 0.005) {
                continue;
            }

            $this->allocate($payment, [$invoice->id => $take], wasSuggested: true);

            $applied = $applied->plus(Money::of($take, 'USD'));
        }

        return $applied;
    }

    /**
     * Detach money from an invoice, returning it to the customer's credit.
     *
     * Nothing is lost when this happens — the payment is still recorded, it
     * simply stops being pointed at that invoice.
     */
    public function unallocate(CustomerPaymentAllocation $allocation): void
    {
        $invoice = $allocation->invoice;

        $allocation->delete();

        if ($invoice) {
            $this->refreshInvoice($invoice->fresh());
        }
    }

    // --------------------------------------------------------------- helpers

    /** Issued invoices, oldest first — the order money is usually meant for. */
    private function openInvoices(Customer $customer): Collection
    {
        return $customer->invoices()
            ->outstanding()
            ->with('allocations')
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();
    }

    private function refreshInvoice(CustomerInvoice $invoice): void
    {
        $invoice->load('allocations');

        $invoice->update([
            'amount_paid' => $invoice->allocations->sum('amount'),
            'status' => $invoice->isPaid() ? 'paid' : 'issued',
        ]);
    }

    private function toBase(Money $money, ?float $rate): Money
    {
        if ($money->currency === 'USD' || ! $rate) {
            return Money::of($money->amount, 'USD');
        }

        // Rates are typed the way they are quoted — foreign units per dollar —
        // so reaching dollars means dividing. See Deal::toBase().
        return Money::of($money->dividedBy($rate, Money::CALC_SCALE)->amount, 'USD');
    }

    private function inInvoiceCurrency(CustomerInvoice $invoice, Money $base): Money
    {
        if ($invoice->currency === 'USD' || ! $invoice->exchange_rate) {
            return Money::of($base->amount, $invoice->currency);
        }

        return Money::of($base->times($invoice->exchange_rate)->amount, $invoice->currency);
    }
}
