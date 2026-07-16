<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;

/**
 * Carry the real contact details into every environment's database.
 *
 * The contact section was rebuilt around one email + two phone numbers
 * (seed data was updated too, but seeds don't run on deploy — this
 * migration does). SiteSetting::put() also busts the settings cache.
 */
return new class extends Migration
{
    public function up(): void
    {
        SiteSetting::put('contact_newwork_label', 'Email', 'contact');
        SiteSetting::put('contact_email_new', 'Info@arammizzuri.com', 'contact');
        SiteSetting::put('contact_phone_label', 'Phone 01', 'contact');
        SiteSetting::put('contact_phone', '+964 (0) 782 445 4414', 'contact');
        SiteSetting::put('contact_phone2_label', 'Phone 02', 'contact');
        SiteSetting::put('contact_phone2', '+964 (0) 750 408 6367', 'contact');

        // obsolete rows from the old two-email layout
        SiteSetting::query()
            ->whereIn('key', ['contact_general_label', 'contact_email_general'])
            ->get()
            ->each->delete();   // delete via model so the cache flushes
    }

    public function down(): void
    {
        // content data migration — nothing sensible to restore
    }
};
