<?php

namespace App\Services\Reporting;

use App\Filament\Resources\CustomerPayments\CustomerPaymentResource;
use App\Filament\Resources\Deals\DealResource;
use App\Models\Consignment;
use App\Models\CustomerInvoice;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentAllocation;
use App\Models\Deal;
use App\Models\DealLine;
use App\Models\DealPurchase;
use App\Models\Expense;
use App\Models\SupplierPayment;
use App\Services\Deals\DealProgress;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The numbers the dashboard and reports read from.
 *
 * All in USD, because that is the only currency both sides of this business can
 * be measured in. Everything is derived from frozen figures rather than
 * recomputed from today's rates, so a figure quoted last month still says the
 * same thing this month.
 */
class BusinessMetrics
{
    /**
     * A rolling window rather than "this month".
     *
     * On the 1st, a calendar month compares one day against a full previous
     * month and reports a collapse that did not happen. Rolling 30 days always
     * compares like with like.
     */
    public function window(int $days = 30): array
    {
        return [now()->subDays($days)->startOfDay(), now()->endOfDay()];
    }

    // ------------------------------------------------------------- headlines

    /** What customers were billed in the window, cancelled invoices excluded. */
    public function revenue(Carbon $from, Carbon $to): Money
    {
        return Money::of(
            CustomerInvoice::query()
                ->whereNot('status', 'cancelled')
                ->whereBetween('invoice_date', [$from, $to])
                ->sum('total_base'),
            'USD',
        );
    }

    /**
     * What those deals cost, over the same window.
     *
     * Counted on the deals that were invoiced rather than on purchases made in
     * the window: a deal bought in March and sold in April belongs to April, or
     * the two halves never meet and the margin is nonsense.
     */
    public function cost(Carbon $from, Carbon $to): Money
    {
        $dealIds = CustomerInvoice::query()
            ->whereNot('status', 'cancelled')
            ->whereBetween('invoice_date', [$from, $to])
            ->distinct()
            ->pluck('deal_id');

        $deals = Deal::query()
            ->whereIn('id', $dealIds)
            ->with(['lines', 'purchases.costs', 'expenses', 'consignments'])
            ->get();

        return $this->sum($deals->map(fn (Deal $deal) => $deal->costBase()));
    }

    public function profit(Carbon $from, Carbon $to): Money
    {
        return $this->revenue($from, $to)->minus($this->cost($from, $to));
    }

    public function marginPercent(Carbon $from, Carbon $to): float
    {
        $revenue = $this->revenue($from, $to)->toFloat();

        return $revenue > 0 ? round($this->profit($from, $to)->toFloat() / $revenue * 100, 1) : 0.0;
    }

    // -------------------------------------------------------------- balances

    /** Everything invoiced and not yet received. */
    public function receivables(): Money
    {
        $invoiced = Money::of(
            CustomerInvoice::query()->whereNot('status', 'cancelled')->sum('total_base'),
            'USD',
        );

        $matched = Money::of(CustomerPaymentAllocation::query()->sum('base_amount'), 'USD');

        return $invoiced->minus($matched);
    }

    /**
     * Money held that is not yet against anything.
     *
     * Reported apart from receivables rather than netted into it: an advance
     * you are holding and a debt still outstanding are different facts, and
     * collapsing them hides the one you need to act on.
     */
    public function customerCredit(): Money
    {
        $received = Money::of(CustomerPayment::query()->sum('base_amount'), 'USD');
        $matched = Money::of(CustomerPaymentAllocation::query()->sum('base_amount'), 'USD');

        $credit = $received->minus($matched);

        return $credit->isPositive() ? $credit : Money::zero('USD');
    }

    /** What is still owed to suppliers on live purchases. */
    public function payables(): Money
    {
        $purchases = DealPurchase::query()
            ->whereNot('status', 'cancelled')
            ->with(['lines', 'costs', 'payments'])
            ->get();

        return $this->sum($purchases->map(fn (DealPurchase $p) => $p->outstandingBase()));
    }

    /**
     * Goods bought before the customer committed.
     *
     * Approval is a warning here rather than a wall, so this is the running
     * total of what that judgement is currently costing you — the number worth
     * watching, because nothing else surfaces it.
     *
     * The rule lives on the model. This total and the purchases screen had each
     * spelled it out separately and drifted apart: the screen flagged rows off
     * the frozen `bought_at_risk` flag alone, so it kept warning about deals
     * that had since been approved while this figure — correctly — read zero.
     */
    public function boughtAtRisk(): Money
    {
        $purchases = DealPurchase::query()
            ->atRisk()
            ->with(['lines', 'costs'])
            ->get();

        return $this->sum($purchases->map(fn (DealPurchase $p) => $p->totalBase()));
    }

    /**
     * What the exchange houses took, across everything.
     *
     * Small on any one payment and invisible in a margin. Reported on its own
     * because it is the only way it ever gets looked at.
     */
    public function transferLosses(Carbon $from, Carbon $to): Money
    {
        $payments = SupplierPayment::query()
            ->whereNotNull('actual_cost_base')
            ->whereBetween('paid_at', [$from, $to])
            ->get();

        return $this->sum($payments->map(fn (SupplierPayment $p) => $p->transferLossBase()));
    }

    public function freightSpend(Carbon $from, Carbon $to): Money
    {
        return Money::of(
            Consignment::query()
                ->whereNotNull('freight_base')
                ->whereBetween('shipped_at', [$from, $to])
                ->sum('freight_base'),
            'USD',
        );
    }

    public function overheads(Carbon $from, Carbon $to): Money
    {
        return Money::of(
            Expense::query()
                ->whereNull('deal_id')          // deal costs are already in cost()
                ->whereNot('status', 'draft')
                ->whereBetween('expense_date', [$from, $to])
                ->sum('base_amount'),
            'USD',
        );
    }

    // ----------------------------------------------------------------- lists

    /** Deals that are waiting on you rather than on someone else. */
    public function needsAttention(): Collection
    {
        /*
         * Each alert carries a link, and the link is asked of the resource
         * rather than written out. These were strings beginning "/admin", which
         * is where the panel used to live — every one of them became a 404 the
         * day it moved to /erp, and silently, because a hardcoded path cannot
         * be wrong until somebody clicks it.
         */
        $items = collect();

        $unapproved = Deal::query()
            ->whereNull('approved_at')
            ->whereNot('status', 'cancelled')
            // The same rule again, so the count and the money below agree.
            ->whereHas('purchases', fn ($q) => $q->atRisk())
            ->count();

        if ($unapproved > 0) {
            $items->push([
                'title' => "{$unapproved} ".str('deal')->plural($unapproved).' bought without approval',
                'body' => 'Goods are on order that nobody has committed to. '
                    .$this->boughtAtRisk()->display().' at your own risk.',
                'url' => DealResource::getUrl(),
                'tone' => 'warning',
            ]);
        }

        $arrivedUnbilled = Deal::query()
            ->whereIn('status', ['arrived', 'delivered'])
            ->whereDoesntHave('invoices', fn ($q) => $q->where('type', 'goods')->whereNot('status', 'cancelled'))
            ->count();

        if ($arrivedUnbilled > 0) {
            $items->push([
                'title' => "{$arrivedUnbilled} ".str('deal')->plural($arrivedUnbilled).' delivered but not invoiced',
                'body' => 'The goods are with the customer and nothing has been billed.',
                'url' => DealResource::getUrl(),
                'tone' => 'danger',
            ]);
        }

        /*
         * Freight that arrived but was never billed on.
         *
         * You told me shipping is invoiced separately once the cost is known —
         * which means there is a gap where the cost is known and the invoice
         * has not been raised. This is that gap.
         */
        $unbilledShipping = Deal::query()
            ->whereHas('consignments', fn ($q) => $q->wherePivot('freight_share_base', '>', 0))
            ->whereDoesntHave('invoices', fn ($q) => $q->where('type', 'shipping')->whereNot('status', 'cancelled'))
            ->count();

        if ($unbilledShipping > 0) {
            $items->push([
                'title' => "{$unbilledShipping} ".str('deal')->plural($unbilledShipping).' with shipping not billed',
                'body' => 'The freight cost is known but has not been invoiced to the customer.',
                'url' => DealResource::getUrl(),
                'tone' => 'warning',
            ]);
        }

        $unmatched = CustomerPayment::query()
            ->where('direction', 'in')
            ->get()
            ->filter(fn (CustomerPayment $p) => $p->load('allocations')->unallocatedBase()->isPositive())
            ->count();

        if ($unmatched > 0) {
            $items->push([
                'title' => "{$unmatched} ".str('payment')->plural($unmatched).' not matched to an invoice',
                'body' => 'Money is on account. Matching it keeps the balances readable.',
                'url' => CustomerPaymentResource::getUrl(),
                'tone' => 'info',
            ]);
        }

        return $items;
    }

    /** @return Collection<int, Deal> */
    /**
     * Open work, by the stage it has reached.
     *
     * The business is organised around the deal and the dashboard never showed
     * one — it opened on six money tiles and a chart, with no answer to "what
     * is actually on?" This is that answer: how many requests are sitting at
     * each stage and what they are worth, which is also where the queue is
     * backing up.
     *
     * Closed and cancelled are left out. They are not work.
     *
     * @return Collection<int, array{stage: string, label: string, count: int, value: Money}>
     */
    public function pipeline(): Collection
    {
        $deals = Deal::query()
            ->open()
            ->with(['lines', 'customer'])
            ->get()
            ->groupBy('status');

        return collect(DealProgress::ORDER)
            ->reject(fn (string $stage) => $stage === 'closed')
            ->map(function (string $stage) use ($deals): array {
                $atStage = $deals->get($stage, collect());

                return [
                    'stage' => $stage,
                    'label' => Deal::STATUSES[$stage] ?? $stage,
                    'count' => $atStage->count(),
                    'value' => Money::of(
                        $atStage->sum(fn (Deal $deal) => $deal->revenueBase()->toFloat()),
                        'USD',
                    ),
                ];
            })
            ->values();
    }

    public function dealsInProgress(int $limit = 10): Collection
    {
        return Deal::query()
            ->open()
            ->with(['customer', 'lines', 'purchases.costs', 'expenses', 'consignments'])
            ->orderByDesc('deal_date')
            ->limit($limit)
            ->get();
    }

    // --------------------------------------------------------------- reports

    /** @return Collection<int, array<string, mixed>> */
    public function profitByDeal(Carbon $from, Carbon $to): Collection
    {
        return Deal::query()
            ->whereBetween('deal_date', [$from, $to])
            ->whereNot('status', 'cancelled')
            ->with(['customer', 'lines', 'purchases.costs', 'expenses', 'consignments'])
            ->get()
            ->map(fn (Deal $deal) => [
                'deal' => $deal->number,
                'customer' => $deal->customer?->name,
                'date' => $deal->deal_date?->toDateString(),
                'revenue' => $deal->revenueBase()->toFloat(),
                'cost' => $deal->costBase()->toFloat(),
                'profit' => $deal->profitBase()->toFloat(),
                'margin' => $deal->marginPercent(),
                // A lump commission belongs to the deal, not to any product in
                // it, so per-product figures on this deal are estimates.
                'approximate' => $deal->perLineProfitIsApproximate(),
            ])
            ->sortByDesc('profit')
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function profitByCustomer(Carbon $from, Carbon $to): Collection
    {
        return $this->profitByDeal($from, $to)
            ->groupBy('customer')
            ->map(fn (Collection $rows, string $customer) => [
                'customer' => $customer,
                'deals' => $rows->count(),
                'revenue' => round($rows->sum('revenue'), 2),
                'cost' => round($rows->sum('cost'), 2),
                'profit' => round($rows->sum('profit'), 2),
                'margin' => $rows->sum('revenue') > 0
                    ? round($rows->sum('profit') / $rows->sum('revenue') * 100, 1)
                    : 0.0,
            ])
            ->sortByDesc('profit')
            ->values();
    }

    /**
     * What each supplier is actually worth to you.
     *
     * Two figures, and both genuinely belong to the supplier. The **goods
     * margin** is exact: a deal line carries its own cost, its own sell price
     * and the supplier it was bought from, so nothing is apportioned. The
     * **cost of paying them** is exact too — every supplier payment records
     * what the transfer really took above the quoted rate, and that is a cost
     * of dealing with this supplier and nobody else.
     *
     * Freight is deliberately absent. A consignment carries deals, not
     * suppliers, so a freight figure per supplier would be a guess wearing the
     * clothes of a measurement. The shipping comparison answers that question
     * where it can be answered honestly.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function profitBySupplier(Carbon $from, Carbon $to): Collection
    {
        $lines = DealLine::query()
            ->whereNotNull('supplier_id')
            ->whereHas('deal', fn ($q) => $q
                ->whereBetween('deal_date', [$from, $to])
                ->whereNot('status', 'cancelled'))
            ->with('supplier')
            ->get()
            ->groupBy('supplier_id');

        // What the exchange took, per supplier, across the same window.
        $losses = SupplierPayment::query()
            ->whereBetween('paid_at', [$from, $to])
            ->get()
            ->groupBy('supplier_id')
            ->map(fn (Collection $payments) => $payments->sum(
                fn (SupplierPayment $payment) => $payment->transferLossBase()->toFloat()
            ));

        return $lines
            ->map(function (Collection $group, int|string $supplierId) use ($losses): array {
                $revenue = $group->sum(fn (DealLine $line) => $line->sellTotalBase()->toFloat());
                $cost = $group->sum(fn (DealLine $line) => $line->costTotalBase()->toFloat());
                $transfer = (float) ($losses[$supplierId] ?? 0);
                $margin = $revenue - $cost;

                return [
                    'supplier' => $group->first()->supplier?->name ?? 'Unknown',
                    'supplier_id' => (int) $supplierId,
                    'revenue' => round($revenue, 2),
                    'cost' => round($cost, 2),
                    'goods_margin' => round($margin, 2),
                    'transfer_cost' => round($transfer, 2),
                    'profit' => round($margin - $transfer, 2),
                    'margin_percent' => $revenue > 0 ? round(($margin - $transfer) / $revenue * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('profit')
            ->values();
    }

    /**
     * Which way of setting a price actually earns more.
     *
     * The three methods are mixed inside a single deal, so the comparison is
     * per line rather than per deal — and this is the only place the system can
     * say whether your judgement when typing a price beats your standard
     * markup, or whether the price list is quietly leaving money behind.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function marginByPricingMethod(Carbon $from, Carbon $to): Collection
    {
        $labels = DealLine::PRICING_METHODS;

        return DealLine::query()
            ->whereHas('deal', fn ($q) => $q
                ->whereBetween('deal_date', [$from, $to])
                ->whereNot('status', 'cancelled'))
            ->get()
            ->groupBy('pricing_method')
            ->map(function (Collection $lines, string $method) use ($labels): array {
                $revenue = $lines->sum(fn (DealLine $line) => $line->sellTotalBase()->toFloat());
                $cost = $lines->sum(fn (DealLine $line) => $line->costTotalBase()->toFloat());

                return [
                    'method' => $method,
                    'label' => $labels[$method] ?? $method,
                    'lines' => $lines->count(),
                    'revenue' => round($revenue, 2),
                    'profit' => round($revenue - $cost, 2),
                    /*
                     * The comparison is the margin, not the total. A method used
                     * on twice as many lines will always win on volume, and that
                     * says nothing at all about which is the better way to price.
                     */
                    'margin_percent' => $revenue > 0 ? round(($revenue - $cost) / $revenue * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('margin_percent')
            ->values();
    }

    /**
     * What each shipping mode really costs, in your own numbers.
     *
     * Sea is billed for space and air for weight, so the two arrive quoted in
     * units that cannot be compared — which is why the choice usually gets made
     * on feel. Both figures are given for both modes: the cost of a kilo by sea
     * and of a cubic metre by air are not what anybody bills you, but they are
     * what makes the decision comparable.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function shippingEconomics(Carbon $from, Carbon $to): Collection
    {
        return Consignment::query()
            ->whereNotNull('freight_base')
            ->where(fn ($q) => $q
                ->whereBetween('shipped_at', [$from, $to])
                ->orWhereBetween('created_at', [$from, $to]))
            ->get()
            ->groupBy('mode')
            ->map(function (Collection $shipments, string $mode): array {
                $freight = $shipments->sum(fn (Consignment $c) => (float) $c->freight_base);
                $weight = $shipments->sum(fn (Consignment $c) => (float) $c->gross_weight_kg);
                $volume = $shipments->sum(fn (Consignment $c) => (float) $c->cbm);

                return [
                    'mode' => $mode,
                    'label' => Consignment::MODES[$mode] ?? $mode,
                    'shipments' => $shipments->count(),
                    'freight' => round($freight, 2),
                    'kg' => round($weight, 2),
                    'cbm' => round($volume, 3),
                    'per_kg' => $weight > 0 ? round($freight / $weight, 2) : null,
                    'per_cbm' => $volume > 0 ? round($freight / $volume, 2) : null,
                ];
            })
            ->sortByDesc('freight')
            ->values();
    }

    /**
     * The deals earning least, so a thin one surfaces.
     *
     * A monthly total averages a bad deal away: a month can look healthy while
     * a third of its orders barely paid for themselves. Ranked by margin and
     * not by profit, because a small order at 4% is the same mistake a large
     * one at 4% only makes more expensive.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function thinnestDeals(Carbon $from, Carbon $to, int $limit = 8): Collection
    {
        return Deal::query()
            ->whereBetween('deal_date', [$from, $to])
            ->whereNot('status', 'cancelled')
            ->with(['customer', 'lines', 'purchases.costs', 'expenses', 'consignments'])
            ->get()
            ->filter(fn (Deal $deal) => $deal->revenueBase()->isPositive())
            ->map(fn (Deal $deal) => [
                'deal' => $deal->number,
                'id' => $deal->id,
                'customer' => $deal->customer?->name ?? 'Unknown',
                'revenue' => round($deal->revenueBase()->toFloat(), 2),
                'profit' => round($deal->profitBase()->toFloat(), 2),
                'margin_percent' => $deal->marginPercent(),
            ])
            ->sortBy('margin_percent')
            ->take($limit)
            ->values();
    }

    /**
     * Per-product profit, marked approximate where a deal commission exists.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function profitByProduct(Carbon $from, Carbon $to): Collection
    {
        return DealLine::query()
            ->whereHas('deal', fn ($q) => $q
                ->whereBetween('deal_date', [$from, $to])
                ->whereNot('status', 'cancelled'))
            ->with('deal')
            ->get()
            ->groupBy('description')
            ->map(fn (Collection $lines, string $description) => [
                'product' => $description,
                'quantity' => round($lines->sum(fn (DealLine $l) => (float) $l->quantity), 2),
                'revenue' => round($lines->sum(fn (DealLine $l) => $l->sellTotalBase()->toFloat()), 2),
                'cost' => round($lines->sum(fn (DealLine $l) => $l->costTotalBase()->toFloat()), 2),
                'profit' => round($lines->sum(fn (DealLine $l) => $l->profitBase()->toFloat()), 2),
                'approximate' => $lines->contains(fn (DealLine $l) => $l->deal?->perLineProfitIsApproximate()),
            ])
            ->sortByDesc('profit')
            ->values();
    }

    // --------------------------------------------------------------- helpers

    /** @param  iterable<Money>  $amounts */
    private function sum(iterable $amounts): Money
    {
        $total = Money::zero('USD');

        foreach ($amounts as $amount) {
            $total = $total->plus($amount);
        }

        return $total;
    }
}
