<?php

namespace App\Services\Shipping;

use App\Models\Consignment;
use App\Models\Deal;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Attaching deals to a tracking number, and dividing the freight bill.
 *
 * The forwarder sends one bill per consignment. When that consignment carries
 * one customer's goods there is nothing to divide and no interface appears at
 * all. When it carries several, the split has to happen — otherwise neither
 * deal's profit is true, and the error is invisible because both still look
 * plausible.
 */
class ConsignmentWriter
{
    /**
     * Suggest how the freight should divide, before the operator adjusts it.
     *
     * Split by what the mode is actually charged for: sea freight buys space,
     * so CBM; air freight buys weight, so kilograms. Splitting by goods value
     * instead — the obvious lazy choice — would systematically over-charge
     * small expensive crystals and under-charge bulky cheap fabric, which for
     * this product mix is precisely backwards.
     *
     * @return array<int, Money> deal id => suggested share
     */
    public function suggest(Consignment $consignment): array
    {
        $consignment->loadMissing('deals.lines.product');

        $basis = $consignment->splitBasis();

        $weights = $consignment->deals
            ->mapWithKeys(fn (Deal $deal) => [
                $deal->id => $basis === 'weight' ? $deal->estimatedWeightKg() : $deal->estimatedCbm(),
            ])
            ->all();

        return $consignment->suggestedSplit($weights);
    }

    /**
     * Write the shares onto the deals.
     *
     * `share_is_manual` records whether the suggestion was accepted or the
     * numbers were judged, so a later question about why a deal carries the
     * freight it does has an answer.
     *
     * @param  array<int, float|string>  $shares  deal id => amount
     */
    public function applySplit(Consignment $consignment, array $shares, bool $manual = true): Consignment
    {
        return DB::transaction(function () use ($consignment, $shares, $manual) {
            foreach ($shares as $dealId => $amount) {
                $share = Money::of($amount, $consignment->freight_currency ?: 'USD');

                $consignment->deals()->updateExistingPivot($dealId, [
                    'freight_share' => $share->amount,
                    'freight_share_base' => $this->toBase($consignment, $dealId, $share)->amount,
                    'share_is_manual' => $manual,
                ]);
            }

            return $consignment->refresh();
        });
    }

    /**
     * Give a single-deal consignment the whole bill, with no interface at all.
     *
     * The common case should cost nothing to get right: one customer, one
     * tracking number, one freight amount that is entirely theirs.
     */
    public function applyWholeBillToSoleDeal(Consignment $consignment): Consignment
    {
        $consignment->loadMissing('deals');

        if ($consignment->deals->count() !== 1 || $consignment->freight_amount === null) {
            return $consignment;
        }

        return $this->applySplit(
            $consignment,
            [$consignment->deals->first()->id => $consignment->freight_amount],
            manual: false,
        );
    }

    /**
     * Convert a share into USD using the deal's own frozen rate.
     *
     * The deal's rate, not today's: a freight bill belongs to the deal it was
     * incurred for, and valuing it at a rate the deal never used would make its
     * profit disagree with every other figure on it.
     */
    private function toBase(Consignment $consignment, int $dealId, Money $share): Money
    {
        if ($share->currency === 'USD') {
            return $share;
        }

        $deal = $consignment->deals->firstWhere('id', $dealId) ?? Deal::find($dealId);

        return $deal?->toBase($share) ?? Money::of($share->amount, 'USD');
    }

    /**
     * Reasons this consignment's mode may not carry what is attached to it.
     *
     * Raised before booking rather than after the forwarder rejects it, because
     * by then a delivery date has usually been promised.
     *
     * @return array<int, string>
     */
    public function warnings(Consignment $consignment): array
    {
        $consignment->loadMissing('deals.lines');

        $warnings = [];

        if ($consignment->mode === 'air_no_battery') {
            $withBatteries = $consignment->deals
                ->filter(fn (Deal $deal) => $deal->hasBatteryGoods())
                ->map(fn (Deal $deal) => $deal->number)
                ->values();

            if ($withBatteries->isNotEmpty()) {
                $warnings[] = sprintf(
                    '%s %s battery goods, which cannot travel as ordinary air cargo. Use "Air (with battery)" or sea.',
                    $withBatteries->implode(', '),
                    $withBatteries->count() === 1 ? 'contains' : 'contain',
                );
            }
        }

        if ($consignment->isShared() && $consignment->freight_amount !== null) {
            $allocated = Money::of(
                $consignment->deals->sum(fn ($d) => (float) $d->pivot->freight_share),
                $consignment->freight_currency ?: 'USD',
            );

            $bill = Money::of($consignment->freight_amount, $consignment->freight_currency ?: 'USD');

            if (! $allocated->equals($bill)) {
                $warnings[] = sprintf(
                    'The split comes to %s but the bill is %s — %s is unaccounted for.',
                    $allocated->display(),
                    $bill->display(),
                    $bill->minus($allocated)->absolute()->display(),
                );
            }
        }

        return $warnings;
    }
}
