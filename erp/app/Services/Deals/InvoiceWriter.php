<?php

namespace App\Services\Deals;

use App\Models\CustomerInvoice;
use App\Models\CustomerInvoiceLine;
use App\Models\Deal;
use App\Models\DealLine;
use App\Services\Documents\DocumentNumberGenerator;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The documents the customer actually receives.
 *
 * Two rules run through all of this:
 *
 * 1. An invoice is a snapshot. Lines are copied at issue, so editing the deal
 *    afterwards cannot change a document already in someone's hands. A customer
 *    holding a printed invoice and you looking at a screen must never see
 *    different numbers — that is how arguments start.
 *
 * 2. Cost never appears, in print or in the rows behind it.
 *
 * One deal produces more than one of these: the goods now, the shipping once
 * the freight bill arrives.
 */
class InvoiceWriter
{
    public function __construct(private readonly DocumentNumberGenerator $numbers) {}

    /**
     * Bill the goods.
     *
     * Refuses to issue a second goods invoice for the same deal — that is
     * almost always a mistake, and the customer would end up owing twice for
     * one order. Cancel the first if it really needs replacing.
     */
    public function issueGoods(Deal $deal, ?string $dueDate = null): CustomerInvoice
    {
        $deal->loadMissing('lines', 'customer');

        if ($deal->lines->isEmpty()) {
            throw new RuntimeException('There is nothing to invoice on this deal.');
        }

        $existing = $deal->invoices()
            ->where('type', 'goods')
            ->whereNot('status', 'cancelled')
            ->exists();

        if ($existing) {
            throw new RuntimeException('This deal already has a goods invoice. Cancel it first if it needs replacing.');
        }

        return DB::transaction(function () use ($deal, $dueDate) {
            $invoice = $this->open($deal, 'goods', $dueDate);

            $subtotal = Money::zero($invoice->currency);

            foreach ($deal->lines as $index => $line) {
                /** @var DealLine $line */
                $lineTotal = Money::of($line->unit_price, $invoice->currency)->times($line->quantity);

                CustomerInvoiceLine::create([
                    'customer_invoice_id' => $invoice->id,
                    'deal_line_id' => $line->id,
                    'description' => $line->description,
                    'description_ku' => $line->description_ku,
                    'specification' => $line->specification,
                    'quantity' => $line->quantity,
                    'unit' => $line->unit,
                    'unit_price' => $line->unit_price,
                    'line_total' => $lineTotal->amount,
                    'display_order' => $line->display_order ?: $index,
                ]);

                $subtotal = $subtotal->plus($lineTotal);
            }

            /*
             * The deal commission is charged, not hidden.
             *
             * It is part of what the customer agreed to pay, so it belongs on
             * their invoice as its own line rather than being smeared across
             * the goods — which would misstate every unit price on the page.
             */
            if ((float) $deal->deal_commission > 0) {
                $commission = $this->commissionInSellCurrency($deal, $invoice->currency);

                CustomerInvoiceLine::create([
                    'customer_invoice_id' => $invoice->id,
                    'description' => 'Service & handling',
                    'quantity' => 1,
                    'unit' => 'item',
                    'unit_price' => $commission->amount,
                    'line_total' => $commission->amount,
                    'display_order' => 9_999,
                ]);

                $subtotal = $subtotal->plus($commission);
            }

            /*
             * Whatever was taken off the whole order, as its own row.
             *
             * Not spread back across the unit prices: the customer agreed a
             * price per piece and the invoice has to go on saying it, or the
             * document stops reconciling against the quotation they approved.
             * Taken from the deal's frozen figure so the two agree by
             * construction rather than by both being calculated correctly.
             */
            return $this->close($invoice, $deal, $subtotal, $deal->customerDiscount());
        });
    }

    /**
     * Bill the shipping, once the freight bill has actually arrived.
     *
     * A separate document rather than a line added to the goods invoice,
     * because at the time the goods are billed the freight cost is not yet
     * known — and a document already sent must not change afterwards.
     *
     * The amount defaults to the deal's share of the freight, which is what it
     * cost you. Charge more by passing your own figure.
     */
    public function issueShipping(Deal $deal, float|string|null $amount = null, ?string $dueDate = null): CustomerInvoice
    {
        $deal->loadMissing('consignments', 'customer');

        $charge = $amount !== null
            ? Money::of($amount, $deal->sell_currency)
            : $this->freightChargeInSellCurrency($deal);

        if ($charge->isZero()) {
            throw new RuntimeException('No freight has been recorded against this deal yet.');
        }

        return DB::transaction(function () use ($deal, $charge, $dueDate) {
            $invoice = $this->open($deal, 'shipping', $dueDate);

            CustomerInvoiceLine::create([
                'customer_invoice_id' => $invoice->id,
                'description' => 'Shipping & delivery',
                'description_ku' => 'گەیاندن',
                'specification' => $deal->consignments
                    ->pluck('tracking_number')
                    ->filter()
                    ->map(fn (string $n) => "Tracking {$n}")
                    ->implode(', ') ?: null,
                'quantity' => 1,
                'unit' => 'shipment',
                'unit_price' => $charge->amount,
                'line_total' => $charge->amount,
                'display_order' => 0,
            ]);

            // No discount here: the deal's discount is against the goods, and
            // taking it off the freight as well would give it away twice.
            return $this->close($invoice, $deal, $charge);
        });
    }

    /**
     * Withdraw an invoice.
     *
     * The document stays, marked cancelled and with a reason. Deleting it would
     * leave a gap in the numbering and no account of why — and the customer
     * still has their copy either way.
     */
    public function cancel(CustomerInvoice $invoice, ?string $reason = null): CustomerInvoice
    {
        if ($invoice->allocations()->exists()) {
            throw new RuntimeException('Money has been matched to this invoice. Unmatch the payment first.');
        }

        $invoice->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        return $invoice->refresh();
    }

    // --------------------------------------------------------------- helpers

    private function open(Deal $deal, string $type, ?string $dueDate): CustomerInvoice
    {
        return CustomerInvoice::create([
            'deal_id' => $deal->id,
            'customer_id' => $deal->customer_id,
            'number' => $this->numbers->next('customer_invoice'),
            'type' => $type,
            'status' => 'issued',
            'currency' => $deal->sell_currency,
            'exchange_rate' => $deal->rateFor($deal->sell_currency),
            'invoice_date' => today(),
            'due_date' => $dueDate,
            // Taken from the customer, so printing never asks a question.
            'language' => $deal->customer?->document_language ?? 'en',
            'issued_at' => now(),
            'created_by' => auth()->id(),
        ]);
    }

    private function close(
        CustomerInvoice $invoice,
        Deal $deal,
        Money $subtotal,
        ?Money $discount = null,
    ): CustomerInvoice {
        $discount ??= Money::zero($subtotal->currency);
        $total = $subtotal->minus($discount);

        $invoice->update([
            'subtotal' => $subtotal->amount,
            'discount' => $discount->amount,
            'total' => $total->amount,
            'total_base' => $deal->toBase($total)->amount,
        ]);

        /*
         * Credit the customer is already holding goes against this the moment
         * it exists — the remainder of an earlier payment, or an advance paid
         * before there was anything to pay for. Both are money of theirs you
         * have; leaving it aside while sending them a bill for the full amount
         * is a conversation nobody wants to have.
         */
        app(PaymentWriter::class)->applyCreditTo($invoice->refresh());

        return $invoice->refresh();
    }

    private function commissionInSellCurrency(Deal $deal, string $currency): Money
    {
        $commission = Money::of(
            $deal->deal_commission,
            $deal->deal_commission_currency ?: $currency,
        );

        if ($commission->currency === $currency) {
            return $commission;
        }

        // Through dollars, because the commission may be set in either currency
        // and there is no direct rate between them.
        $base = $deal->toBase($commission);

        return $currency === 'USD'
            ? $base
            : Money::of($base->times($deal->rateFor($currency))->amount, $currency);
    }

    /** What the freight cost this deal, expressed in the customer's currency. */
    private function freightChargeInSellCurrency(Deal $deal): Money
    {
        $base = Money::of(
            $deal->consignments->sum(fn ($c) => (float) $c->pivot->freight_share_base),
            'USD',
        );

        if ($deal->sell_currency === 'USD') {
            return $base;
        }

        return Money::of(
            $base->times($deal->rateFor($deal->sell_currency))->amount,
            $deal->sell_currency,
        );
    }
}
