<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Site settings table (key / value CMS store)
|--------------------------------------------------------------------------
| Holds every editable text/image block on the public site, e.g.
| "hero_title_en", "hero_title_ku", "contact_email_new". Grouped by section
| so the admin "Site Content" screen can render them under tabs.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();                // machine key, e.g. hero_title_en
            $table->text('value')->nullable();              // the editable value
            $table->string('group')->default('general')->index(); // hero / about / contact ...
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
