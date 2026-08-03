<?php

namespace App\Actions\Catalog;

use App\Models\Product;
use App\Models\ProductSize;
use Illuminate\Support\Facades\DB;

/**
 * Rebuild one supplier's shelf layout under another supplier.
 *
 * Each supplier keeps their own tree, which is right — their codes, names and
 * finishes are their own — but it means onboarding a second supplier of the
 * same kind of goods starts with retyping Crystal, Flat Crystal and everything
 * under them. Two suppliers of crystals stock the same shapes; what differs is
 * what they charge.
 *
 * Prices are deliberately not copied. A price belongs to the supplier who
 * quoted it, and carrying Supplier A's numbers into Supplier B's tree would
 * produce a price list that looks filled in and is fiction. The sizes come
 * across unpriced, ready for the Price Lists screen.
 */
class CopySupplierTree
{
    /**
     * @return int how many products were created
     */
    public function copy(int $fromSupplierId, int $toSupplierId, int $sectionId, bool $withSizes = true): int
    {
        if ($fromSupplierId === $toSupplierId) {
            return 0;
        }

        $source = Product::query()
            ->where('supplier_id', $fromSupplierId)
            ->where('price_list_section_id', $sectionId)
            ->with('sizes')
            ->orderBy('id')
            ->get();

        if ($source->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($source, $toSupplierId, $sectionId, $withSizes): int {
            /** @var array<int, int> old id => new id */
            $map = [];
            $created = 0;

            /*
             * Parents before children, so a child always has its new parent to
             * point at. Ordering by id gets that for free — a parent cannot have
             * been created after the child hanging off it.
             */
            foreach ($source as $product) {
                $copy = Product::create([
                    'name' => $product->name,
                    'name_ar' => $product->name_ar,
                    'name_ku' => $product->name_ku,
                    'name_zh' => $product->name_zh,
                    'description' => $product->description,
                    'parent_id' => $product->parent_id === null
                        ? null
                        : ($map[$product->parent_id] ?? null),
                    'supplier_id' => $toSupplierId,
                    'price_list_section_id' => $sectionId,
                    'unit_id' => $product->unit_id,
                    'weight_kg' => $product->weight_kg,
                    'volume_cbm' => $product->volume_cbm,
                    'contains_battery' => $product->contains_battery,
                    'is_active' => true,
                ]);

                $map[$product->id] = $copy->id;
                $created++;

                if (! $withSizes) {
                    continue;
                }

                foreach ($product->sizes as $size) {
                    ProductSize::create([
                        'product_id' => $copy->id,
                        'label' => $size->label,
                        'display_order' => $size->display_order,
                        // No cost, no currency, no date. See the class comment:
                        // a price belongs to whoever quoted it.
                        'cost_price' => null,
                    ]);
                }
            }

            return $created;
        });
    }
}
