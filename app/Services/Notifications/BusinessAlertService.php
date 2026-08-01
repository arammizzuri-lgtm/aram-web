<?php

namespace App\Services\Notifications;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\SupplierBill;
use Illuminate\Support\Collection;

/**
 * Detects the things worth interrupting someone about.
 *
 * Deliberately narrow: an alert that fires every day stops being read. Each rule
 * here describes a condition that costs money if left alone — stock that will run
 * out before a 45-day reorder can land, margins still running on estimates, cash
 * that should already have arrived.
 *
 * @see docs/06-USER-FLOWS.md F15
 */
class BusinessAlertService
{
    /** A container costed on estimates for longer than this is distorting margins. */
    private const int PROVISIONAL_COSTING_DAYS = 30;

    private const int INVOICE_DUE_SOON_DAYS = 7;

    /** @return Collection<int, BusinessAlert> */
    public function all(): Collection
    {
        return collect([
            ...$this->provisionalCosting(),
            ...$this->arrivedShipments(),
            ...$this->overdueInvoices(),
            ...$this->invoicesDueSoon(),
            ...$this->lowStock(),
            ...$this->creditLimits(),
            ...$this->supplierPaymentsDue(),
        ]);
    }

    /** @return array<int, BusinessAlert> */
    private function provisionalCosting(): array
    {
        $stale = Shipment::query()
            ->awaitingFinalCosting()
            ->whereNotNull('ata')
            ->whereDate('ata', '<=', now()->subDays(self::PROVISIONAL_COSTING_DAYS))
            ->get();

        if ($stale->isEmpty()) {
            return [];
        }

        $oldest = (int) $stale->first()->ata->diffInDays(now());

        return [new BusinessAlert(
            key: 'landed_cost_pending',
            title: $stale->count().' '.str('shipment')->plural($stale->count()).' still costed on estimates',
            body: "The oldest arrived {$oldest} days ago. Every sale from it is reporting a margin that has not been reconciled.",
            severity: 'danger',
            url: '/admin/shipments',
            actionLabel: 'Finalise costing',
        )];
    }

    /** @return array<int, BusinessAlert> */
    private function arrivedShipments(): array
    {
        $arrived = Shipment::query()
            ->whereIn('status', ['cleared'])
            ->whereHas('items', fn ($q) => $q->whereColumn('received_quantity', '<', 'quantity'))
            ->get();

        return $arrived->isEmpty() ? [] : [new BusinessAlert(
            key: 'shipment_arrived',
            title: $arrived->count().' cleared '.str('shipment')->plural($arrived->count()).' waiting to be received',
            body: 'Stock is not sellable and not valued until it is booked in.',
            severity: 'warning',
            url: '/admin/shipments',
            actionLabel: 'Receive goods',
        )];
    }

    /** @return array<int, BusinessAlert> */
    private function overdueInvoices(): array
    {
        $overdue = Invoice::query()->overdue()->get();

        if ($overdue->isEmpty()) {
            return [];
        }

        $total = $overdue->sum(fn (Invoice $i) => $i->amountDue());
        $worst = $overdue->max(fn (Invoice $i) => $i->daysOverdue());

        return [new BusinessAlert(
            key: 'invoice_overdue',
            title: $overdue->count().' overdue '.str('invoice')->plural($overdue->count()),
            body: '$'.number_format($total, 2)." outstanding, the worst {$worst} days past due.",
            severity: 'danger',
            url: '/admin/invoices',
            actionLabel: 'Chase payment',
        )];
    }

    /** @return array<int, BusinessAlert> */
    private function invoicesDueSoon(): array
    {
        $due = Invoice::query()
            ->outstanding()
            ->whereDate('due_date', '>=', today())
            ->whereDate('due_date', '<=', today()->addDays(self::INVOICE_DUE_SOON_DAYS))
            ->get();

        return $due->isEmpty() ? [] : [new BusinessAlert(
            key: 'invoice_due_soon',
            title: $due->count().' '.str('invoice')->plural($due->count()).' due within a week',
            body: '$'.number_format($due->sum(fn (Invoice $i) => $i->amountDue()), 2).' expected in.',
            severity: 'info',
            url: '/admin/invoices',
            actionLabel: 'Review',
        )];
    }

    /** @return array<int, BusinessAlert> */
    private function lowStock(): array
    {
        $low = Product::query()->lowStock()->count();

        return $low === 0 ? [] : [new BusinessAlert(
            key: 'low_stock',
            title: $low.' '.str('product')->plural($low).' at or below reorder level',
            body: 'Lead times run 35–55 days, so a reorder placed today lands next season.',
            severity: 'warning',
            url: '/admin/products',
            actionLabel: 'Review stock',
        )];
    }

    /** @return array<int, BusinessAlert> */
    private function creditLimits(): array
    {
        $breached = Customer::query()
            ->where('credit_limit', '>', 0)
            ->get()
            ->filter(fn (Customer $c) => $c->creditUsedPercent() >= 100);

        return $breached->isEmpty() ? [] : [new BusinessAlert(
            key: 'credit_limit_exceeded',
            title: $breached->count().' '.str('customer')->plural($breached->count()).' over their credit limit',
            body: $breached->take(3)->pluck('name')->implode(', ').'. New orders need approval.',
            severity: 'danger',
            url: '/admin/customers',
            actionLabel: 'Review credit',
        )];
    }

    /** @return array<int, BusinessAlert> */
    private function supplierPaymentsDue(): array
    {
        $due = SupplierBill::query()
            ->whereNot('status', 'cancelled')
            ->whereColumn('amount_paid', '<', 'total')
            ->whereDate('due_date', '<=', today()->addDays(self::INVOICE_DUE_SOON_DAYS))
            ->get();

        return $due->isEmpty() ? [] : [new BusinessAlert(
            key: 'supplier_payment_due',
            title: $due->count().' supplier '.str('payment')->plural($due->count()).' due',
            body: 'Deposits hold production slots — a late one moves the whole container.',
            severity: 'warning',
            url: '/admin/purchase-orders',
            actionLabel: 'Review payables',
        )];
    }
}
