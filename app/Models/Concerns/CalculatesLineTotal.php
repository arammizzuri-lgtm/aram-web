<?php

namespace App\Models\Concerns;

/** Keeps a line item's stored total in step with its quantity, price and discount. */
trait CalculatesLineTotal
{
    protected static function bootCalculatesLineTotal(): void
    {
        static::saving(function ($item) {
            $gross = (float) $item->quantity * (float) $item->unit_price;

            $item->line_total = round($gross - ($gross * ((float) $item->discount_rate / 100)), 2);
        });
    }
}
