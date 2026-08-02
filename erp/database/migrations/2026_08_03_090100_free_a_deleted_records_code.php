<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * A deleted record gives up its code.
 *
 * Now that everything can be deleted, a plain unique index has an awkward
 * consequence: delete the supplier you created twice and "SUP-A" stays taken by
 * a row nobody can see, so the form refuses a code that appears to belong to
 * nothing. The person typing has no way to find out why.
 *
 * A partial unique index — unique only among the rows that are still there —
 * says what was actually meant all along: two live suppliers may not share a
 * code, and a deleted one is not competing for it.
 *
 * The same statement serves both databases. SQLite has had partial indexes
 * since 3.8 and PostgreSQL far longer, and the syntax is identical, so this
 * needs no branching between the test database and the server's.
 *
 * The trade is small and worth naming: restoring a record whose code has since
 * been taken now fails. The restore actions catch that and say so.
 */
return new class extends Migration
{
    /** table => the column that has to be unique among living rows */
    private const UNIQUE_COLUMNS = [
        'suppliers' => 'code',
        'customers' => 'code',
        'products' => 'sku',
        'product_categories' => 'slug',
        'consignments' => 'tracking_number',
        'currencies' => 'code',
        'users' => 'email',
    ];

    public function up(): void
    {
        foreach (self::UNIQUE_COLUMNS as $table => $column) {
            if (! Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            $index = "{$table}_{$column}_unique";

            DB::statement("DROP INDEX IF EXISTS {$index}");
            DB::statement(
                "CREATE UNIQUE INDEX {$index} ON {$table} ({$column}) WHERE deleted_at IS NULL"
            );
        }
    }

    public function down(): void
    {
        foreach (self::UNIQUE_COLUMNS as $table => $column) {
            $index = "{$table}_{$column}_unique";

            DB::statement("DROP INDEX IF EXISTS {$index}");
            DB::statement("CREATE UNIQUE INDEX {$index} ON {$table} ({$column})");
        }
    }
};
