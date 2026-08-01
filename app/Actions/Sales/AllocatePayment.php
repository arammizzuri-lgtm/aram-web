<?php

namespace App\Actions\Sales;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Spreads one payment across several invoices.
 *
 * A shop pays $5,000 against four outstanding invoices — that is the normal
 * case in wholesale, not the exception. Anything left over stays on the payment
 * as customer credit rather than being forced onto an invoice that did not
 * ask for it.
 */
class AllocatePayment
{
    /** @param array<int, float> $allocations invoice_id => amount */
    public function handle(Payment $payment, array $allocations): Payment
    {
        return DB::transaction(function () use ($payment, $allocations) {
            $this->clear($payment);

            $total = 0.0;

            foreach ($allocations as $invoiceId => $amount) {
                $amount = round((float) $amount, 4);

                if ($amount <= 0) {
                    continue;
                }

                $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoiceId);

                if ($invoice->customer_id !== $payment->customer_id) {
                    throw new RuntimeException(
                        "{$invoice->number} belongs to another customer and cannot be settled by this payment."
                    );
                }

                // Rounded to 2dp before comparing: a payment settling an invoice
                // exactly must not be rejected by a fraction of a cent.
                $due = round($invoice->amountDue(), 2);

                if (round($amount, 2) > $due + 0.005) {
                    throw new RuntimeException(sprintf(
                        '%s only has $%s outstanding, but $%s was allocated to it.',
                        $invoice->number,
                        number_format($due, 2),
                        number_format($amount, 2),
                    ));
                }

                $total += $amount;

                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $amount,
                    'base_amount' => round($amount * (float) ($payment->exchange_rate ?: 1), 4),
                    'allocated_at' => now(),
                    'allocated_by' => auth()->id(),
                ]);

                $this->refreshInvoice($invoice);
            }

            if (round($total, 2) > round((float) $payment->amount, 2) + 0.005) {
                throw new RuntimeException(sprintf(
                    'Allocated $%s of a $%s payment.',
                    number_format($total, 2),
                    number_format((float) $payment->amount, 2),
                ));
            }

            $payment->forceFill([
                'unallocated_amount' => round((float) $payment->amount - $total, 4),
            ])->save();

            return $payment->fresh('allocations');
        });
    }

    /**
     * Settle the oldest invoices first.
     *
     * The conventional default, and the one that keeps the aging buckets honest
     * — paying the newest first would leave old debt looking permanently stuck.
     *
     * @return array<int, float>
     */
    public function autoAllocate(Payment $payment): array
    {
        $remaining = (float) $payment->amount;
        $plan = [];

        $invoices = Invoice::query()
            ->where('customer_id', $payment->customer_id)
            ->outstanding()
            ->orderBy('due_date')
            ->orderBy('invoice_date')
            ->get();

        foreach ($invoices as $invoice) {
            if ($remaining <= 0.005) {
                break;
            }

            $take = min($remaining, round($invoice->amountDue(), 2));

            if ($take <= 0) {
                continue;
            }

            $plan[$invoice->id] = round($take, 2);
            $remaining -= $take;
        }

        return $plan;
    }

    /** Undo an allocation set, so it can be re-entered without a credit note. */
    public function clear(Payment $payment): void
    {
        $invoiceIds = $payment->allocations()->pluck('invoice_id');

        $payment->allocations()->delete();

        Invoice::whereIn('id', $invoiceIds)->get()->each(fn (Invoice $i) => $this->refreshInvoice($i));

        $payment->forceFill(['unallocated_amount' => $payment->amount])->save();
    }

    /** Keep the invoice's paid figure and status derived from its allocations. */
    private function refreshInvoice(Invoice $invoice): void
    {
        $paid = round((float) PaymentAllocation::where('invoice_id', $invoice->id)->sum('amount'), 2);
        $total = round((float) $invoice->total, 2);

        $invoice->forceFill([
            'amount_paid' => $paid,
            'status' => match (true) {
                $paid >= $total - 0.005 => 'paid',
                $paid > 0.005 => 'partially_paid',
                default => 'posted',
            },
        ])->saveQuietly();
    }
}
