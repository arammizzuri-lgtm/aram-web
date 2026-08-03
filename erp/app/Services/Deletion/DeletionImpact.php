<?php

namespace App\Services\Deletion;

use App\Livewire\UndoDelete;
use App\Models\CollectionPoint;
use App\Models\Consignment;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerPayment;
use App\Models\Deal;
use App\Models\DealPurchase;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * What deleting this particular record would actually do.
 *
 * "Are you sure?" is not a question — it asks you to confirm something you have
 * not been told. The answer differs enormously between a supplier typed twice
 * this morning and a customer with four years of invoices behind them, and the
 * person clicking is the only one who can weigh that. So the dialog says what
 * is at stake, in figures, and then gets out of the way.
 *
 * Three things are worked out here:
 *
 *   - **what hangs off it**, in plain sentences
 *   - **whether it can ever be erased for good** — anything a live record still
 *     points at cannot be, and the database would refuse anyway
 *   - **the gentler thing to do instead**, where there is one: deactivating a
 *     supplier you still have history with, cancelling an invoice the customer
 *     is holding a copy of
 *
 * Every delete is reversible, so none of this is a wall. It is the difference
 * between a decision and a reflex.
 */
class DeletionImpact
{
    /** What goes, what stays, and what moves. */
    public function describe(Model $record): string
    {
        $lines = $this->consequences($record);

        if ($lines === []) {
            return 'Nothing else depends on this. '.$this->restoreNote();
        }

        return implode(' ', $lines).' '.$this->restoreNote();
    }

    /**
     * Whether this can be erased permanently rather than merely deleted.
     *
     * False whenever anything still points at it — **including rows that have
     * themselves been deleted**, because a deleted row is hidden and not gone,
     * and its foreign key is as real as any other. Counting only the living
     * ones is what offered "Delete permanently" on a customer whose every deal
     * had been deleted, and met a constraint that had never moved.
     *
     * A guess either way, so it is wrong in the safe direction: the erase is
     * wrapped in a catch as well, because this is a list of relationships
     * maintained by hand and the database is the only thing that actually knows.
     */
    public function canBeErased(Model $record): bool
    {
        return $this->consequences($record) === [];
    }

    /**
     * The gentler move, named so the dialog can offer it.
     *
     * Deactivating keeps a record readable everywhere it has already been used
     * and only takes it out of the pickers, which is nearly always what is
     * actually wanted about a supplier you have stopped using.
     */
    public function alternative(Model $record): ?string
    {
        return match (true) {
            $record instanceof CustomerInvoice => 'Cancelling it instead keeps the number and leaves a visible trail, '
                .'which matters if the customer is holding a copy.',

            $record instanceof Deal => 'Cancelling it instead keeps the deal and its history visible, '
                .'and stops it counting as open work.',

            $this->hasActiveFlag($record) && $this->consequences($record) !== [] => 'Deactivating it instead takes it out of the pickers while leaving '
                    .'everything it was already used on readable.',

            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    private function consequences(Model $record): array
    {
        return array_values(array_filter(match (true) {
            $record instanceof Deal => $this->deal($record),
            $record instanceof Customer => $this->customer($record),
            $record instanceof Supplier => $this->supplier($record),
            $record instanceof Product => $this->product($record),
            $record instanceof ProductCategory => $this->category($record),
            $record instanceof Consignment => $this->consignment($record),
            $record instanceof DealPurchase => $this->purchase($record),
            $record instanceof CustomerInvoice => $this->invoice($record),
            $record instanceof CustomerPayment => $this->customerPayment($record),
            $record instanceof SupplierPayment => $this->supplierPayment($record),
            $record instanceof Expense => $this->expense($record),
            $record instanceof CollectionPoint => $this->collectionPoint($record),
            $record instanceof Currency => $this->currency($record),
            $record instanceof ExchangeRate => [],
            $record instanceof User => $this->user($record),
            default => [],
        }));
    }

    // ---------------------------------------------------------------- deals

    /**
     * @return array<int, ?string>
     */
    private function deal(Deal $deal): array
    {
        $deal->loadMissing(['purchases.payments', 'invoices.allocations']);

        $invoices = $deal->invoices->whereNotIn('status', ['cancelled']);
        $received = $deal->invoices->sum(fn (CustomerInvoice $i) => $i->paidBase()->toFloat());
        $paidOut = $deal->purchases->sum(fn (DealPurchase $p) => $p->paidBase()->toFloat());

        return [
            $this->count($invoices->count(), 'invoice', 'issued to the customer'),
            $received > 0 ? Money::of($received, 'USD')->display().' received against them.' : null,

            /*
             * The figure is cost and stays behind the same boundary as every
             * other figure in this business — but the *fact* does not.
             *
             * It used to return null outright without `view_cost`, which left
             * an assistant deleting a deal told nothing at all about the money
             * already sent to suppliers over it. And `canBeErased()` is this
             * same list, so a line that appears for one person and not another
             * makes a safety check depend on who is looking — here it happens
             * to be caught by the sentence below, which is luck rather than
             * design.
             */
            $paidOut > 0
                ? (auth()->user()?->can('view_cost')
                    ? Money::of($paidOut, 'USD')->display().' already paid to suppliers.'
                    : 'Money has already been paid to suppliers against it.')
                : null,

            /*
             * The quiet one. A deleted deal drops out of every query that
             * reaches through it — profit by product, goods bought without
             * approval, and now the purchases underneath it and the money sent
             * against them, which used to stay behind and keep counting as owed
             * to suppliers with no deal on any screen to trace them back to.
             *
             * The selling side is deliberately not the same. An invoice is a
             * document the customer is holding; you do not un-issue one by
             * deleting the deal behind it, so it stays, and so does their
             * balance. Cancel the invoice if that is what you meant.
             */
            $invoices->isNotEmpty() || $received > 0 || $paidOut > 0
                ? 'Its figures leave the reports, the purchases under it included; '
                    .'the invoices themselves stay, and so does the customer\'s balance.'
                : null,
        ];
    }

    // ------------------------------------------------------ who you deal with

    /** @return array<int, ?string> */
    private function customer(Customer $customer): array
    {
        $owed = $customer->outstandingBalance();

        return [
            $this->count($this->stillThere($customer->deals()), 'deal'),
            $this->count($this->stillThere($customer->invoices()), 'invoice'),
            abs($owed) > 0.005
                ? ($owed > 0
                    ? 'They still owe you '.Money::of($owed, 'USD')->display().'.'
                    : 'You are holding '.Money::of(abs($owed), 'USD')->display().' of their credit.')
                : null,
        ];
    }

    /** @return array<int, ?string> */
    private function supplier(Supplier $supplier): array
    {
        $paid = (float) $supplier->payments()->sum('base_amount');

        return [
            $this->count($this->stillThere($supplier->purchases()), 'purchase'),
            $this->count($this->stillThere($supplier->supplierProducts()), 'priced product'),
            $this->count($this->stillThere($supplier->crystalProducts()), 'crystal colour'),
            $this->count($this->stillThere($supplier->catalogueItems()), 'catalogue item'),
            $paid > 0 ? Money::of($paid, 'USD')->display().' paid to them.' : null,
        ];
    }

    // ------------------------------------------------------------ what you sell

    /** @return array<int, ?string> */
    private function product(Product $product): array
    {
        return [
            $this->count($this->stillThere($product->dealLines()), 'deal line'),
            $this->count($this->stillThere($product->supplierProducts()), 'supplier price'),
        ];
    }

    /** @return array<int, ?string> */
    private function category(ProductCategory $category): array
    {
        return [
            $this->count($this->stillThere($category->products()), 'product'),
            $this->count($this->stillThere($category->children()), 'sub-category'),
        ];
    }

    // -------------------------------------------------------------- the work

    /** @return array<int, ?string> */
    private function consignment(Consignment $consignment): array
    {
        // Qualified, because the share lives on the pivot and `deals` has no
        // column by that name to sum.
        $freight = (float) $consignment->deals()->sum('consignment_deal.freight_share_base');

        return [
            $this->count($this->stillThere($consignment->deals()), 'deal', 'shipping under it'),
            /*
             * The freight is the part worth saying out loud. It is a cost sitting
             * on those deals' profit, and it leaves with the consignment.
             */
            $freight > 0
                ? Money::of($freight, 'USD')->display().' of freight is charged to them, and comes off again.'
                : null,
        ];
    }

    /** @return array<int, ?string> */
    private function purchase(DealPurchase $purchase): array
    {
        $paid = $purchase->paidBase();

        return [
            $this->count($this->stillThere($purchase->lines()), 'line', 'buying through it'),
            $paid->isPositive() ? $paid->display().' already sent to the supplier.' : null,
        ];
    }

    // ------------------------------------------------------------- the money

    /** @return array<int, ?string> */
    private function invoice(CustomerInvoice $invoice): array
    {
        $paid = $invoice->paidBase();

        return [
            'Issued to '.($invoice->customer?->name ?? 'the customer').'.',
            $paid->isPositive() ? $paid->display().' has been paid against it.' : null,
            $this->balanceShift($invoice->customer, -(float) $invoice->total_base),
        ];
    }

    /**
     * A deleted payment changes what somebody owes you.
     *
     * This is the one that most needs saying: the row disappears quietly and
     * the customer's balance moves with it, so the next conversation about
     * money starts from a different number than the last one did.
     *
     * @return array<int, ?string>
     */
    private function customerPayment(CustomerPayment $payment): array
    {
        return [
            $this->count($this->stillThere($payment->allocations()), 'invoice', 'matched to it'),
            $this->balanceShift($payment->customer, (float) $payment->base_amount),
        ];
    }

    /** @return array<int, ?string> */
    private function supplierPayment(SupplierPayment $payment): array
    {
        return [
            'Recorded against '.($payment->purchase?->number ?? 'a purchase').'.',
            'What you owe that supplier goes up by '
                .Money::of((float) $payment->base_amount, 'USD')->display().'.',
        ];
    }

    /** @return array<int, ?string> */
    private function expense(Expense $expense): array
    {
        return [
            $expense->deal
                ? 'Charged to '.$expense->deal->number.', whose profit goes up by '
                    .Money::of((float) $expense->base_amount, 'USD')->display().'.'
                : null,
        ];
    }

    // ----------------------------------------------------------- the settings

    /** @return array<int, ?string> */
    private function collectionPoint(CollectionPoint $point): array
    {
        return [$this->count($this->stillThere($point->consignments()), 'consignment', 'collected from it')];
    }

    /** @return array<int, ?string> */
    private function currency(Currency $currency): array
    {
        return [$this->count($this->stillThere($currency->ratesFrom()), 'exchange rate')];
    }

    /** @return array<int, ?string> */
    private function user(User $user): array
    {
        return [
            $user->is($this->currentUser()) ? 'This is you — you would be signing yourself out.' : null,
            'They lose access immediately. What they recorded stays.',
        ];
    }

    // ----------------------------------------------------------------- pieces

    /**
     * The effect on an account balance, said as a movement rather than a total.
     *
     * "Goes from 2,000 to 3,400" is checkable against what you already believe;
     * a bare new figure is not.
     */
    private function balanceShift(?Model $customer, float $change): ?string
    {
        if (! $customer instanceof Customer || abs($change) < 0.005) {
            return null;
        }

        $before = $customer->outstandingBalance();

        return sprintf(
            '%s owing goes from %s to %s.',
            $customer->name,
            Money::of($before, 'USD')->display(),
            Money::of($before + $change, 'USD')->display(),
        );
    }

    private function count(int $count, string $noun, string $verb = 'on it'): ?string
    {
        if ($count < 1) {
            return null;
        }

        return sprintf('%d %s %s.', $count, str($noun)->plural($count), $verb);
    }

    /**
     * How many rows still point at this record — deleted ones included.
     *
     * The "included" is the whole point, and it was missing. A deleted deal is
     * hidden, not gone: its row is still in the table and still holds the
     * customer's id. So a customer whose every deal had been deleted counted
     * zero, was offered "Delete permanently", and met a foreign key that had
     * been there the entire time.
     *
     * Anything that soft-deletes therefore gets counted with its dead included.
     * `withTrashed()` only exists on relations whose far side keeps them, hence
     * the check.
     *
     * @param  HasMany|BelongsToMany  $relation
     */
    private function stillThere(Relation $relation): int
    {
        if (in_array(SoftDeletes::class, class_uses_recursive($relation->getRelated()), true)) {
            $relation->withTrashed();
        }

        /*
         * And without the scopes that hide a row whose parent has gone.
         *
         * A purchase under a deleted deal stops counting everywhere else —
         * that is the point of those scopes — but the row is still in the
         * table, still holding this supplier's id, and `supplier_id` is
         * restrict-on-delete. Counted through the scope it reads as zero, the
         * dialog offers "Delete permanently", and the database refuses. This
         * question is about foreign keys, not about what should be reported.
         */
        return $relation->withoutGlobalScopes(UndoDelete::PARENT_SCOPES)->count();
    }

    private function hasActiveFlag(Model $record): bool
    {
        return array_key_exists('is_active', $record->getAttributes());
    }

    private function restoreNote(): string
    {
        return 'It can be brought back from Settings › Recently deleted.';
    }

    private function currentUser(): ?Model
    {
        $user = auth()->user();

        return $user instanceof Model ? $user : null;
    }
}
