<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The four sections now open the one screen that serves all of them.
 *
 * Their tiles pointed at the crystal matrix and the flat catalogue list, which
 * are the two screens the tree replaced. Left alone, the Price Lists module
 * would keep sending people to the old pages and their old, separate data.
 *
 * The panel lives at /erp, not /admin. Every one of these links has always
 * said /admin and so has always been a 404 — harmless until now only because
 * nothing renders the tiles yet. Writing the same mistake into the new link
 * would bake it in for good, so they are corrected on the way past.
 *
 * Only the link changes. Nothing about the sections themselves — their names,
 * their attribute schemas, their order — is touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['crystals', 'textile', 'packaging', 'furniture'] as $code) {
            DB::table('price_list_sections')
                ->where('code', $code)
                ->update(['route_name' => "/erp/product-price-list?section={$code}"]);
        }
    }

    public function down(): void
    {
        $was = [
            'crystals' => '/admin/crystal-price-list',
            'textile' => '/admin/catalogue-price-list?section=textile',
            'packaging' => '/admin/catalogue-price-list?section=packaging',
            'furniture' => '/admin/catalogue-price-list?section=furniture',
        ];

        foreach ($was as $code => $route) {
            DB::table('price_list_sections')->where('code', $code)->update(['route_name' => $route]);
        }
    }
};
