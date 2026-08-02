<?php

namespace App\Services\Deals;

use App\Models\DealPurchase;
use App\Models\SupplierPayment;
use App\Services\Documents\DocumentNumberGenerator;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Paying China, in instalments or in one go.
 *
 * The reason this is a service rather than a form save is the second amount.
 * A payment records both what the supplier received and what leaving your hands
 * actually cost — and those are rarely the same number.
 */
class SupplierPaymentWriter
{
    public function __construct(private readonly DocumentNumberGenerator $numbers) {}

    /**
     * Record a payment against a purchase.
     *
     * `actualCostBase` is what you really handed over, in dollars, including the
     * exchange house's cut. Left null it is simply unknown and the converted
     * amount stands — but recorded, it is the difference between reports that
     * match your pocket and reports that quietly flatter every deal.
     */
    public function record(
        DealPurchase $purchase,
        float|string $amount,
        ?string $currency = null,
        ?float $actualCostBase = null,
        ?string $paidAt = null,
        string $method = 'exchange',
        ?string $reference = null,
        ?string $notes = null,
    ): SupplierPayment {
        $purchase->loadMissing('deal');

        $currency ??= $purchase->currency;
        $paid = Money::of($amount, $currency);
        $rate = $purchase->deal->rateFor($currency);

        return DB::transaction(function () use (
            $purchase, $paid, $currency, $rate, $actualCostBase, $paidAt, $method, $reference, $notes
        ) {
            $payment = SupplierPayment::create([
                'supplier_id' => $purchase->supplier_id,
                'deal_purchase_id' => $purchase->id,
                'number' => $this->numbers->next('supplier_payment'),
                'amount' => $paid->amount,
                'currency' => $currency,
                'exchange_rate' => $rate,
                'base_amount' => $purchase->deal->toBase($paid)->amount,
                'actual_cost_base' => $actualCostBase,
                'method' => $method,
                'reference' => $reference,
                'paid_at' => $paidAt ?? today(),
                'notes' => $notes,
                'recorded_by' => auth()->id(),
            ]);

            $this->refreshStatus($purchase->fresh());

            return $payment;
        });
    }

    /**
     * Move the purchase's status to match what has actually been paid.
     *
     * Derived rather than chosen, because a status someone has to remember to
     * change is a status that is wrong most of the time. Cancelled and received
     * are left alone — those are statements about goods, not money, and paying
     * a bill does not un-receive a shipment.
     */
    public function refreshStatus(DealPurchase $purchase): DealPurchase
    {
        $purchase->loadMissing('lines', 'costs', 'payments');

        if (in_array($purchase->status, ['cancelled', 'received'], true)) {
            return $purchase;
        }

        $paid = $purchase->paidBase();

        $status = match (true) {
            $paid->isZero() => $purchase->status === 'draft' ? 'draft' : 'ordered',
            $purchase->isFullyPaid() => 'paid',
            default => 'paid_partial',
        };

        $purchase->update(['status' => $status]);

        return $purchase->refresh();
    }

    /**
     * A deposit of the usual size, as a starting figure.
     *
     * China suppliers commonly want 30% up front and the balance before
     * shipping. The supplier's own recorded deposit percent wins where it is
     * known, because "usual" varies by who you are buying from.
     */
    public function suggestedDeposit(DealPurchase $purchase): Money
    {
        $percent = (float) ($purchase->supplier->deposit_percent ?? 0) ?: 30.0;

        return $purchase->goodsTotal()->times($percent / 100)->roundTo(Money::SCALE);
    }
}
