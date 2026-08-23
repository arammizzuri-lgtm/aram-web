<?php

namespace App\Services\Deals;

use App\Models\Deal;
use App\Models\DealLine;
use App\Models\DealPurchase;
use App\Services\Documents\DocumentNumberGenerator;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Everything that has to happen when a deal is saved.
 *
 * This exists so the promise "never enter the same thing twice" is kept in one
 * place rather than in whichever screen happened to save the record. Naming a
 * supplier on a line is all the operator does; the purchase document, the
 * grouping, and the frozen USD values are all consequences of that.
 */
class DealWriter
{
    public function __construct(
        private readonly DocumentNumberGenerator $numbers,
        private readonly DealProgress $progress,
    ) {}

    /**
     * Bring a deal's derived data back in step with its lines.
     *
     * Runs in a transaction because a half-applied sync leaves lines pointing at
     * a purchase that was never created, which reads as goods bought from
     * nobody.
     */
    public function sync(Deal $deal): Deal
    {
        return DB::transaction(function () use ($deal) {
            $deal->loadMissing(['lines', 'purchases']);

            $this->freezeBaseAmounts($deal);
            $this->syncPurchases($deal);
            $this->freezeDiscounts($deal);

            return $deal->refresh();
        });
    }

    /**
     * Stamp every line's USD value using the deal's frozen rates.
     *
     * Done on save rather than on read so that a later rate change cannot move
     * historical profit. The stored figure is the answer as it stood the day
     * the deal was agreed, which is the only answer that stays true.
     *
     * The line total is converted once, rather than converting the unit price
     * and multiplying. Multiplying a rounded per-unit figure by 500 pieces
     * amplifies its own rounding error into a visible discrepancy.
     */
    private function freezeBaseAmounts(Deal $deal): void
    {
        foreach ($deal->lines as $line) {
            $quantity = (float) $line->quantity;

            $costTotal = Money::of($line->unit_cost, $line->cost_currency)->times($quantity);
            $sellTotal = Money::of($line->unit_price, $deal->sell_currency)->times($quantity);

            $line->forceFill([
                'cost_total_base' => $deal->toBase($costTotal)->amount,
                'sell_total_base' => $deal->toBase($sellTotal)->amount,
            ])->saveQuietly();
        }
    }

    /**
     * Stamp what the discounts came to, in the currencies that will be read.
     *
     * Last of the three, because both of the others feed it: a discount is a
     * share of totals that have to be right first, and the per-supplier ones
     * cannot be valued until `syncPurchases()` has decided which lines belong
     * to which supplier.
     *
     * Frozen for the same reason as everything else here. A percentage left to
     * recompute would be re-answered against whatever rate the deal is opened
     * under, so a 5% concession agreed in March would quietly become a
     * different number of dollars in June — on a deal that has not been
     * touched, and in the figure the profit report is built from.
     */
    /**
     * Re-answer the discount figures alone.
     *
     * A supplier's own concession is typed on their purchase document, which is
     * saved without going anywhere near the deal form — so without this, the
     * deal would go on reporting yesterday's profit until somebody happened to
     * open it and press save. Only the discount half is redone: nothing about
     * the lines or the purchase grouping has changed.
     */
    public function syncDiscounts(Deal $deal): void
    {
        DB::transaction(fn () => $this->freezeDiscounts($deal));
    }

    private function freezeDiscounts(Deal $deal): void
    {
        $deal->load(['lines', 'purchases.lines']);

        foreach ($deal->purchases as $purchase) {
            $purchase->forceFill([
                'discount_base' => $deal->toBase($purchase->discountTotal())->amount,
            ])->saveQuietly();
        }

        $discounts = $deal->computeDiscounts();

        $deal->forceFill([
            // The deal-wide part only. Each purchase carries its own, and
            // adding them here as well would take the same money off twice.
            'supplier_discount_base' => $discounts->dealWideSupplier->amount,
            'customer_discount' => $discounts->customer()->amount,
            'customer_discount_base' => $deal->toBase($discounts->customer())->amount,
        ])->saveQuietly();
    }

    /**
     * One purchase per supplier named on a line, created on demand.
     *
     * The operator never creates a purchase — they say where a line is bought
     * from and the document appears. Purchases whose last line has moved to
     * another supplier are removed, but only while still a draft: once ordered
     * or paid, a purchase is a record of something that happened and deleting
     * it would erase money that actually moved.
     */
    private function syncPurchases(Deal $deal): void
    {
        $supplierIds = $deal->lines
            ->pluck('supplier_id')
            ->filter()
            ->unique()
            ->values();

        foreach ($supplierIds as $supplierId) {
            $purchase = $deal->purchases->firstWhere('supplier_id', $supplierId)
                ?? DealPurchase::create([
                    'deal_id' => $deal->id,
                    'supplier_id' => $supplierId,
                    'number' => $this->numbers->next('deal_purchase'),
                    'currency' => 'CNY',
                    'status' => 'draft',
                    // Bought before the customer committed: the dashboard totals
                    // these so the money you are carrying at your own risk is a
                    // number you can see rather than a feeling.
                    'bought_at_risk' => ! $deal->isApproved(),
                ]);

            DealLine::query()
                ->where('deal_id', $deal->id)
                ->where('supplier_id', $supplierId)
                ->update(['deal_purchase_id' => $purchase->id]);
        }

        $deal->purchases()
            ->where('status', 'draft')
            ->whereNotIn('supplier_id', $supplierIds->all())
            ->whereDoesntHave('payments')
            ->delete();

        /*
         * Naming a supplier is the deal starting to be bought.
         *
         * Said here rather than left for the operator to remember, because it
         * is the one thing about the deal's stage the system can be certain of:
         * a purchase document exists. Forward-only, so re-saving a deal that
         * has already shipped does not drag it back.
         */
        if ($supplierIds->isNotEmpty()) {
            $this->progress->advanceTo($deal, 'purchasing');
        }
    }

    /** Allocate the deal's number at the moment it is first saved. */
    public function assignNumber(Deal $deal): string
    {
        return $deal->number ?: $this->numbers->next('deal');
    }
}
