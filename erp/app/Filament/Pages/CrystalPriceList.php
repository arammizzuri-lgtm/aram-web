<?php

namespace App\Filament\Pages;

use App\Models\CrystalPrice;
use App\Models\CrystalProduct;
use App\Models\CrystalSize;
use App\Models\Supplier;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Supplier → catalogue → price grid.
 *
 * The supplier is chosen first and everything below reloads from that supplier's
 * own records: their codes, their names, the sizes they actually quote, their
 * prices. Adding Supplier B is a row in `suppliers` plus their catalogue — this
 * page needs no change at all.
 */
class CrystalPriceList extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Price Lists';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Crystals';

    protected static ?string $title = 'Crystal price list';

    protected string $view = 'filament.pages.crystal-price-list';

    public ?int $supplierId = null;

    public string $search = '';

    public string $finish = 'all';

    public string $sort = 'catalogue';

    /** @var array<string, string> cell key "productId-sizeId" => entered price */
    public array $prices = [];

    /**
     * Replaced by ProductPriceList, which serves all four sections.
     *
     * The page and its tables are left standing rather than deleted: the old
     * colour charts and their prices are still in them, and this is the only
     * way back to that data if the tree turns out to have lost something.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        $this->supplierId = $this->suppliers()->keys()->first();
        $this->loadPrices();
    }

    public function updatedSupplierId(): void
    {
        // A different supplier is a different catalogue; nothing carries over.
        $this->reset(['search', 'finish', 'sort', 'prices']);
        $this->loadPrices();
    }

    /** Suppliers that actually have a crystal catalogue. */
    public function suppliers(): Collection
    {
        return Supplier::query()
            ->whereHas('crystalProducts')
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    /**
     * Every active size, always.
     *
     * Showing only the sizes a supplier had already priced made the grid
     * impossible to finish: the columns you had not filled in yet were the ones
     * missing, so there was nowhere to type them.
     */
    public function sizes(): Collection
    {
        return CrystalSize::query()->active()->ordered()->get();
    }

    /** How many of those sizes this supplier actually quotes, as a statistic. */
    public function quotedSizeCount(): int
    {
        return $this->supplierId === null
            ? 0
            : CrystalSize::query()->active()->pricedBy($this->supplierId)->count();
    }

    public function crystals(): Collection
    {
        if ($this->supplierId === null) {
            return collect();
        }

        $crystals = CrystalProduct::query()
            ->forSupplier($this->supplierId)
            ->active()
            ->search($this->search)
            ->when($this->finish !== 'all', fn ($q) => $q->where('finish', $this->finish))
            ->with('prices')
            ->orderBy('display_order')
            ->get();

        return $this->applySort($crystals);
    }

    /**
     * Sorting codes needs natural ordering, not alphabetical.
     *
     * Sorted as strings, P100 lands between P10 and P11. strnatcmp compares the
     * numeric runs as numbers, so P2 < P10 < P100 as anyone reading a catalogue
     * would expect.
     */
    private function applySort(Collection $crystals): Collection
    {
        return match ($this->sort) {
            'code' => $crystals->sort(fn ($a, $b) => strnatcasecmp($a->crystal_code, $b->crystal_code))->values(),
            'name' => $crystals->sortBy('crystal_name', SORT_NATURAL | SORT_FLAG_CASE)->values(),
            'finish' => $crystals
                ->sortBy(fn ($c) => [array_search($c->finish, ['plain', 'ab', 'special'], true), $c->display_order])
                ->values(),
            default => $crystals,
        };
    }

    /**
     * Write the current sort back as the stored catalogue order.
     *
     * Sorting the view is temporary; this makes it the order everyone sees,
     * including anyone comparing the screen against the printed chart.
     */
    public function applySortPermanently(): void
    {
        if ($this->supplierId === null || $this->sort === 'catalogue') {
            return;
        }

        $ordered = $this->applySort(
            CrystalProduct::forSupplier($this->supplierId)->orderBy('display_order')->get()
        );

        foreach ($ordered as $position => $crystal) {
            $crystal->forceFill(['display_order' => $position])->saveQuietly();
        }

        $label = $this->sortOptions()[$this->sort] ?? $this->sort;
        $this->sort = 'catalogue';

        Notification::make()
            ->title('Catalogue reordered')
            ->body("{$ordered->count()} colours saved in {$label} order.")
            ->success()
            ->send();
    }

    /** @return array<string, string> */
    public function sortOptions(): array
    {
        return [
            'catalogue' => 'Catalogue order (as printed)',
            'code' => 'Code — P01, P02, P03…',
            'name' => 'Colour name (A–Z)',
            'finish' => 'Finish — plain, AB, effects',
        ];
    }

    public function currency(): string
    {
        return Supplier::find($this->supplierId)?->default_currency ?? 'CNY';
    }

    private function loadPrices(): void
    {
        if ($this->supplierId === null) {
            return;
        }

        $this->prices = CrystalPrice::query()
            ->forSupplier($this->supplierId)
            ->get()
            ->mapWithKeys(fn (CrystalPrice $price) => [
                // Stored at 4dp for arithmetic; shown without the trailing zeros,
                // because "120.0000" in an editable cell is just noise to retype.
                "{$price->crystal_product_id}-{$price->crystal_size_id}" => rtrim(rtrim((string) $price->price, '0'), '.'),
            ])
            ->all();
    }

    /**
     * Persist whatever was typed into the grid.
     *
     * Only cells that actually moved are written, and each one leaves a history
     * row, so re-saving an untouched grid is a no-op rather than 630 pointless
     * history entries.
     */
    public function savePrices(): void
    {
        if ($this->supplierId === null) {
            return;
        }

        $currency = $this->currency();
        $changed = 0;
        $cleared = 0;

        foreach ($this->prices as $key => $value) {
            [$productId, $sizeId] = array_pad(explode('-', $key), 2, null);

            if (! is_numeric($productId) || ! is_numeric($sizeId)) {
                continue;
            }

            $existing = CrystalPrice::query()
                ->where('supplier_id', $this->supplierId)
                ->where('crystal_product_id', $productId)
                ->where('crystal_size_id', $sizeId)
                ->first();

            // An emptied cell means "this supplier does not offer this size",
            // which is different from offering it at nothing.
            if (blank($value)) {
                if ($existing) {
                    $existing->delete();
                    $cleared++;
                }

                continue;
            }

            if (! is_numeric($value) || (float) $value < 0) {
                continue;
            }

            if ($existing === null) {
                CrystalPrice::create([
                    'supplier_id' => $this->supplierId,
                    'crystal_product_id' => $productId,
                    'crystal_size_id' => $sizeId,
                    'price' => (float) $value,
                    'currency' => $currency,
                    'effective_date' => today(),
                    'updated_by' => auth()->id(),
                ]);
                $changed++;

                continue;
            }

            if ($existing->updatePrice((float) $value)) {
                $changed++;
            }
        }

        $this->loadPrices();

        Notification::make()
            ->title($changed || $cleared ? 'Price list saved' : 'Nothing changed')
            ->body($changed || $cleared
                ? trim("{$changed} prices updated".($cleared ? ", {$cleared} cleared" : ''), ', ')
                : 'No cell was different from what was already stored.')
            ->success()
            ->send();
    }

    /** @return array<string, string> */
    public function finishOptions(): array
    {
        return [
            'all' => 'All finishes',
            'plain' => 'Plain colours',
            'ab' => 'Aurora Borealis (AB)',
            'special' => 'Opals, neons & effects',
        ];
    }

    public function coverage(): array
    {
        if ($this->supplierId === null) {
            return ['priced' => 0, 'total' => 0, 'percent' => 0];
        }

        $colours = CrystalProduct::forSupplier($this->supplierId)->active()->count();
        $sizes = $this->sizes()->count();
        $total = $colours * $sizes;
        $priced = CrystalPrice::forSupplier($this->supplierId)->count();

        return [
            'priced' => $priced,
            'total' => $total,
            'percent' => $total > 0 ? round($priced / $total * 100, 1) : 0,
        ];
    }
}
