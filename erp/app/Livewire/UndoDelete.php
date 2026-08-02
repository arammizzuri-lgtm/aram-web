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

        $model = $class::withTrashed()->find($key);

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
