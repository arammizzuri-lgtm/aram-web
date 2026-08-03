<?php

namespace App\Filament\Pages;

use App\Models\CatalogueItem;
use App\Models\CatalogueItemPrice;
use App\Models\PriceListSection;
use App\Models\Supplier;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * One screen for every flat-priced section — Textile, Packaging, Furniture.
 *
 * The columns, the field labels and the price unit all come from the section's
 * own attribute schema, so a fabric shows composition, width and GSM while a
 * packaging item shows dimensions and material. A fifth section needs a row in
 * `price_list_sections`, not a new page.
 *
 * Crystals keeps its dedicated screen because its pricing is a colour × size
 * grid rather than a flat list.
 */
class CataloguePriceList extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static string|UnitEnum|null $navigationGroup = 'Price Lists';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Textile, Packaging & Furniture';

    protected string $view = 'filament.pages.catalogue-price-list';

    public string $section = 'textile';

    public ?int $supplierId = null;

    public string $search = '';

    /** Columns opened by the operator for tiers the supplier has not quoted yet. */
    public array $extraBreaks = [];

    public string $newBreak = '';

    /**
     * Replaced by ProductPriceList, which serves all four sections.
     *
     * Kept, not deleted: the textile, packaging and furniture lines entered
     * here are still in catalogue_items, and this screen is the way back to
     * them.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        $this->section = request()->query('section', 'textile');

        if (! $this->sections()->contains('code', $this->section)) {
            $this->section = $this->sections()->first()?->code ?? 'textile';
        }

        $this->supplierId = $this->suppliers()->keys()->first();
    }

    public function getTitle(): string|Htmlable
    {
        return $this->currentSection()?->name.' price list';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->currentSection()?->description;
    }

    public function updatedSection(): void
    {
        // A different section is a different catalogue shape; nothing carries over.
        $this->reset(['search']);
        $this->supplierId = $this->suppliers()->keys()->first();
    }

    /** @return Collection<int, PriceListSection> */
    public function sections(): Collection
    {
        // Crystals is excluded — it has its own matrix screen.
        return PriceListSection::query()
            ->active()
            ->whereNot('code', 'crystals')
            ->ordered()
            ->get();
    }

    public function currentSection(): ?PriceListSection
    {
        return $this->sections()->firstWhere('code', $this->section);
    }

    /** Suppliers who actually carry something in this section. */
    public function suppliers(): Collection
    {
        $sectionId = $this->currentSection()?->id;

        if ($sectionId === null) {
            return collect();
        }

        return Supplier::query()
            ->whereHas('catalogueItems', fn ($q) => $q->where('price_list_section_id', $sectionId))
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    /** @return Collection<int, CatalogueItem> */
    public function items(): Collection
    {
        $sectionId = $this->currentSection()?->id;

        if ($sectionId === null || $this->supplierId === null) {
            return collect();
        }

        return CatalogueItem::query()
            ->forSection($sectionId)
            ->where('supplier_id', $this->supplierId)
            ->active()
            ->search($this->search)
            ->with(['prices', 'unit'])
            ->orderBy('display_order')
            ->orderBy('code')
            ->get();
    }

    /**
     * The quantity breaks this supplier quotes, plus any the operator has opened.
     *
     * Columns are the union across the catalogue, so two fabrics quoted at
     * different tiers both get somewhere to sit. A supplier who starts offering
     * a tier nobody has yet is handled by `addBreak()` — without it there would
     * be no cell to type the first one into.
     */
    public function quantityBreaks(): array
    {
        $quoted = CatalogueItemPrice::query()
            ->whereIn('catalogue_item_id', $this->items()->pluck('id'))
            ->distinct()
            ->pluck('min_quantity')
            ->map(fn ($q) => (float) $q)
            ->all();

        $breaks = array_unique(array_merge([1.0], $quoted, $this->extraBreaks));
        sort($breaks);

        return array_values($breaks);
    }

    public function addBreak(): void
    {
        $quantity = (float) $this->newBreak;

        if ($quantity <= 1 || in_array($quantity, $this->quantityBreaks(), true)) {
            $this->newBreak = '';

            return;
        }

        $this->extraBreaks[] = $quantity;
        $this->newBreak = '';
    }

    public function currency(): string
    {
        return Supplier::find($this->supplierId)?->default_currency ?? 'USD';
    }

    public function savePrice(int $itemId, string $break, ?string $value): void
    {
        $item = CatalogueItem::find($itemId);

        if ($item === null || (int) $item->supplier_id !== (int) $this->supplierId) {
            return;
        }

        $quantity = (float) $break;

        $existing = CatalogueItemPrice::query()
            ->where('catalogue_item_id', $itemId)
            ->where('min_quantity', $quantity)
            ->first();

        // An emptied cell means the supplier does not quote that break — which
        // is different from quoting it at nothing.
        if (blank($value)) {
            $existing?->delete();

            return;
        }

        if (! is_numeric($value) || (float) $value < 0) {
            return;
        }

        CatalogueItemPrice::updateOrCreate(
            ['catalogue_item_id' => $itemId, 'min_quantity' => $quantity],
            [
                'supplier_id' => $item->supplier_id,
                'price' => (float) $value,
                'currency' => $this->currency(),
                'effective_date' => today(),
                'updated_by' => auth()->id(),
            ],
        );

        Notification::make()->title('Price saved')->success()->send();
    }

    /**
     * How many lines carry a price at all — not how many cells are filled.
     *
     * Cell coverage would be the wrong question here: a supplier quoting one
     * fabric at 500/3,000 and another at 1,000/5,000 has quoted both of them
     * completely, yet only fills four of eight cells in the union grid. What
     * matters is whether a line can be ordered, which needs one price, not all.
     */
    public function coverage(): array
    {
        $items = $this->items();

        $priced = CatalogueItemPrice::query()
            ->whereIn('catalogue_item_id', $items->pluck('id'))
            ->distinct()
            ->count('catalogue_item_id');

        return [
            'priced' => $priced,
            'total' => $items->count(),
            'percent' => $items->count() > 0 ? round($priced / $items->count() * 100, 1) : 0,
        ];
    }
}
