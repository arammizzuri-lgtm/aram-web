<?php

namespace App\Livewire;

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
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The Undo button behind every delete notification.
 *
 * It has to be a component of its own, and mounted on every page, because of
 * how a notification travels: it is serialised by `Notification::toArray()` and
 * rebuilt on the other side, so a closure cannot make the trip. What survives
 * is an event name and its data — so the notification dispatches, and this
 * listens.
 *
 * The event arrives from the browser, which means the class name in it is not
 * to be trusted. Only the models listed here can be restored, and only by
 * someone allowed to see them; anything else is ignored without ceremony.
 */
class UndoDelete extends Component
{
    /**
     * Everything that soft-deletes and has a screen behind it.
     *
     * A list rather than a check for the SoftDeletes trait: `restore()` on an
     * arbitrary model named by whoever is holding the keyboard is not something
     * to leave to a trait check.
     *
     * @var array<int, class-string<Model>>
     */
    public const RESTORABLE = [
        Deal::class,
        Customer::class,
        Supplier::class,
        Product::class,
        ProductCategory::class,
        Consignment::class,
        DealPurchase::class,
        CustomerInvoice::class,
        CustomerPayment::class,
        SupplierPayment::class,
        Expense::class,
        CollectionPoint::class,
        Currency::class,
        ExchangeRate::class,
        User::class,
    ];

    /**
     * The allowlist, for anywhere else that turns a name back into a record.
     *
     * @return array<int, class-string<Model>>
     */
    public static function restorable(): array
    {
        return self::RESTORABLE;
    }

    /**
     * The scopes that hide a row whose parent has gone.
     *
     * A purchase under a deleted deal, a supplier payment against a deleted
     * purchase, a match whose invoice has gone — each stops counting, which is
     * the entire point of those scopes and what keeps a deleted deal's costs
     * off the dashboard.
     *
     * Two questions must ignore them. *What is in the bin* — a row hidden from
     * the way back is a delete you cannot undo, which is the one thing this
     * page exists to prevent. And *what still points at this row* — a foreign
     * key does not care what is being reported; it is there or it is not.
     *
     * @var array<int, string>
     */
    public const PARENT_SCOPES = ['dealStillThere', 'purchaseStillThere', 'bothEndsPresent'];

    /**
     * Every row of a model, deleted ones and orphans included.
     *
     * @param  class-string<Model>  $class
     */
    public static function everyRowOf(string $class): Builder
    {
        return $class::withTrashed()->withoutGlobalScopes(self::PARENT_SCOPES);
    }

    /**
     * Put it back, then reload.
     *
     * The row reappearing where it was is the confirmation — better than a
     * second notification saying it happened, and it avoids a restored record
     * sitting invisible behind a table that has already been rendered.
     *
     * @param  array{model?: string, key?: mixed}  $record
     */
    #[On('undo-delete')]
    public function restore(array $record): void
    {
        $class = $record['model'] ?? null;
        $key = $record['key'] ?? null;

        if (! is_string($class) || ! in_array($class, self::RESTORABLE, true) || blank($key)) {
            return;
        }

        /*
         * The cost side of this business is a permission, not a screen, so the
         * check is the same one the resource itself makes. Undo must not be a
         * back door into restoring something you could not have deleted.
         */
        if (! auth()->check()) {
            return;
        }

        $model = self::everyRowOf($class)->find($key);

        if (! $model instanceof Model || ! $model->trashed()) {
            return;
        }

        $model->restore();

        $this->js('window.location.reload()');
    }

    public function render(): View
    {
        return view('livewire.undo-delete');
    }
}
