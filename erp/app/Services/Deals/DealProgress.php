<?php

namespace App\Services\Deals;

use App\Models\Deal;

/**
 * Where a deal has got to.
 *
 * A deal has nine states and, until this existed, only three were reachable:
 * the quotation moved it to `quoted` and approval to `approved`, and then it
 * stopped. Nothing ever became `purchasing`, `shipping`, `arrived`, `delivered`
 * or `closed`, so the status filter offered choices that matched nothing, the
 * dashboard's "delivered but not invoiced" alert could never fire, and every
 * deal you had ever done stayed open forever.
 *
 * Two ways a deal moves, and both are needed:
 *
 *   - by itself, when something happens that can only mean one thing — goods
 *     ordered from a supplier means purchasing, a consignment in transfer means
 *     shipping
 *   - by hand, on the deal screen, because the system does not see everything
 *     and you should never be arguing with it about where your own deal is
 */
class DealProgress
{
    /**
     * The ordinary life of a deal, in order.
     *
     * `cancelled` is not in it: it is not a later stage of anything, it is the
     * deal not happening, and it is only ever chosen deliberately.
     */
    public const ORDER = [
        'draft',
        'quoted',
        'approved',
        'purchasing',
        'shipping',
        'arrived',
        'delivered',
        'closed',
    ];

    /**
     * Move a deal forward, never back.
     *
     * Forward-only because these calls come from events that recur: saving the
     * deal re-syncs its purchases, editing a consignment re-checks its status.
     * If those could move a deal backwards, adding one line to a delivered deal
     * would drag it back to purchasing, and the status would describe the last
     * thing you touched rather than where the deal actually is.
     *
     * A cancelled or closed deal is left alone entirely. Both are endings, and
     * an ending that a background event can undo is not an ending.
     */
    public function advanceTo(Deal $deal, string $status): Deal
    {
        if (! $this->isLaterThanCurrent($deal, $status)) {
            return $deal;
        }

        $deal->update(['status' => $status]);

        return $deal;
    }

    /**
     * Whether the deal is finished with — one way or the other.
     *
     * Kept here rather than on the model because it is a statement about the
     * progression, and the progression is what this class owns.
     */
    public function isFinished(Deal $deal): bool
    {
        return in_array($deal->status, ['closed', 'cancelled'], true);
    }

    private function isLaterThanCurrent(Deal $deal, string $status): bool
    {
        if ($this->isFinished($deal)) {
            return false;
        }

        $to = array_search($status, self::ORDER, true);
        $from = array_search($deal->status, self::ORDER, true);

        // A status outside the ordinary run — `cancelled` — is never arrived at
        // automatically, only chosen.
        if ($to === false) {
            return false;
        }

        return $from === false || $to > $from;
    }

    /**
     * What a consignment's own status says about the deals riding on it.
     *
     * `awaiting` is deliberately absent: a tracking number can be logged before
     * the goods move, and that says nothing new about the deal beyond what
     * ordering them already said.
     */
    public function statusForConsignment(string $consignmentStatus): ?string
    {
        return match ($consignmentStatus) {
            'in_transfer' => 'shipping',
            'arrived' => 'arrived',
            'delivered' => 'delivered',
            default => null,
        };
    }
}
