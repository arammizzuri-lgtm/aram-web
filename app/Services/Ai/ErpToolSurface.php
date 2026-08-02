<?php

namespace App\Services\Ai;

use App\Models\Consignment;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;

/**
 * The fixed set of questions the assistant is allowed to ask the database.
 *
 * Deliberately a closed surface of typed, parameterised queries — the model
 * never writes SQL and can never reach a table that is not exposed here. Every
 * tool is read-only, and each one re-checks the caller's permissions rather
 * than trusting the prompt: an assistant login asking "what did we pay for
 * this?" gets an answer with the cost figures removed, not a refusal the model
 * could be talked out of.
 *
 * Rebuilt around deals. The old stock and container tools are gone because the
 * things they reported on no longer exist — a question with no possible answer
 * is worse than a missing tool, because the model will try to answer it anyway.
 */
class ErpToolSurface
{
    /** Tools whose output is meaningless once cost is stripped out. */
    private const COST_BEARING = ['get_deal_profitability', 'get_suppliers'];

    /** @return array<int, array<string, mixed>> the tool schemas sent to the model */
    public function definitions(User $user): array
    {
        $tools = [
            [
                'name' => 'find_deals',
                'description' => 'Search customer orders by customer, status or text. Use for '
                    .'"what is happening with X", "which orders are waiting", "show me open deals".',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'customer' => ['type' => 'string', 'description' => 'Customer name, partial match'],
                        'status' => [
                            'type' => 'string',
                            'enum' => array_keys(Deal::STATUSES),
                            'description' => 'Restrict to one stage',
                        ],
                        'open_only' => ['type' => 'boolean', 'description' => 'Exclude closed and cancelled'],
                        'limit' => ['type' => 'integer', 'description' => 'Max rows, default 25'],
                    ],
                ],
            ],
            [
                'name' => 'find_products',
                'description' => 'Look up products by name, SKU, Chinese name, category or supplier.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'search' => ['type' => 'string'],
                        'category' => ['type' => 'string'],
                        'supplier' => ['type' => 'string'],
                        'limit' => ['type' => 'integer'],
                    ],
                ],
            ],
            [
                'name' => 'get_customer_balances',
                'description' => 'What customers owe, and what credit they hold. Use for '
                    .'"who owes me money", "does X have credit", "what is outstanding".',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'customer' => ['type' => 'string'],
                        'owing_only' => ['type' => 'boolean'],
                        'limit' => ['type' => 'integer'],
                    ],
                ],
            ],
            [
                'name' => 'get_consignments',
                'description' => 'Shipments in progress by tracking number, mode and status. Use for '
                    .'"where are my goods", "what is still in transit", "when does X arrive".',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'tracking_number' => ['type' => 'string'],
                        'status' => ['type' => 'string', 'enum' => array_keys(Consignment::STATUSES)],
                        'in_transit_only' => ['type' => 'boolean'],
                        'limit' => ['type' => 'integer'],
                    ],
                ],
            ],
            [
                'name' => 'get_deal_profitability',
                'description' => 'Revenue, cost and profit per deal. Use for "which orders made money", '
                    .'"what did I earn on X", "worst deals".',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                        'to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                        'worst_first' => ['type' => 'boolean', 'description' => 'Show losses first'],
                        'limit' => ['type' => 'integer'],
                    ],
                ],
            ],
            [
                'name' => 'get_suppliers',
                'description' => 'Suppliers, what is owed to them, and how much has been bought.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'search' => ['type' => 'string'],
                        'limit' => ['type' => 'integer'],
                    ],
                ],
            ],
        ];

        /*
         * Withheld rather than emptied.
         *
         * A profitability tool with the money removed returns a list of deal
         * numbers and nothing else, which invites the model to guess at the
         * gap. Not offering the tool is the honest signal that this user does
         * not get to ask the question.
         */
        return $user->can('view_cost')
            ? $tools
            : array_values(array_filter(
                $tools,
                fn (array $tool) => ! in_array($tool['name'], self::COST_BEARING, true),
            ));
    }

    /** @return array<string, mixed> */
    public function run(string $name, array $input, User $user): array
    {
        // Second gate. The first is not offering the tool; this one assumes the
        // first was bypassed, because a security boundary with one lock is a
        // boundary that fails completely the first time something changes.
        if (in_array($name, self::COST_BEARING, true) && ! $user->can('view_cost')) {
            return ['error' => 'You do not have permission to see cost or profit figures.'];
        }

        return match ($name) {
            'find_deals' => $this->deals($input, $user),
            'find_products' => $this->products($input, $user),
            'get_customer_balances' => $this->customerBalances($input),
            'get_consignments' => $this->consignments($input),
            'get_deal_profitability' => $this->profitability($input),
            'get_suppliers' => $this->suppliers($input),
            default => ['error' => "Unknown tool: {$name}"],
        };
    }

    // ----------------------------------------------------------------- tools

    private function deals(array $input, User $user): array
    {
        $showCost = $user->can('view_cost');

        $rows = Deal::query()
            ->with(['customer', 'lines', 'purchases.costs', 'expenses', 'consignments'])
            ->when($input['customer'] ?? null, fn ($q, $c) => $q->whereHas(
                'customer', fn ($w) => $w->whereLike('name', "%{$c}%")
            ))
            ->when($input['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($input['open_only'] ?? false, fn ($q) => $q->open())
            ->orderByDesc('deal_date')
            ->limit(min((int) ($input['limit'] ?? 25), 100))
            ->get()
            ->map(fn (Deal $deal) => array_filter([
                'deal' => $deal->number,
                'customer' => $deal->customer?->name,
                'status' => Deal::STATUSES[$deal->status] ?? $deal->status,
                'date' => $deal->deal_date?->toDateString(),
                'expected_delivery' => $deal->expected_delivery?->toDateString(),
                'lines' => $deal->lines->count(),
                'revenue_usd' => $deal->revenueBase()->toFloat(),
                'cost_usd' => $showCost ? $deal->costBase()->toFloat() : null,
                'profit_usd' => $showCost ? $deal->profitBase()->toFloat() : null,
                'approved' => $deal->isApproved(),
            ], fn ($v) => $v !== null));

        return ['count' => $rows->count(), 'deals' => $rows->all()];
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
            ->when($input['category'] ?? null, fn ($q, $c) => $q->whereHas(
                'category', fn ($w) => $w->whereLike('name', "%{$c}%")
            ))
            ->when($input['supplier'] ?? null, fn ($q, $s) => $q->whereHas(
                'defaultSupplier', fn ($w) => $w->whereLike('name', "%{$s}%")
            ))
            ->limit(min((int) ($input['limit'] ?? 25), 100))
            ->get()
            ->map(fn (Product $p) => array_filter([
                'sku' => $p->sku,
                'name' => $p->name,
                'name_zh' => $p->name_zh,
                'category' => $p->category?->name,
                'supplier' => $p->defaultSupplier?->name,
                'contains_battery' => $p->contains_battery ? true : null,
                'selling_price' => (float) $p->selling_price,
                'cost' => $showCost ? (float) $p->cost_price : null,
                'margin_percent' => $showCost ? $p->marginPercent() : null,
            ], fn ($v) => $v !== null));

        return ['count' => $rows->count(), 'products' => $rows->all()];
    }

    private function customerBalances(array $input): array
    {
        $rows = Customer::query()
            ->when($input['customer'] ?? null, fn ($q, $c) => $q->whereLike('name', "%{$c}%"))
            ->get()
            ->map(fn (Customer $c) => [
                'customer' => $c->name,
                'city' => $c->city,
                'owes_usd' => $c->outstandingBalance(),
                'credit_held_usd' => $c->unallocatedCredit(),
            ])
            ->when(
                $input['owing_only'] ?? false,
                fn ($rows) => $rows->filter(fn ($r) => $r['owes_usd'] > 0)
            )
            ->sortByDesc('owes_usd')
            ->take(min((int) ($input['limit'] ?? 25), 100))
            ->values();

        return ['count' => $rows->count(), 'customers' => $rows->all()];
    }

    /** No cost gate: where the goods are is not commercially sensitive. */
    private function consignments(array $input): array
    {
        $rows = Consignment::query()
            ->with('deals.customer')
            ->when($input['tracking_number'] ?? null, fn ($q, $t) => $q->whereLike('tracking_number', "%{$t}%"))
            ->when($input['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($input['in_transit_only'] ?? false, fn ($q) => $q->inTransit())
            ->orderByDesc('shipped_at')
            ->limit(min((int) ($input['limit'] ?? 20), 50))
            ->get()
            ->map(fn (Consignment $c) => array_filter([
                'tracking_number' => $c->tracking_number,
                'mode' => Consignment::MODES[$c->mode] ?? $c->mode,
                'status' => Consignment::STATUSES[$c->status] ?? $c->status,
                'boxes' => $c->boxes,
                'weight_kg' => $c->gross_weight_kg ? (float) $c->gross_weight_kg : null,
                'cbm' => $c->cbm ? (float) $c->cbm : null,
                'shipped' => $c->shipped_at?->toDateString(),
                'arrived' => $c->arrived_at?->toDateString(),
                'customers' => $c->deals->map(fn (Deal $d) => $d->customer?->name)->filter()->values()->all(),
            ], fn ($v) => $v !== null && $v !== []));

        return ['count' => $rows->count(), 'consignments' => $rows->all()];
    }

    private function profitability(array $input): array
    {
        $worstFirst = (bool) ($input['worst_first'] ?? false);

        $rows = Deal::query()
            ->with(['customer', 'lines', 'purchases.costs', 'expenses', 'consignments'])
            ->when($input['from'] ?? null, fn ($q, $f) => $q->whereDate('deal_date', '>=', $f))
            ->when($input['to'] ?? null, fn ($q, $t) => $q->whereDate('deal_date', '<=', $t))
            ->get()
            ->map(fn (Deal $deal) => [
                'deal' => $deal->number,
                'customer' => $deal->customer?->name,
                'date' => $deal->deal_date?->toDateString(),
                'revenue_usd' => $deal->revenueBase()->toFloat(),
                'cost_usd' => $deal->costBase()->toFloat(),
                'profit_usd' => $deal->profitBase()->toFloat(),
                'margin_percent' => $deal->marginPercent(),
            ])
            ->sortBy('profit_usd', SORT_REGULAR, ! $worstFirst)
            ->take(min((int) ($input['limit'] ?? 20), 100))
            ->values();

        return ['count' => $rows->count(), 'deals' => $rows->all()];
    }

    private function suppliers(array $input): array
    {
        $rows = Supplier::query()
            ->withCount(['purchases', 'supplierProducts', 'crystalProducts'])
            ->when($input['search'] ?? null, fn ($q, $s) => $q->whereLike('name', "%{$s}%"))
            ->limit(min((int) ($input['limit'] ?? 25), 100))
            ->get()
            ->map(fn (Supplier $s) => array_filter([
                'supplier' => $s->name,
                'name_zh' => $s->name_zh,
                'country' => $s->country,
                'city' => $s->city,
                'purchases' => $s->purchases_count,
                'catalogue_items' => $s->supplier_products_count + $s->crystal_products_count,
                'owed_usd' => $s->outstandingBalance(),
            ], fn ($v) => $v !== null));

        return ['count' => $rows->count(), 'suppliers' => $rows->all()];
    }
}
