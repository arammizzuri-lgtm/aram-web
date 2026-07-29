<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give every project a display number, running 01, 02, 03… in the order the
 * projects were uploaded.
 *
 * Projects added through the admin had no number at all — the field was left
 * for someone to fill in by hand and nothing assigned one — so the public site
 * showed a blank where the number should be. From now on the model assigns the
 * next number on create; this brings the ones already uploaded into line.
 *
 * Every project is renumbered rather than only the blank ones, because a
 * sequence with holes punched in it is not "in order". Ordering is by `id`,
 * which is upload order, and deliberately not by `sort_order` — rearranging
 * the public grid should not renumber the projects.
 *
 * The writes go through the query builder on purpose: going via the model
 * would re-run the save hooks and regenerate every thumbnail.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ids = DB::table('projects')->orderBy('id')->pluck('id');

        DB::transaction(function () use ($ids) {
            foreach ($ids as $position => $id) {
                DB::table('projects')->where('id', $id)->update([
                    'num' => str_pad((string) ($position + 1), 2, '0', STR_PAD_LEFT),
                ]);
            }
        });
    }

    public function down(): void
    {
        // The numbers this replaced were blank or ad hoc, so there is nothing
        // meaningful to restore.
    }
};
