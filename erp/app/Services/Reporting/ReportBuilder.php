<?php

namespace App\Services\Reporting;

use App\Models\Consignment;
use App\Models\CustomerInvoice;
use App\Models\DealPurchase;
use App\Models\SupplierPayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The reports, each as a plain heading list plus rows.
 *
 * Kept as arrays rather than view models so the same definition drives both the
 * on-screen table and the CSV export — the alternative is two descriptions of
 * one report that slowly stop agreeing.
 */
class ReportBuilder
{
    public function __construct(private readonly BusinessMetrics $metrics) {}

    /**
     * Every report, with what it needs to know about itself.
     *
     * `cost` marks the ones that expose purchase prices or margin; those are
     * withheld entirely from anyone without `view_cost` rather than shown with
     * columns blanked.
     *
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            'profit_by_deal' => [
                'label' => 'Profit by deal',
                'description' => 'What each order earned, after goods, freight and everything else.',
                'columns' => ['Deal', 'Customer', 'Date', 'Revenue', 'Cost', 'Profit', 'Margin'],
                'numeric' => [3, 4, 5, 6],
                'cost' => true,
            ],
            'profit_by_customer' => [
                'label' => 'Profit by customer',
                'description' => 'Who actually earns you money, which is not always who spends the most.',
                'columns' => ['Customer', 'Deals', 'Revenue', 'Cost', 'Profit', 'Margin'],
                'numeric' => [1, 2, 3, 4, 5],
                'cost' => true,
            ],
            'profit_by_product' => [
                'label' => 'Profit by product',
                'description' => 'Approximate where a deal carried a lump commission — see the note.',
                'columns' => ['Product', 'Quantity', 'Revenue', 'Cost', 'Profit', 'Exact?'],
                'numeric' => [1, 2, 3, 4],
                'cost' => true,
            ],
            'receivables' => [
                'label' => 'Who owes you',
                'description' => 'Invoices issued and not yet settled, oldest first.',
                'columns' => ['Invoice', 'Customer', 'Date', 'Total', 'Paid', 'Still due', 'Days'],
                'numeric' => [3, 4, 5, 6],
                'cost' => false,
            ],
            'payables' => [
                'label' => 'What you owe suppliers',
                'description' => 'Live purchases with money still outstanding.',
                'columns' => ['Purchase', 'Supplier', 'Deal', 'Total', 'Paid', 'Still owed'],
                'numeric' => [3, 4, 5],
                'cost' => true,
            ],
            'transfer_losses' => [
                'label' => 'Cost of sending money',
                'description' => 'What the exchange took above the quoted rate. Invisible anywhere else.',
                'columns' => ['Payment', 'Supplier', 'Date', 'Sent', 'At quoted rate', 'Really cost', 'Difference'],
                'numeric' => [3, 4, 5, 6],
                'cost' => true,
            ],
            'shipping' => [
                'label' => 'Shipping',
                'description' => 'Freight by consignment, and which deals carried it.',
                'columns' => ['Tracking', 'Mode', 'Shipped', 'Weight', 'CBM', 'Freight', 'Deals'],
                'numeric' => [3, 4, 5],
                'cost' => true,
            ],
        ];
    }

    /** @return Collection<int, array<int, mixed>> */
    public function rows(string $report, Carbon $from, Carbon $to): Collection
    {
        return match ($report) {
            'profit_by_deal' => $this->profitByDeal($from, $to),
            'profit_by_customer' => $this->profitByCustomer($from, $to),
            'profit_by_product' => $this->profitByProduct($from, $to),
            'receivables' => $this->receivables(),
            'payables' => $this->payables(),
            'transfer_losses' => $this->transferLosses($from, $to),
            'shipping' => $this->shipping($from, $to),
            default => collect(),
        };
    }

    // --------------------------------------------------------------- reports

    private function profitByDeal(Carbon $from, Carbon $to): Collection
    {
        return $this->metrics->profitByDeal($from, $to)->map(fn (array $r) => [
            $r['deal'],
            $r['customer'] ?? '—',
            $r['date'],
            $r['revenue'],
            $r['cost'],
            $r['profit'],
            $r['margin'].'%',
        ]);
    }

    private function profitByCustomer(Carbon $from, Carbon $to): Collection
    {
        return $this->metrics->profitByCustomer($from, $to)->map(fn (array $r) => [
            $r['customer'] ?? '—',
            $r['deals'],
            $r['revenue'],
            $r['cost'],
            $r['profit'],
            $r['margin'].'%',
        ]);
    }

    private function profitByProduct(Carbon $from, Carbon $to): Collection
    {
        return $this->metrics->profitByProduct($from, $to)->map(fn (array $r) => [
            $r['product'],
            $r['quantity'],
            $r['revenue'],
            $r['cost'],
            $r['profit'],
            // Said plainly rather than shown as a precise-looking number: a
            // deal-level commission cannot be split across products honestly.
            $r['approximate'] ? 'Approximate' : 'Exact',
        ]);
    }

    private function receivables(): Collection
    {
        return CustomerInvoice::query()
            ->outstanding()
            ->with(['customer', 'allocations'])
            ->orderBy('invoice_date')
            ->get()
            ->filter(fn (CustomerInvoice $i) => $i->outstandingBase()->isPositive())
            ->map(fn (CustomerInvoice $i) => [
                $i->number,
                $i->customer?->name ?? '—',
                $i->invoice_date?->toDateString(),
                round((float) $i->total_base, 2),
                $i->paidBase()->toFloat(),
                $i->outstandingBase()->toFloat(),
                $i->invoice_date ? (int) $i->invoice_date->diffInDays(now()) : 0,
            ])
            ->values();
    }

    private function payables(): Collection
    {
        return DealPurchase::query()
            ->whereNot('status', 'cancelled')
            ->with(['supplier', 'deal', 'lines', 'costs', 'payments'])
            ->get()
            ->filter(fn (DealPurchase $p) => $p->outstandingBase()->isPositive())
            ->sortByDesc(fn (DealPurchase $p) => $p->outstandingBase()->toFloat())
            ->map(fn (DealPurchase $p) => [
                $p->number,
                $p->supplier?->name ?? '—',
                $p->deal?->number ?? '—',
                $p->totalBase()->toFloat(),
                $p->paidBase()->toFloat(),
                $p->outstandingBase()->toFloat(),
            ])
            ->values();
    }

    private function transferLosses(Carbon $from, Carbon $to): Collection
    {
        return SupplierPayment::query()
            ->whereNotNull('actual_cost_base')
            ->whereBetween('paid_at', [$from, $to])
            ->with('supplier')
            ->orderByDesc('paid_at')
            ->get()
            ->map(fn (SupplierPayment $p) => [
                $p->number,
                $p->supplier?->name ?? '—',
                $p->paid_at?->toDateString(),
                number_format((float) $p->amount, 2).' '.$p->currency,
                round((float) $p->base_amount, 2),
                round((float) $p->actual_cost_base, 2),
                $p->transferLossBase()->toFloat(),
            ]);
    }

    private function shipping(Carbon $from, Carbon $to): Collection
    {
        return Consignment::query()
            ->whereBetween('shipped_at', [$from, $to])
            ->with('deals')
            ->orderByDesc('shipped_at')
            ->get()
            ->map(fn (Consignment $c) => [
                $c->tracking_number,
                Consignment::MODES[$c->mode] ?? $c->mode,
                $c->shipped_at?->toDateString(),
                $c->gross_weight_kg ? round((float) $c->gross_weight_kg, 2) : 0,
                $c->cbm ? round((float) $c->cbm, 3) : 0,
                round((float) ($c->freight_base ?? 0), 2),
                $c->deals->pluck('number')->implode(', ') ?: '—',
            ]);
    }
}
