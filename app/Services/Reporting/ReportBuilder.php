<?php

namespace App\Services\Reporting;

use App\Models\CrystalPrice;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Shipment;
use App\Models\StockLevel;
use App\Models\Supplier;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Builds the reports as plain rows.
 *
 * Every report returns headings plus rows, so the same definition renders on
 * screen and exports to CSV without a second implementation drifting out of
 * step with the first.
 */
class ReportBuilder
{
    /** @return array<string, array{label: string, description: string, dated: bool}> */
    public function available(): array
    {
        return [
            'sales' => ['label' => 'Sales & margin', 'description' => 'Every invoice with its true margin after landed cost.', 'dated' => true],
            'profit_by_product' => ['label' => 'Profit by product', 'description' => 'What each product actually earned, against landed cost.', 'dated' => true],
            'shipment_costs' => ['label' => 'Shipment costs', 'description' => 'Goods value, shipping costs and uplift per container.', 'dated' => false],
            'inventory' => ['label' => 'Inventory valuation', 'description' => 'Stock on hand at landed cost, by product.', 'dated' => false],
            'receivables' => ['label' => 'Receivables aging', 'description' => 'Who owes what, bucketed by how overdue it is.', 'dated' => false],
            'expenses' => ['label' => 'Expenses', 'description' => 'Operating spend by category.', 'dated' => true],
            'purchases' => ['label' => 'Purchases', 'description' => 'Purchase orders and how much has arrived.', 'dated' => true],
            'supplier_performance' => ['label' => 'Supplier performance', 'description' => 'Spend, lead time and catalogue size per supplier.', 'dated' => false],
            'slow_movers' => ['label' => 'Slow-moving stock', 'description' => 'Capital sitting still — stock with no sales in the period.', 'dated' => true],
            'crystal_prices' => ['label' => 'Crystal price list', 'description' => 'Supplier crystal prices by colour and size.', 'dated' => false],
        ];
    }

    /** @return array{headings: array<int, string>, rows: Collection, totals: array<string, mixed>} */
    public function build(string $report, CarbonInterface $from, CarbonInterface $to): array
    {
        return match ($report) {
            'sales' => $this->sales($from, $to),
            'profit_by_product' => $this->profitByProduct($from, $to),
            'shipment_costs' => $this->shipmentCosts(),
            'inventory' => $this->inventory(),
            'receivables' => $this->receivables(),
            'expenses' => $this->expenses($from, $to),
            'purchases' => $this->purchases($from, $to),
            'supplier_performance' => $this->supplierPerformance(),
            'slow_movers' => $this->slowMovers($from, $to),
            'crystal_prices' => $this->crystalPrices(),
            default => throw new InvalidArgumentException("Unknown report: {$report}"),
        };
    }

    private function sales(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = Invoice::query()
            ->with('customer')
            ->whereNot('status', 'cancelled')
            ->whereBetween('invoice_date', [$from, $to])
            ->orderBy('invoice_date')
            ->get()
            ->map(fn (Invoice $i) => [
                $i->number,
                $i->invoice_date?->format('Y-m-d'),
                $i->customer?->name,
                round((float) $i->total, 2),
                round((float) $i->cogs_total_base, 2),
                round((float) $i->gross_profit_base, 2),
                (float) $i->margin_percent,
                round($i->amountDue(), 2),
                $i->status,
            ]);

        return [
            'headings' => ['Invoice', 'Date', 'Customer', 'Total', 'COGS', 'Gross profit', 'Margin %', 'Outstanding', 'Status'],
            'rows' => $rows,
            'totals' => [
                'Revenue' => $rows->sum(3),
                'COGS' => $rows->sum(4),
                'Gross profit' => $rows->sum(5),
                'Outstanding' => $rows->sum(7),
            ],
        ];
    }

    private function profitByProduct(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->join('products', 'products.id', '=', 'invoice_items.product_id')
            ->whereNot('invoices.status', 'cancelled')
            ->whereBetween('invoices.invoice_date', [$from, $to])
            ->groupBy('products.id', 'products.sku', 'products.name')
            ->selectRaw('products.sku, products.name')
            ->selectRaw('sum(invoice_items.quantity) as qty')
            ->selectRaw('sum(invoice_items.line_total) as revenue')
            ->selectRaw('sum(invoice_items.quantity * invoice_items.unit_cost_base) as cogs')
            ->orderByRaw('sum(invoice_items.line_total - (invoice_items.quantity * invoice_items.unit_cost_base)) desc')
            ->get()
            ->map(function ($r) {
                $revenue = round((float) $r->revenue, 2);
                $cogs = round((float) $r->cogs, 2);
                $profit = round($revenue - $cogs, 2);

                return [
                    $r->sku, $r->name, round((float) $r->qty, 2), $revenue, $cogs, $profit,
                    $revenue > 0 ? round($profit / $revenue * 100, 1) : 0,
                ];
            });

        return [
            'headings' => ['SKU', 'Product', 'Qty sold', 'Revenue', 'COGS', 'Gross profit', 'Margin %'],
            'rows' => $rows,
            'totals' => ['Revenue' => $rows->sum(3), 'COGS' => $rows->sum(4), 'Gross profit' => $rows->sum(5)],
        ];
    }

    private function shipmentCosts(): array
    {
        $rows = Shipment::query()
            ->orderByDesc('eta')
            ->get()
            ->map(fn (Shipment $s) => [
                $s->number,
                $s->container_number,
                $s->status->getLabel(),
                $s->eta?->format('Y-m-d'),
                round((float) $s->total_goods_base, 2),
                round((float) $s->total_costs_base, 2),
                round((float) $s->total_goods_base + (float) $s->total_costs_base, 2),
                $s->costUpliftPercent(),
                $s->landed_cost_status->getLabel(),
            ]);

        return [
            'headings' => ['Shipment', 'Container', 'Status', 'ETA', 'Goods', 'Shipping costs', 'Landed total', 'Uplift %', 'Costing'],
            'rows' => $rows,
            'totals' => ['Goods' => $rows->sum(4), 'Shipping costs' => $rows->sum(5), 'Landed total' => $rows->sum(6)],
        ];
    }

    private function inventory(): array
    {
        $rows = StockLevel::query()
            ->with(['product.category', 'warehouse'])
            ->where('quantity', '>', 0)
            ->get()
            ->map(fn (StockLevel $l) => [
                $l->product->sku,
                $l->product->name,
                $l->product->category?->name,
                $l->warehouse?->name,
                round((float) $l->quantity, 2),
                round((float) $l->reserved_quantity, 2),
                round($l->available_quantity, 2),
                round((float) $l->average_cost, 4),
                round((float) $l->total_value, 2),
            ]);

        return [
            'headings' => ['SKU', 'Product', 'Category', 'Warehouse', 'On hand', 'Reserved', 'Available', 'Avg landed cost', 'Value'],
            'rows' => $rows,
            'totals' => ['Inventory value' => $rows->sum(8)],
        ];
    }

    /** Aging buckets are what turn "they owe us money" into "chase this one today". */
    private function receivables(): array
    {
        $rows = Customer::query()
            ->with(['invoices' => fn ($q) => $q->outstanding()])
            ->get()
            ->map(function (Customer $c) {
                $buckets = [0, 0, 0, 0];

                foreach ($c->invoices as $invoice) {
                    $days = $invoice->daysOverdue();
                    $due = $invoice->amountDue();

                    $index = match (true) {
                        $days <= 30 => 0,
                        $days <= 60 => 1,
                        $days <= 90 => 2,
                        default => 3,
                    };

                    $buckets[$index] += $due;
                }

                return [
                    $c->code, $c->name,
                    round($buckets[0], 2), round($buckets[1], 2), round($buckets[2], 2), round($buckets[3], 2),
                    round(array_sum($buckets), 2),
                    round((float) $c->credit_limit, 2),
                ];
            })
            ->filter(fn (array $r) => $r[6] > 0)
            ->values();

        return [
            'headings' => ['Code', 'Customer', '0–30 days', '31–60', '61–90', '90+', 'Total due', 'Credit limit'],
            'rows' => $rows,
            'totals' => [
                '0–30' => $rows->sum(2), '31–60' => $rows->sum(3),
                '61–90' => $rows->sum(4), '90+' => $rows->sum(5), 'Total' => $rows->sum(6),
            ],
        ];
    }

    private function expenses(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = Expense::query()
            ->with(['category', 'shipment'])
            ->whereNot('status', 'draft')
            ->whereBetween('expense_date', [$from, $to])
            ->orderBy('expense_date')
            ->get()
            ->map(fn (Expense $e) => [
                $e->number,
                $e->expense_date?->format('Y-m-d'),
                $e->category?->name,
                $e->description,
                $e->shipment?->number ?? '—',
                round((float) $e->base_amount, 2),
                $e->status,
            ]);

        return [
            'headings' => ['Number', 'Date', 'Category', 'Description', 'Container', 'Amount', 'Status'],
            'rows' => $rows,
            'totals' => ['Total' => $rows->sum(5)],
        ];
    }

    private function purchases(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = PurchaseOrder::query()
            ->with('supplier')
            ->whereBetween('order_date', [$from, $to])
            ->orderBy('order_date')
            ->get()
            ->map(fn (PurchaseOrder $po) => [
                $po->number,
                $po->order_date?->format('Y-m-d'),
                $po->supplier?->name,
                $po->supplier_reference,
                $po->incoterm,
                round((float) $po->total, 2),
                $po->receivedPercent(),
                $po->status->getLabel(),
            ]);

        return [
            'headings' => ['Order', 'Date', 'Supplier', 'Their ref', 'Incoterm', 'Total', 'Received %', 'Status'],
            'rows' => $rows,
            'totals' => ['Ordered' => $rows->sum(5)],
        ];
    }

    private function supplierPerformance(): array
    {
        $rows = Supplier::query()
            ->withCount(['supplierProducts', 'crystalProducts', 'purchaseOrders'])
            ->get()
            ->map(fn (Supplier $s) => [
                $s->code,
                $s->name,
                $s->country,
                $s->purchase_orders_count,
                round((float) $s->purchaseOrders()->sum('total'), 2),
                $s->supplier_products_count + $s->crystal_products_count,
                $s->average_lead_time_days ?? '—',
                round($s->outstandingBalance(), 2),
                $s->rating ?? '—',
            ]);

        return [
            'headings' => ['Code', 'Supplier', 'Country', 'Orders', 'Total spend', 'Catalogue size', 'Lead time (days)', 'Outstanding', 'Rating'],
            'rows' => $rows,
            'totals' => ['Total spend' => $rows->sum(4), 'Outstanding' => $rows->sum(7)],
        ];
    }

    /** Stock that has not moved is cash sitting on a shelf. */
    private function slowMovers(CarbonInterface $from, CarbonInterface $to): array
    {
        $sold = InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->whereBetween('invoices.invoice_date', [$from, $to])
            ->pluck('invoice_items.product_id')
            ->unique();

        $rows = Product::query()
            ->with('category')
            ->whereNotIn('id', $sold)
            ->get()
            ->map(fn (Product $p) => [
                $p->sku, $p->name, $p->category?->name,
                round($p->stockOnHand(), 2),
                round($p->effectiveCost(), 4),
                round($p->stockOnHand() * $p->effectiveCost(), 2),
            ])
            ->filter(fn (array $r) => $r[3] > 0)
            ->sortByDesc(5)
            ->values();

        return [
            'headings' => ['SKU', 'Product', 'Category', 'On hand', 'Landed cost', 'Capital tied up'],
            'rows' => $rows,
            'totals' => ['Capital tied up' => $rows->sum(5)],
        ];
    }

    private function crystalPrices(): array
    {
        $rows = CrystalPrice::query()
            ->with(['crystalProduct', 'size', 'supplier'])
            ->get()
            ->map(fn (CrystalPrice $p) => [
                $p->supplier?->name,
                $p->crystalProduct?->crystal_code,
                $p->crystalProduct?->crystal_name,
                $p->crystalProduct?->finish,
                $p->size?->label,
                round((float) $p->price, 2),
                $p->currency,
            ])
            ->sortBy([[1, 'asc'], [4, 'asc']])
            ->values();

        return [
            'headings' => ['Supplier', 'Code', 'Colour', 'Finish', 'Size', 'Price', 'Currency'],
            'rows' => $rows,
            'totals' => [],
        ];
    }
}
