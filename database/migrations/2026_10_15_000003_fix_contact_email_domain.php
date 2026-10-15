<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The studio's e-mail is on arammizuri.com. An earlier migration
 * (2026_07_16_000001) wrote it as arammizzuri.com — a domain that does not
 * exist, so mail sent there bounced. Any setting still carrying the
 * misspelling is corrected; one changed in the admin since is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('site_settings')->where('value', 'like', '%arammizzuri.com%')->get(['id', 'value'])
            ->each(fn ($row) => DB::table('site_settings')->where('id', $row->id)
                ->update(['value' => str_ireplace('arammizzuri.com', 'arammizuri.com', $row->value)]));

        SiteSetting::flushCache();
    }

    public function down(): void
    {
        // a domain that does not exist is not put back
    }
};
