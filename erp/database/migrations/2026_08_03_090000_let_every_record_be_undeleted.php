<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Deleting stops being final.
 *
 * Only deals could be deleted, and only deals kept what was deleted. Everything
 * else was permanent the moment it was typed: a tracking number entered wrong, a
 * supplier created twice, a payment recorded that never arrived — none of them
 * could be taken back, and the only way to blunt a wrong payment was to edit the
 * amount, which rewrites history rather than correcting it.
 *
 * The fix is not a delete button on every screen. It is that deleting is
 * *reversible* on every screen: the fear of the button is that it is final, and
 * a row that can be restored is one you can tidy without thinking twice.
 *
 * `deleted_at` on every table behind a screen. What each one then means when it
 * goes — whose balance moves, which deals lose their supplier — is a question
 * for the confirmation dialog, not for the schema.
 */
return new class extends Migration
{
    /**
     * Every table with a screen of its own.
     *
     * `deals` is absent because it has soft-deleted since it was written, which
     * is where this pattern comes from.
     */
    private const TABLES = [
        // Who you deal with.
        'customers',
        'suppliers',

        // What you sell.
        'products',
        'product_categories',

        // The work.
        'consignments',
        'deal_purchases',

        // The money. These are the ones whose deletion moves a balance, so they
        // are the ones that most need to be reversible.
        'customer_invoices',
        'customer_payments',
        'supplier_payments',
        'expenses',

        // Settings.
        'collection_points',
        'currencies',
        'exchange_rates',
        'users',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, fn (Blueprint $t) => $t->softDeletes());
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, fn (Blueprint $t) => $t->dropSoftDeletes());
        }
    }
};
