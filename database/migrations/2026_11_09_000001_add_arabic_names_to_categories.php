<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Arabic names for project categories — the site's third language (/ar).
 *
 * The categories are the studio's own, edited in the admin, so the Arabic is
 * filled in by matching the English name a category is known by. One whose
 * name is not in the list is left blank and shows in English on the Arabic
 * pages until an Arabic name is written for it (Projects → Categories).
 */
return new class extends Migration
{
    private const ARABIC = [
        'residential' => 'سكني',
        'commercial' => 'تجاري',
        'hospitality' => 'ضيافة',
        'mixed-use' => 'متعدد الاستخدامات',
        'mixed use' => 'متعدد الاستخدامات',
        'cultural' => 'ثقافي',
        'master planning' => 'تخطيط عمراني',
        'urban planning' => 'تخطيط عمراني',
        'urban design' => 'تصميم حضري',
        'interior design' => 'تصميم داخلي',
        'interior' => 'تصميم داخلي',
        'exterior design' => 'تصميم خارجي',
        'exterior' => 'تصميم خارجي',
        'landscape' => 'تنسيق المواقع',
        'landscape design' => 'تنسيق المواقع',
        'educational' => 'تعليمي',
        'education' => 'تعليمي',
        'healthcare' => 'صحي',
        'industrial' => 'صناعي',
        'office' => 'مكاتب',
        'offices' => 'مكاتب',
        'religious' => 'ديني',
        'public' => 'عام',
        'civic' => 'مدني',
        'restoration' => 'ترميم',
        'renovation' => 'تجديد',
        'retail' => 'تجزئة',
        'restaurant' => 'مطاعم',
        'restaurants' => 'مطاعم',
        'villa' => 'فلل',
        'villas' => 'فلل',
    ];

    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name_ku');
        });

        foreach (DB::table('categories')->get(['id', 'name']) as $row) {
            $arabic = self::ARABIC[mb_strtolower(trim((string) $row->name))] ?? null;
            if ($arabic) {
                DB::table('categories')->where('id', $row->id)->update(['name_ar' => $arabic]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('name_ar');
        });
    }
};
