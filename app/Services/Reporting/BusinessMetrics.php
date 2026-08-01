<?php

namespace App\Services\Reporting;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Shipment;
use App\Models\StockLevel;
use App\Models\SupplierBill;
use Carbon\CarbonInterface;

/**
 * The figures the dashboard is built from.
 *
 * Deliberately organised around the cash-conversion cycle rather than revenue:
 * an importer with healthy sales can still run out of money, because cash leaves
 * for a container roughly a hundred days before it comes back from a customer.
 */
class BusinessMetrics
{
    public function revenue(CarbonInterface $from, CarbonInterface $to): float
    {
        return (float) Invoice::query()
            ->where('invoice_type', 'standard')
            ->whereNot('status', 'cancelled')
            ->whereBetween('invoice_date', [$from, $to])
            ->sum('total');
    }

    public function costOfGoodsSold(CarbonInterface $from, CarbonInterface $to): float
    {
        return (float) Invoice::query()
            ->where('invoice_type', 'standard')
            ->whereNot('status', 'cancelled')
            ->whereBetween('invoice_date', [$from, $to])
            ->sum('cogs_total_base');
    }

    public function grossProfit(CarbonInterface $from, CarbonInterface $to): float
    {
        return round($this->revenue($from, $to) - $this->costOfGoodsSold($from, $to), 2);
    }

    public function grossMarginPercent(CarbonInterface $from, CarbonInterface $to): float
    {
        $revenue = $this->revenue($from, $to);

        return $revenue > 0 ? round($this->grossProfit($from, $to) / $revenue * 100, 1) : 0.0;
    }

    /** Operating expenses only — logistics costs already sit inside COGS via landed cost. */
    public function operatingExpenses(CarbonInterface $from, CarbonInterface $to): float
    {
        return (float) Expense::query()
            ->whereNot('status', 'draft')
            ->where('is_allocated_to_shipment', false)
            ->whereBetween('expense_date', [$from, $to])
            ->sum('base_amount');
    }

    public function netProfit(CarbonInterface $from, CarbonInterface $to): float
    {
        return round($this->grossProfit($from, $to) - $this->operatingExpenses($from, $to), 2);
    }

    /** Stock physically in the warehouse, at landed cost. */
    public function inventoryValue(): float
    {
        return round((float) StockLevel::query()->sum('total_value'), 2);
    }

    /**
     * Paid-for stock still on the water.
     *
     * An asset, not an expense — without showing it separately the balance looks
     * like it vanishes for two months every time a container is ordered.
     */
    public function goodsInTransit(): float
    {
        return round((float) Shipment::query()
            ->whereIn('status', ['booked', 'in_transit', 'arrived', 'customs'])
            ->sum('total_goods_base'), 2);
    }

    public function receivables(): float
    {
        return round((float) Invoice::query()->outstanding()->sum('total')
            - (float) Invoice::query()->outstanding()->sum('amount_paid'), 2);
    }

    public function overdueReceivables(): float
    {
        return round((float) Invoice::query()->overdue()->sum('total')
            - (float) Invoice::query()->overdue()->sum('amount_paid'), 2);
    }

    public function payables(): float
    {
        return round((float) SupplierBill::query()->whereNot('status', 'cancelled')->sum('total')
            - (float) SupplierBill::query()->whereNot('status', 'cancelled')->sum('amount_paid'), 2);
    }

    public function overdueInvoiceCount(): int
    {
        return Invoice::query()->overdue()->count();
    }

    /** Containers carrying a provisional cost — every day here is a day of wrong margins. */
    public function shipmentsAwaitingFinalCosting(): int
    {
        return Shipment::query()->awaitingFinalCosting()->count();
    }

    public function oldestProvisionalCostingDays(): ?int
    {
        $oldest = Shipment::query()->awaitingFinalCosting()->whereNotNull('ata')->first();

        return $oldest?->ata ? (int) $oldest->ata->diffInDays(now()) : null;
    }

    public function containersInTransit(): int
    {
        return Shipment::query()->inTransit()->count();
    }

    /** Percentage change between two periods, for the trend line on a tile. */
    public function change(float $current, float $previous): ?float
    {
        if (abs($previous) < 0.005) {
            return null;
        }

        return round(($current - $previous) / abs($previous) * 100, 1);
    }
}
