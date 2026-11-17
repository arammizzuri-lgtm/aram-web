<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Every landmark's address in lower case, again: the step before compared on MySQL without
 * regard to case, so "Gire-Mirazan" found itself as "gire-mirazan" taken and was left as it was.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('landmarks')->get(['id', 'slug']) as $l) {
            $slug = Str::slug((string) $l->slug);
            if ($slug !== $l->slug && $slug !== '' && ! DB::table('landmarks')->where('slug', $slug)->where('id', '!=', $l->id)->exists()) {
                DB::table('landmarks')->where('id', $l->id)->update(['slug' => $slug]);
            }
        }
    }

    public function down(): void {}
};
