<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * A landmark's earlier web addresses (App\Models\Landmark::$old_slugs), so a renamed page's
 * old links lead on to it. And every address in lower case, the only kind the page answers:
 * Göbekli Tepe was renamed "Gire-Mirazan" in the admin on the live site, and both its old
 * and its new address stopped working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landmarks', function (Blueprint $table) {
            $table->json('old_slugs')->nullable()->after('slug');
        });

        foreach (DB::table('landmarks')->get(['id', 'slug']) as $l) {
            $slug = Str::slug((string) $l->slug);
            if ($slug !== $l->slug && $slug !== '' && ! DB::table('landmarks')->where('slug', $slug)->exists()) {
                DB::table('landmarks')->where('id', $l->id)->update(['slug' => $slug]);
            }
        }

        // the one renamed before addresses were remembered
        if (! DB::table('landmarks')->where('slug', 'gobekli-tepe')->exists()) {
            DB::table('landmarks')->where('slug', 'gire-mirazan')->whereNull('old_slugs')
                ->update(['old_slugs' => json_encode(['gobekli-tepe'])]);
        }
    }

    public function down(): void
    {
        Schema::table('landmarks', function (Blueprint $table) {
            $table->dropColumn('old_slugs');
        });
    }
};
