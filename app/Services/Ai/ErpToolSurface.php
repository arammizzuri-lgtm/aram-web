<?php

namespace App\Services\Ai;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Reporting\BusinessMetrics;
use Illuminate\Support\Carbon;

/**
 * The fixed set of questions the assistant is allowed to ask the database.
 *
 * Deliberately a closed surface of typed, parameterised queries — the model
 * never writes SQL and can never reach a table that is not exposed here. Every
 * tool is read-only, and each one re-checks the caller's permissions rather
 * than trusting the prompt: a Sales login asking "what are our margins?" gets
 * an answer with the cost figures removed, not a refusal the model could be
 * talked out of.
 */
class ErpToolSurface
{
    public function __construct(private readonly BusinessMetrics $metrics) {}

    /** @return array<int, array<string, mixed>> the tool schemas sent to the model */
    public function definitions(User $user): array
    {
        $tools = [
            [
                'name' => 'get_business_summary',
                'description' => 'Revenue, cost of goods, profit, expenses, inventory value, receivables and payables for a date range. Use for questions about overall performance, profit, or cash position.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'from' => ['type' => 'string', 'description' => 'Start date, YYYY-MM-DD.'],
                        'to' => ['type' => 'string', 'description' => 'End date, YYYY-MM-DD.'],
                    ],
                    'required' => ['from', 'to'],
                ],
            ],
            [
                'name' => 'find_products',
                'description' => 'Search the product catalogue by name, SKU or category. Returns stock, prices and margin. Use for questions about specific products or what is in stock.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'search' => ['type' => 'string', 'description' => 'Name, SKU or partial match.'],
                        'category' => ['type' => 'string', 'description' => 'Category name, e.g. Crystals.'],
                        'supplier' => ['type' => 'string', 'description' => 'Supplier name.'],
                        'low_stock_only' => ['type' => 'boolean', 'description' => 'Only products at or below reorder level.'],
                        'limit' => ['type' => 'integer', 'description' => 'Max rows, default 25.'],
                    ],
                ],
            ],
            [
                'name' => 'get_customer_balances',
                'description' => 'What customers owe, with aging buckets and credit limits. Use for questions about who owes money or who is overdue.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'overdue_only' => ['type' => 'boolean', 'description' => 'Only customers with overdue invoices.'],
                        'limit' => ['type' => 'integer'],
                    ],
                ],
            ],
            [
                'name' => 'get_shipments',
                'description' => 'Containers with their goods value, shipping costs, cost uplift and costing status. Use for questions about imports, containers, freight or landed cost.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string', 'description' => 'e.g. in_transit, cleared, delivered.'],
                        'limit' => ['type' => 'integer'],
                    ],
                ],
            ],
            [
                'name' => 'get_product_profitability',
                'description' => 'Products ranked by gross profit over a period, measured against true landed cost. Use for best/worst sellers and margin questions.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'from' => ['type' => 'string'],
                        'to' => ['type' => 'string'],
                        'worst_first' => ['type' => 'boolean', 'description' => 'Show loss-makers first.'],
                        'limit' => ['type' => 'integer'],
                    ],
                    'required' => ['from', 'to'],
                ],
            ],
            [
                'name' => 'get_suppliers',
                'description' => 'Suppliers with spend, lead times, catalogue size and outstanding balance. Use for questions about who you buy from.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['limit' => ['type' => 'integer']],
                ],
            ],
        ];

        // Cost-bearing tools simply are not offered to a user who cannot see cost.
        return $user->can('view_cost')
            ? $tools
            : array_values(array_filter(
                $tools,
                fn (array $t) => ! in_array($t['name'], ['get_business_summary', 'get_product_profitability', 'get_suppliers'], true),
            ));
    }

    /** @return array<string, mixed> */
    public function run(string $name, array $input, User $user): array
    {
        $allowed = array_column($this->definitions($user), 'name');

        if (! in_array($name, $allowed, true)) {
            return ['error' => "The tool '{$name}' is not available to this user."];
        }

        return match ($name) {
            'get_business_summary' => $this->businessSummary($input),
            'find_products' => $this->products($input, $user),
            'get_customer_balances' => $this->customerBalances($input),
            'get_shipments' => $this->shipments($input, $user),
            'get_product_profitability' => $this->profitability($input),
            'get_suppliers' => $this->suppliers($input),
            default => ['error' => 'Unknown tool.'],
        };
    }

    private function businessSummary(array $input): array
    {
        $from = Carbon::parse($input['from'] ?? now()->subDays(30))->startOfDay();
        $to = Carbon::parse($input['to'] ?? now())->endOfDay();

        return [
            'period' => $from->toDateString().' to '.$to->toDateString(),
            'currency' => 'USD',
            'revenue' => $this->metrics->revenue($from, $to),
            'cost_of_goods_sold' => $this->metrics->costOfGoodsSold($from, $to),
            'gross_profit' => $this->metrics->grossProfit($from, $to),
            'gross_margin_percent' => $this->metrics->grossMarginPercent($from, $to),
            'operating_expenses' => $this->metrics->operatingExpenses($from, $to),
            'net_profit' => $this->metrics->netProfit($from, $to),
            'inventory_value' => $this->metrics->inventoryValue(),
            'goods_in_transit' => $this->metrics->goodsInTransit(),
            'receivables' => $this->metrics->receivables(),
            'overdue_receivables' => $this->metrics->overdueReceivables(),
            'payables' => $this->metrics->payables(),
            'shipments_awaiting_final_costing' => $this->metrics->shipmentsAwaitingFinalCosting(),
            'note' => 'Operating expenses exclude freight and duty, which sit inside landed cost.',
        ];
    }

    private function products(array $input, User $user): array
    {
        $showCost = $user->can('view_cost');

        $rows = Product::query()
            ->with(['category', 'defaultSupplier'])
            // whereLike stays case-insensitive on PostgreSQL; a plain LIKE does not,
            // and the model would silently be told "no such product".
            ->when($input['search'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w
                ->whereLike('name', "%{$s}%")
                ->orWhereLike('sku', "%{$s}%")
                ->orWhereLike('name_zh', "%{$s}%")))
            ->when($input['category'] ?? null, fn ($q, $c) => $q->whereHas('category', fn ($w) => $w->whereLike('name', "%{$c}%")))
            ->when($input['supplier'] ?? null, fn ($q, $s) => $q->whereHas('defaultSupplier', fn ($w) => $w->whereLike('name', "%{$s}%")))
            ->when($input['low_stock_only'] ?? false, fn ($q) => $q->lowStock())
            ->limit(min((int) ($input['limit'] ?? 25), 100))
            ->get()
            ->map(fn (Product $p) => array_filter([
                'sku' => $p->sku,
                'name' => $p->name,
                'category' => $p->category?->name,
                'supplier' => $p->defaultSupplier?->name,
                'in_stock' => $p->stockOnHand(),
                'available' => $p->stockAvailable(),
                'incoming' => $p->stockIncoming(),
                'selling_price' => (float) $p->selling_price,
                'landed_cost' => $showCost ? $p->effectiveCost() : null,
                'margin_percent' => $showCost ? $p->marginPercent() : null,
            ], fn ($v) => $v !== null));

        return ['count' => $rows->count(), 'products' => $rows->all()];
    }

    private function customerBalances(array $input): array
    {
        $rows = Customer::query()
            ->with(['invoices' => fn ($q) => $q->outstanding()])
            ->get()
            ->map(function (Customer $c) {
                $overdue = $c->invoices->filter(fn (Invoice $i) => $i->isOverdue());

                return [
                    'customer' => $c->name,
                    'city' => $c->city,
                    'outstanding' => $c->outstandingBalance(),
                    'overdue_amount' => round($overdue->sum(fn (Invoice $i) => $i->amountDue()), 2),
                    'overdue_invoices' => $overdue->count(),
                    'worst_days_overdue' => (int) ($overdue->max(fn (Invoice $i) => $i->daysOverdue()) ?? 0),
                    'credit_limit' => (float) $c->credit_limit,
                    'credit_used_percent' => $c->creditUsedPercent(),
                ];
            })
            ->when($input['overdue_only'] ?? false, fn ($rows) => $rows->filter(fn ($r) => $r['overdue_amount'] > 0))
            ->filter(fn ($r) => $r['outstanding'] > 0)
            ->sortByDesc('outstanding')
            ->take(min((int) ($input['limit'] ?? 25), 100))
            ->values();

        return ['count' => $rows->count(), 'customers' => $rows->all()];
    }

    private function shipments(array $input, User $user): array
    {
        $showCost = $user->can('view_cost');

        $rows = Shipment::query()
            ->when($input['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('eta')
            ->limit(min((int) ($input['limit'] ?? 20), 50))
            ->get()
            ->map(fn (Shipment $s) => array_filter([
                'shipment' => $s->number,
                'container' => $s->container_number,
                'route' => trim(($s->port_of_loading ?? '?').' to '.($s->port_of_discharge ?? '?')),
                'status' => $s->status->getLabel(),
                'eta' => $s->eta?->toDateString(),
                'volume_cbm' => (float) $s->total_volume_cbm,
                'goods_value' => $showCost ? (float) $s->total_goods_base : null,
                'shipping_costs' => $showCost ? (float) $s->total_costs_base : null,
                'cost_uplift_percent' => $showCost ? $s->costUpliftPercent() : null,
                'costing_status' => $s->landed_cost_status->getLabel(),
            ], fn ($v) => $v !== null));

        return ['count' => $rows->count(), 'shipments' => $rows->all()];
    }

    private function profitability(array $input): array
    {
        $from = Carbon::parse($input['from'])->startOfDay();
        $to = Carbon::parse($input['to'])->endOfDay();
        $worstFirst = (bool) ($input['worst_first'] ?? false);

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
            ->orderByRaw('sum(invoice_items.line_total - (invoice_items.quantity * invoice_items.unit_cost_base)) '.($worstFirst ? 'asc' : 'desc'))
            ->limit(min((int) ($input['limit'] ?? 15), 50))
            ->get()
            ->map(function ($r) {
                $revenue = round((float) $r->revenue, 2);
                $cogs = round((float) $r->cogs, 2);

                return [
                    'sku' => $r->sku,
                    'product' => $r->name,
                    'quantity_sold' => round((float) $r->qty, 2),
                    'revenue' => $revenue,
                    'cost_of_goods_sold' => $cogs,
                    'gross_profit' => round($revenue - $cogs, 2),
                    'margin_percent' => $revenue > 0 ? round(($revenue - $cogs) / $revenue * 100, 1) : 0,
                ];
            });

        return [
            'period' => $from->toDateString().' to '.$to->toDateString(),
            'ordered_by' => $worstFirst ? 'lowest profit first' : 'highest profit first',
            'products' => $rows->all(),
            'note' => 'Cost of goods is the true landed cost including freight and duty, frozen when each invoice was posted.',
        ];
    }

    private function suppliers(array $input): array
    {
        $rows = Supplier::query()
            ->withCount(['supplierProducts', 'crystalProducts', 'purchaseOrders'])
            ->limit(min((int) ($input['limit'] ?? 25), 50))
            ->get()
            ->map(fn (Supplier $s) => [
                'supplier' => $s->name,
                'chinese_name' => $s->name_zh,
                'country' => $s->country,
                'city' => $s->city,
                'orders' => $s->purchase_orders_count,
                'total_spend' => round((float) $s->purchaseOrders()->sum('total'), 2),
                'catalogue_size' => $s->supplier_products_count + $s->crystal_products_count,
                'average_lead_time_days' => $s->average_lead_time_days,
                'outstanding' => $s->outstandingBalance(),
            ]);

        return ['count' => $rows->count(), 'suppliers' => $rows->all()];
    }
}
