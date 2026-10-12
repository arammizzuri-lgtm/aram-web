<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The built-in line-art mark for "Erbil Int'l Airport" closed its <polygon>
 * with a path command — points="3,21 33,7 23,30 16,23 Z" — which is not valid
 * in a polygon (it closes itself). Browsers rejected the attribute and drew
 * nothing, logging an error on every page load. Strip any such letters from
 * polygon/polyline points in every stored mark.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('clients')->whereNotNull('mark')->get(['id', 'mark']) as $row) {
            $fixed = preg_replace_callback(
                '/(<poly(?:gon|line)\b[^>]*\bpoints=")([^"]*)(")/i',
                fn ($m) => $m[1].trim(preg_replace('/\s+/', ' ', preg_replace('/[A-Za-z]/', '', $m[2]))).$m[3],
                $row->mark,
            );
            if ($fixed !== $row->mark) {
                DB::table('clients')->where('id', $row->id)->update(['mark' => $fixed]);
            }
        }
    }

    public function down(): void
    {
        // Nothing to undo: the removed letters were never valid.
    }
};
