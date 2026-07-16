<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;

/** Practice founding year corrected: Est. 2009 → Est. 2022. */
return new class extends Migration
{
    public function up(): void
    {
        SiteSetting::put('hero_eyebrow', 'Erbil · Kurdistan · Est. 2022', 'hero');
    }

    public function down(): void
    {
        SiteSetting::put('hero_eyebrow', 'Erbil · Kurdistan · Est. 2009', 'hero');
    }
};
