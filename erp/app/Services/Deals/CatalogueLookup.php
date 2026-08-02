<?php

namespace App\Services\Deals;

use App\Models\CatalogueItem;
use App\Models\CrystalPrice;
use App\Models\CrystalProduct;
use App\Models\Product;

/**
 * The bridge from the price lists to a deal line.
 *
 * The deal screen used to have no way to reach the catalogue at all: every line
 * was typed by hand, "From price list" was an option that did nothing, and the
 * cost, the Chinese name, the battery flag and the weight all had to be
 * remembered or looked up on another screen. Which meant the price lists — the
 * part of this system with the most work in it — served the deal not at all.
 *
 * Three families sit behind one search box here, because from the deal's side
 * they are the same question: *what is this thing, what does it cost me, and
 * what do I charge for it?*
 *
 *   products   — general catalogue, cost per supplier, sell per customer type
 *   items      — textile, packaging, furniture; flat lists with quantity breaks
 *   crystals   — a colour × size matrix, so a pick names both
 *
 * A key is what the screen holds onto: `product:12`, `item:34`, `crystal:5:7`.
 * Nothing here writes to the database — it reads the lists and hands back the
 * fields a line would otherwise be typed with.
 */
class CatalogueLookup
{
    /**
     * Search all three price lists at once.
     *
     * @return array<string, string> key => label
     */
    public function search(?string $term, int $limit = 30): array
    {
        $term = trim((string) $term);

        if (mb_strlen($term) < 2) {
            return [];
        }

        return array_merge(
            $this->searchProducts($term, $limit),
            $this->searchItems($term, $limit),
            $this->searchCrystals($term, $limit),
        );
    }

    /** The label for a key already on a line, so an existing pick shows itself. */
    public function label(?string $key): ?string
    {
        $parts = $this->parse($key);

        if ($parts === null) {
            return null;
        }

        return match ($parts[0]) {
            'product' => ($product = Product::find($parts[1]))
                ? $this->productLabel($product)
                : null,
            'item' => ($item = CatalogueItem::with('section')->find($parts[1]))
                ? $this->itemLabel($item)
                : null,
            'crystal' => ($price = CrystalPrice::with(['crystalProduct', 'size'])
                ->where('crystal_product_id', $parts[1])
                ->where('crystal_size_id', $parts[2] ?? 0)
                ->first())
                ? $this->crystalLabel($price)
                : null,
            default => null,
        };
    }

    /**
     * The key for a line that already names something in the catalogue.
     *
     * Lets an existing line show what it was picked from when the deal is
     * reopened, rather than presenting an empty search box beside a description
     * that plainly came from somewhere.
     *
     * The crystal case is checked first because a crystal line can carry a
     * product id as well — the colour may also be stocked — and the pick that
     * describes it is the colour and size, not the product behind them.
     */
    public function keyForIds(
        ?int $productId = null,
        ?int $catalogueItemId = null,
        ?int $crystalProductId = null,
        ?int $crystalSizeId = null,
    ): ?string {
        return match (true) {
            $crystalProductId !== null && $crystalSizeId !== null => "crystal:{$crystalProductId}:{$crystalSizeId}",
            $catalogueItemId !== null => "item:{$catalogueItemId}",
            $productId !== null => "product:{$productId}",
            default => null,
        };
    }

    /**
     * Everything a line takes from the catalogue.
     *
     * Cost and selling price both, which is the entire promise of the redesign:
     * one pick fills the supplier's side and the customer's side together.
     *
     * `list_price` is deliberately separate from `unit_cost` rather than folded
     * into one "price" — a line priced from the list still has to show what it
     * cost you, or the profit on it cannot be seen.
     *
     * @return array<string, mixed>|null
     */
    public function resolve(?string $key, float $quantity = 1, ?int $customerTypeId = null): ?array
    {
        $parts = $this->parse($key);

        if ($parts === null) {
            return null;
        }

        return match ($parts[0]) {
            'product' => $this->fromProduct((int) $parts[1], max($quantity, 1), $customerTypeId),
            'item' => $this->fromItem((int) $parts[1], max($quantity, 1), $customerTypeId),
            'crystal' => $this->fromCrystal((int) $parts[1], (int) ($parts[2] ?? 0), $customerTypeId),
            default => null,
        };
    }

    // ------------------------------------------------------------- resolving

    /** @return array<string, mixed>|null */
    private function fromProduct(int $id, float $quantity, ?int $customerTypeId): ?array
    {
        $product = Product::with(['supplierProducts', 'sellPrices', 'unit'])->find($id);

        if ($product === null) {
            return null;
        }

        /*
         * Whose price to take when several suppliers quote the same product.
         *
         * The preferred one first, then the product's default supplier, then
         * the cheapest — in that order because the first two are decisions
         * somebody made and the third is only a guess. Whatever it lands on is
         * shown in the supplier box on the line, where it can be changed.
         */
        $source = $product->supplierProducts->firstWhere('is_preferred', true)
            ?? $product->supplierProducts->firstWhere('supplier_id', $product->default_supplier_id)
            ?? $product->supplierProducts->sortBy(fn ($sp) => (float) $sp->unit_price)->first();

        $sell = $product->sellPriceFor($customerTypeId, $quantity);

        /*
         * The standard selling price is the fallback, exactly as the product
         * screen says it is: "used when no customer-type price applies". It is
         * required on every product, so pricing from the list works from the
         * first deal rather than only after every product has been priced for
         * every type.
         */
        /*
         * Cast before the fallback, not after: `selling_price` is decimal-cast,
         * so an unset one reads back as the string "0.0000" — which PHP counts
         * as true, since only "0" and "" are false. Written the other way round
         * an unpriced product reports a price of nothing rather than no price,
         * and the deal screen fills the box with zero instead of saying it does
         * not know.
         */
        $listPrice = $sell?->price ?? (((float) $product->selling_price) ?: null);
        $listCurrency = $sell?->currency ?? ($product->selling_price_currency ?: 'USD');

        return $this->payload(
            description: $product->name,
            descriptionKu: $product->name_ku,
            descriptionZh: $product->name_zh,
            unit: $product->unit?->code,
            supplierId: $source?->supplier_id,
            unitCost: $source?->unit_price,
            costCurrency: $source?->currency,
            containsBattery: (bool) $product->contains_battery,
            listPrice: $listPrice,
            listCurrency: $listCurrency,
            ids: ['product_id' => $product->id],
        );
    }

    /** @return array<string, mixed>|null */
    private function fromItem(int $id, float $quantity, ?int $customerTypeId): ?array
    {
        $item = CatalogueItem::with(['prices', 'sellPrices', 'unit'])->find($id);

        if ($item === null) {
            return null;
        }

        $cost = $item->priceFor($quantity);
        $sell = $item->sellPriceFor($customerTypeId);

        return $this->payload(
            description: trim($item->code.' '.$item->name),
            descriptionKu: null,
            descriptionZh: $item->name_zh,
            unit: $item->unit?->code,
            supplierId: $item->supplier_id,
            unitCost: $cost?->price,
            costCurrency: $cost?->currency,
            containsBattery: (bool) $item->contains_battery,
            listPrice: $sell?->price,
            listCurrency: $sell?->currency,
            // A catalogue entry that is also stocked carries the product too, so
            // weight and volume — which live on the product — reach the freight
            // split.
            ids: ['catalogue_item_id' => $item->id, 'product_id' => $item->product_id],
        );
    }

    /** @return array<string, mixed>|null */
    private function fromCrystal(int $productId, int $sizeId, ?int $customerTypeId): ?array
    {
        $crystal = CrystalProduct::with(['sellPrices'])->find($productId);

        if ($crystal === null || $sizeId <= 0) {
            return null;
        }

        $cost = CrystalPrice::with('size')
            ->where('crystal_product_id', $productId)
            ->where('crystal_size_id', $sizeId)
            ->first();

        $sell = $crystal->sellPriceFor($sizeId, $customerTypeId);
        $size = $cost?->size?->label;

        $name = trim($crystal->crystal_code.' '.$crystal->crystal_name);

        return $this->payload(
            description: trim($name.($size ? ' · '.$size : '')),
            descriptionKu: null,
            descriptionZh: null,
            unit: 'pcs',
            supplierId: $crystal->supplier_id,
            unitCost: $cost?->price,
            costCurrency: $cost?->currency,
            containsBattery: false,
            listPrice: $sell?->price,
            listCurrency: $sell?->currency,
            ids: [
                'crystal_product_id' => $crystal->id,
                'crystal_size_id' => $sizeId,
                'product_id' => $crystal->product_id,
            ],
        );
    }

    /**
     * One shape for all three, so the screen fills a line the same way whatever
     * it was picked from.
     *
     * @param  array<string, mixed>  $ids
     * @return array<string, mixed>
     */
    private function payload(
        string $description,
        ?string $descriptionKu,
        ?string $descriptionZh,
        ?string $unit,
        ?int $supplierId,
        mixed $unitCost,
        ?string $costCurrency,
        bool $containsBattery,
        mixed $listPrice,
        ?string $listCurrency,
        array $ids,
    ): array {
        return [
            'description' => $description,
            'description_ku' => $descriptionKu,
            'description_zh' => $descriptionZh,
            'unit' => $unit ?: 'pcs',
            'supplier_id' => $supplierId,
            'unit_cost' => $unitCost === null ? null : (float) $unitCost,
            'cost_currency' => $costCurrency ?: 'CNY',
            'contains_battery' => $containsBattery,
            'list_price' => $listPrice === null ? null : (float) $listPrice,
            'list_price_currency' => $listCurrency ?: 'USD',
            'product_id' => $ids['product_id'] ?? null,
            'catalogue_item_id' => $ids['catalogue_item_id'] ?? null,
            'crystal_product_id' => $ids['crystal_product_id'] ?? null,
            'crystal_size_id' => $ids['crystal_size_id'] ?? null,
        ];
    }

    // -------------------------------------------------------------- searching

    /** @return array<string, string> */
    private function searchProducts(string $term, int $limit): array
    {
        return Product::query()
            ->active()
            ->where(fn ($q) => $q->whereLike('name', "%{$term}%")
                ->orWhereLike('sku', "%{$term}%")
                ->orWhereLike('name_zh', "%{$term}%"))
            ->limit($limit)
            ->get()
            ->mapWithKeys(fn (Product $p) => ["product:{$p->id}" => $this->productLabel($p)])
            ->all();
    }

    /** @return array<string, string> */
    private function searchItems(string $term, int $limit): array
    {
        return CatalogueItem::query()
            ->with('section')
            ->active()
            ->search($term)
            ->limit($limit)
            ->get()
            ->mapWithKeys(fn (CatalogueItem $i) => ["item:{$i->id}" => $this->itemLabel($i)])
            ->all();
    }

    /**
     * Crystals are searched through their prices, not their colours.
     *
     * A colour on its own cannot become a line — 20mm P07 and 10mm P07 are
     * different goods at different prices — so what is offered is the priced
     * combinations, which are also exactly the ones the supplier will sell you.
     *
     * @return array<string, string>
     */
    private function searchCrystals(string $term, int $limit): array
    {
        $productIds = CrystalProduct::query()
            ->active()
            ->search($term)
            ->limit($limit)
            ->pluck('id');

        if ($productIds->isEmpty()) {
            return [];
        }

        return CrystalPrice::query()
            ->with(['crystalProduct', 'size'])
            ->whereIn('crystal_product_id', $productIds)
            ->limit($limit)
            ->get()
            ->sortBy(fn (CrystalPrice $p) => (float) $p->size?->size_mm)
            ->mapWithKeys(fn (CrystalPrice $p) => [
                "crystal:{$p->crystal_product_id}:{$p->crystal_size_id}" => $this->crystalLabel($p),
            ])
            ->all();
    }

    // ---------------------------------------------------------------- labels

    private function productLabel(Product $product): string
    {
        return trim($product->name.($product->sku ? "  ·  {$product->sku}" : ''));
    }

    private function itemLabel(CatalogueItem $item): string
    {
        $section = $item->section?->name;

        return trim($item->code.' '.$item->name).($section ? "  ·  {$section}" : '');
    }

    private function crystalLabel(CrystalPrice $price): string
    {
        $crystal = $price->crystalProduct;

        return trim(($crystal?->crystal_code ?? '').' '.($crystal?->crystal_name ?? ''))
            .'  ·  '.($price->size?->label ?? '');
    }

    /**
     * Split a key into its family and ids.
     *
     * @return array<int, string>|null
     */
    private function parse(?string $key): ?array
    {
        if (blank($key) || ! str_contains((string) $key, ':')) {
            return null;
        }

        $parts = explode(':', (string) $key);

        return in_array($parts[0], ['product', 'item', 'crystal'], true) ? $parts : null;
    }
}
