<?php

namespace App\Filament\Pages;

use App\Models\PriceListSection;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\Supplier;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Section → supplier → tree → sizes → prices.
 *
 * One screen for all four sections, because the shape is the same in each: a
 * supplier's own tree of goods, and at the bottom of it things that come in
 * sizes. What differs between crystals and furniture is vocabulary, and
 * vocabulary lives in `price_list_sections` rather than in a class.
 *
 * The tree is shown rather than a grid. A grid needs every row to share the
 * same columns, and sizes belong to their product now — a P13 in three sizes
 * and a sofa in two have no common axis to lay across the top.
 *
 * Prices are the only thing typed here. Products are added on the Products
 * screen, arrive unpriced, and are filled in from whatever the supplier last
 * sent — which is why an emptied box means "not priced yet" and not "free".
 */
class ProductPriceList extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'Price Lists';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Price lists';

    protected static ?string $title = 'Price lists';

    protected string $view = 'filament.pages.product-price-list';

    public ?string $section = null;

    public ?int $supplierId = null;

    public string $search = '';

    /** @var array<int, bool> product id => open */
    public array $expanded = [];

    /** @var array<int, string> size id => the price as typed */
    public array $prices = [];

    public function mount(): void
    {
        // The Price Lists module links here with ?section=textile and the like,
        // so arriving from one of those tiles opens the list it named.
        $requested = request()->query('section');
        $codes = $this->sections()->pluck('code');

        $this->section = $codes->contains($requested)
            ? $requested
            : $codes->first();

        $this->supplierId = $this->suppliers()->keys()->first();
        $this->loadPrices();
    }

    public function updatedSection(): void
    {
        // A different section is a different catalogue and usually a different
        // supplier; nothing about the old view still applies.
        $this->reset(['search', 'expanded', 'prices']);
        $this->supplierId = $this->suppliers()->keys()->first();
        $this->loadPrices();
    }

    public function updatedSupplierId(): void
    {
        $this->reset(['search', 'expanded', 'prices']);
        $this->loadPrices();
    }

    public function sections(): Collection
    {
        return PriceListSection::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    public function currentSection(): ?PriceListSection
    {
        return $this->sections()->firstWhere('code', $this->section);
    }

    /** Only suppliers who actually have something in this section. */
    public function suppliers(): Collection
    {
        $sectionId = $this->currentSection()?->id;

        if ($sectionId === null) {
            return collect();
        }

        return Supplier::query()
            ->whereHas('products', fn ($q) => $q->where('price_list_section_id', $sectionId))
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    /**
     * The supplier's tree for this section, flattened for display.
     *
     * Each entry carries its depth so the view can indent it, and its sizes so
     * an opened row has something to show. Searching flattens the tree to the
     * matches themselves — hunting for P13 should not mean opening Crystal and
     * Flat Crystal first — and each match keeps its trail as a subtitle.
     *
     * @return Collection<int, array{product: Product, depth: int, trail: string}>
     */
    public function rows(): Collection
    {
        $sectionId = $this->currentSection()?->id;

        if ($this->supplierId === null || $sectionId === null) {
            return collect();
        }

        $all = Product::query()
            ->where('supplier_id', $this->supplierId)
            ->where('price_list_section_id', $sectionId)
            ->with(['sizes' => fn ($q) => $q->ordered()])
            ->orderBy('name')
            ->get();

        if (filled($this->search)) {
            $byId = $all->keyBy('id');

            return $all
                ->filter(fn (Product $p) => $p->sizes->isNotEmpty()
                    && str_contains(mb_strtolower($p->name), mb_strtolower($this->search)))
                ->map(fn (Product $p) => [
                    'product' => $p,
                    'depth' => 0,
                    'trail' => $this->trail($p, $byId),
                ])
                ->values();
        }

        return $this->flatten($all, null, 0);
    }

    /**
     * The branch above a product, read off what is already loaded.
     *
     * Walking `$product->parent` would be a query per level, and the whole
     * section is in memory already — this page loads it to build the tree.
     *
     * @param  Collection<int, Product>  $byId
     */
    private function trail(Product $product, Collection $byId): string
    {
        $names = [];

        for (
            $node = $byId->get($product->parent_id);
            $node !== null;
            $node = $byId->get($node->parent_id)
        ) {
            array_unshift($names, $node->name);
        }

        return implode(' › ', $names);
    }

    /**
     * Depth-first, parents before their children.
     *
     * @param  Collection<int, Product>  $all
     * @return Collection<int, array{product: Product, depth: int, trail: string}>
     */
    private function flatten(Collection $all, ?int $parentId, int $depth): Collection
    {
        return $all
            ->where('parent_id', $parentId)
            // Indentation already shows the branch, so the trail stays empty
            // here and is only spelled out when a search has flattened it away.
            ->flatMap(fn (Product $product) => collect([
                ['product' => $product, 'depth' => $depth, 'trail' => ''],
            ])->concat($this->flatten($all, $product->id, $depth + 1)))
            ->values();
    }

    public function toggle(int $productId): void
    {
        $this->expanded[$productId] = ! ($this->expanded[$productId] ?? false);
    }

    public function isExpanded(int $productId): bool
    {
        // A search has already narrowed things to what you were looking for, so
        // making you click each result open again would be a step for nothing.
        return filled($this->search) || ($this->expanded[$productId] ?? false);
    }

    public function expandAll(): void
    {
        $this->expanded = $this->rows()
            ->filter(fn (array $row) => $row['product']->sizes->isNotEmpty())
            ->mapWithKeys(fn (array $row) => [$row['product']->id => true])
            ->all();
    }

    public function collapseAll(): void
    {
        $this->expanded = [];
    }

    public function currency(): string
    {
        return Supplier::find($this->supplierId)?->default_currency ?: 'USD';
    }

    private function loadPrices(): void
    {
        $sectionId = $this->currentSection()?->id;

        if ($this->supplierId === null || $sectionId === null) {
            $this->prices = [];

            return;
        }

        $this->prices = ProductSize::query()
            ->whereHas('product', fn ($q) => $q
                ->where('supplier_id', $this->supplierId)
                ->where('price_list_section_id', $sectionId))
            ->get()
            ->mapWithKeys(fn (ProductSize $size) => [
                // Stored at 4dp for arithmetic; shown without trailing zeros,
                // because "0.4500" in a box you might retype is just noise.
                $size->id => $size->cost_price === null
                    ? ''
                    : rtrim(rtrim((string) $size->cost_price, '0'), '.'),
            ])
            ->all();
    }

    /**
     * Write back only what moved.
     *
     * Re-saving an untouched list should be a no-op, not a hundred writes with
     * today's date on them — the effective date is what tells you when a price
     * last actually changed.
     */
    public function savePrices(): void
    {
        $currency = $this->currency();
        $changed = 0;
        $cleared = 0;

        $sizes = ProductSize::query()
            ->whereKey(array_keys($this->prices))
            ->get()
            ->keyBy('id');

        foreach ($this->prices as $sizeId => $value) {
            $size = $sizes->get((int) $sizeId);

            if ($size === null) {
                continue;
            }

            // Emptied means nobody has quoted this size yet, which is a
            // different thing from quoting it at nothing.
            if (blank($value)) {
                if ($size->cost_price !== null) {
                    $size->update(['cost_price' => null, 'effective_date' => null]);
                    $cleared++;
                }

                continue;
            }

            if (! is_numeric($value) || (float) $value < 0) {
                continue;
            }

            /*
             * An unpriced size has to be treated as different from every number,
             * including zero. Casting first would make null and 0 both 0.0, and
             * quoting something at free — which suppliers do, for samples and
             * for parts thrown in with an order — would silently not save.
             */
            if ($size->cost_price !== null && (float) $size->cost_price === (float) $value) {
                continue;
            }

            $size->update([
                'cost_price' => (float) $value,
                'currency' => $currency,
                'effective_date' => today(),
            ]);
            $changed++;
        }

        $this->loadPrices();

        Notification::make()
            ->title($changed || $cleared ? 'Price list saved' : 'Nothing changed')
            ->body($changed || $cleared
                ? trim("{$changed} prices updated".($cleared ? ", {$cleared} cleared" : ''), ', ')
                : 'No box was different from what was already stored.')
            ->success()
            ->send();
    }

    /** @return array{priced: int, total: int, percent: float} */
    public function coverage(): array
    {
        $sectionId = $this->currentSection()?->id;

        if ($this->supplierId === null || $sectionId === null) {
            return ['priced' => 0, 'total' => 0, 'percent' => 0.0];
        }

        $sizes = ProductSize::query()
            ->whereHas('product', fn ($q) => $q
                ->where('supplier_id', $this->supplierId)
                ->where('price_list_section_id', $sectionId));

        $total = (clone $sizes)->count();
        $priced = (clone $sizes)->priced()->count();

        return [
            'priced' => $priced,
            'total' => $total,
            'percent' => $total > 0 ? round($priced / $total * 100, 1) : 0.0,
        ];
    }

    public static function canAccess(): bool
    {
        // The cost side of the business. Same boundary the product screen draws.
        return auth()->user()?->can('view_cost') ?? false;
    }
}
