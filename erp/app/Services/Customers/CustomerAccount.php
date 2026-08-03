<?php

namespace App\Services\Customers;

use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerPayment;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A customer's account, read the way a bank statement reads.
 *
 * The system's own arithmetic is a receivable — invoiced less received, so a
 * customer who owes you is a positive number. That is correct accounting and it
 * is not how anybody thinks about a customer in front of them. Here the sign is
 * turned over: money the customer put in counts up, what they bought counts
 * down, and a balance below zero means they owe you.
 *
 *     deposits  +   what they paid you
 *     spending  −   what you invoiced them
 *     withdrawal −  what you refunded
 *     ─────────────────────────────────
 *     balance       below zero: they owe you
 *                   above zero: you are holding their money
 *
 * The two views are the same fact and must never disagree, so the balance here
 * is derived from `Customer::outstandingBalance()` rather than added up again
 * — one arithmetic, negated, instead of two that can drift apart.
 *
 * Matching a payment to an invoice moves nothing on this page, and that is
 * worth knowing: the balance is what came in against what went out, whether or
 * not anybody has said which payment settles which invoice. Matching decides
 * *which* invoice is settled — for the customer's own records, and for how
 * overdue the rest is.
 */
class CustomerAccount
{
    /** Below zero, they owe you. Above it, you are holding their money. */
    public function balance(Customer $customer): Money
    {
        return Money::of(-$customer->outstandingBalance(), 'USD');
    }

    /** Money received that has not been pointed at an invoice yet. */
    public function credit(Customer $customer): Money
    {
        return Money::of(max(0, $customer->unallocatedCredit()), 'USD');
    }

    /** What they owe on invoices, ignoring any credit held against it. */
    public function owed(Customer $customer): Money
    {
        $owed = $customer->invoices()
            ->whereNot('status', 'cancelled')
            ->with('allocations')
            ->get()
            ->sum(fn (CustomerInvoice $invoice) => max(0, $invoice->outstandingBase()->toFloat()));

        return Money::of($owed, 'USD');
    }

    /**
     * Every movement, oldest first, with the balance after each one.
     *
     * One list rather than an invoice list beside a payment list: what somebody
     * wants to know is what happened between them and this customer, in the
     * order it happened.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function statement(Customer $customer): Collection
    {
        $movements = collect();

        if (abs((float) $customer->opening_balance) > 0.005) {
            $movements->push([
                'date' => $customer->created_at ?? now(),
                'sort' => 0,
                'kind' => 'opening',
                'title' => 'Opening balance',
                'detail' => 'Carried in when they were added',
                // An opening balance is what they already owed, so it counts
                // against them exactly as an invoice does.
                'change' => Money::of(-(float) $customer->opening_balance, 'USD'),
                'record' => null,
            ]);
        }

        foreach ($customer->invoices()->whereNot('status', 'cancelled')->with('deal')->get() as $invoice) {
            $movements->push([
                'date' => $invoice->invoice_date ?? $invoice->created_at,
                'sort' => $invoice->id,
                'kind' => 'spending',
                'title' => $invoice->number,
                'detail' => trim(($invoice->type === 'shipping' ? 'Shipping' : 'Goods')
                    .($invoice->deal ? ' · '.$invoice->deal->number : '')),
                'change' => Money::of(-(float) $invoice->total_base, 'USD'),
                'record' => $invoice,
            ]);
        }

        foreach ($customer->payments()->get() as $payment) {
            $isRefund = $payment->isRefund();

            $movements->push([
                'date' => $payment->paid_at ?? $payment->created_at,
                'sort' => $payment->id,
                'kind' => $isRefund ? 'withdrawal' : 'deposit',
                'title' => $payment->number,
                'detail' => trim(ucfirst((string) $payment->method)
                    .($payment->reference ? ' · '.$payment->reference : '')),
                // base_amount is already negative on a refund — the sign is the
                // record's, not something worked out again here.
                'change' => Money::of((float) $payment->base_amount, 'USD'),
                'record' => $payment,
            ]);
        }

        $running = 0.0;

        return $movements
            ->sortBy([['date', 'asc'], ['sort', 'asc']])
            ->values()
            ->map(function (array $movement) use (&$running): array {
                $running += $movement['change']->toFloat();

                $movement['balance'] = Money::of($running, 'USD');

                return $movement;
            });
    }

    /**
     * What is owed, split by how long it has been owed.
     *
     * Measured from the invoice date rather than a due date, because the terms
     * here are a conversation more often than a number on a document. The
     * buckets are the ordinary ones, and their point is that the rightmost
     * column is the one that turns into a bad debt.
     *
     * @return array<string, Money>
     */
    public function ageing(Customer $customer): array
    {
        $buckets = ['current' => 0.0, '30' => 0.0, '60' => 0.0, '90' => 0.0];

        $invoices = $customer->invoices()
            ->whereNot('status', 'cancelled')
            ->with('allocations')
            ->get();

        foreach ($invoices as $invoice) {
            $outstanding = $invoice->outstandingBase()->toFloat();

            if ($outstanding <= 0.005) {
                continue;
            }

            $days = $invoice->invoice_date
                ? (int) $invoice->invoice_date->diffInDays(now())
                : 0;

            $bucket = match (true) {
                $days <= 30 => 'current',
                $days <= 60 => '30',
                $days <= 90 => '60',
                default => '90',
            };

            $buckets[$bucket] += $outstanding;
        }

        return array_map(fn (float $amount) => Money::of($amount, 'USD'), $buckets);
    }

    /**
     * The balance at the end of each of the last months.
     *
     * A running total rather than a per-month figure: the question the chart
     * answers is "is this drifting?", and only a cumulative line can show a
     * customer who settles a little later every month.
     *
     * @return Collection<int, array{month: Carbon, balance: float}>
     */
    public function balanceByMonth(Customer $customer, int $months = 12): Collection
    {
        $statement = $this->statement($customer);

        return collect(range($months - 1, 0))->map(function (int $back) use ($statement): array {
            $end = now()->subMonths($back)->endOfMonth();

            $upToNow = $statement->filter(
                fn (array $movement) => Carbon::parse($movement['date'])->lessThanOrEqualTo($end)
            );

            // Months before the account had any movement at all sit at zero;
            // `last()` returns null there, and reaching into it for a balance is
            // how the whole page fell over.
            $latest = $upToNow->last();

            return [
                'month' => $end->copy()->startOfMonth(),
                'balance' => round($latest === null ? 0.0 : $latest['balance']->toFloat(), 2),
            ];
        });
    }

    /**
     * Payments with money still spare on them, oldest first.
     *
     * The order matters: credit is used up in the order it arrived, so a
     * customer's oldest advance is the first thing spent.
     *
     * @return Collection<int, CustomerPayment>
     */
    public function paymentsWithCredit(Customer $customer): Collection
    {
        return $customer->payments()
            ->where('direction', 'in')
            ->with('allocations')
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (CustomerPayment $payment) => $payment->unallocatedBase()->isPositive())
            ->values();
    }
}
