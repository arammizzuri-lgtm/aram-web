<?php

namespace App\Models\Concerns;

/**
 * Totals for documents built from line items.
 *
 * Tax is calculated per line on the discounted amount, then summarised on the
 * document, so mixed tax rates within one document stay correct.
 */
trait HasLineItems
{
    public function recalculateTotals(bool $save = true): static
    {
        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;

        foreach ($this->items()->get() as $item) {
            $gross = (float) $item->quantity * (float) $item->unit_price;
            $discount = $gross * ((float) $item->discount_rate / 100);
            $net = $gross - $discount;

            $subtotal += $gross;
            $discountTotal += $discount;
            $taxTotal += $net * ((float) $item->tax_rate / 100);
        }

        $this->subtotal = round($subtotal, 2);
        $this->discount_total = round($discountTotal, 2);
        $this->tax_total = round($taxTotal, 2);
        $this->total = round($subtotal - $discountTotal + $taxTotal, 2);

        if ($save) {
            $this->saveQuietly();
        }

        return $this;
    }
}
