<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clients & Partners, now database-managed (add / remove / reorder in the
 * admin). Each row is a name + bilingual category + a logo: either an uploaded
 * original (auto-converted to a one-colour "mono" mask on save) or, for the
 * seeded originals, a bespoke line-art SVG "mark".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sub_en')->nullable();
            $table->string('sub_ku')->nullable();
            $table->text('mark')->nullable();        // seeded line-art SVG (inner markup)
            $table->string('logo')->nullable();      // uploaded original
            $table->string('logo_mono')->nullable(); // generated one-colour mask
            $table->integer('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        $now = now();
        $rows = [];
        foreach (self::seedClients() as $i => $c) {
            $rows[] = [
                'name' => $c['name'],
                'sub_en' => $c['sub_en'],
                'sub_ku' => $c['sub_ku'],
                'mark' => $c['mark'],
                'sort_order' => $i,
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('clients')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }

    /** The original hardcoded clients, carried over so the section is unchanged. */
    private static function seedClients(): array
    {
        return [
            ['name' => 'Kar Group', 'sub_en' => 'Energy & Infrastructure', 'sub_ku' => 'وزە و ژێرخان',
             'mark' => '<polygon points="18,2 32,10 32,26 18,34 4,26 4,10" stroke="#fff" stroke-width="2" stroke-linejoin="round"/><path d="M13 24 V12 M13 18 L23 12 M13 18 L23 24" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>'],
            ['name' => '404 Cafe', 'sub_en' => 'Café · Erbil', 'sub_ku' => 'کافێ · هەولێر',
             'mark' => '<path d="M8 15 h16 v7 a8 8 0 0 1 -16 0 Z" stroke="#fff" stroke-width="2" stroke-linejoin="round"/><path d="M24 16 h3 a4 4 0 0 1 0 8 h-3" stroke="#fff" stroke-width="2"/><path d="M13 11 c0-2 2-2 2-4 M19 11 c0-2 2-2 2-4" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/>'],
            ['name' => 'KRG', 'sub_en' => "Kurdistan Regional Gov't", 'sub_ku' => 'حکومەتی هەرێمی کوردستان',
             'mark' => '<circle cx="18" cy="18" r="5.5" fill="#fff"/><circle cx="18" cy="18" r="10" stroke="#fff" stroke-width="1.2"/><line x1="18" y1="2" x2="18" y2="6.5" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/><line x1="18" y1="29.5" x2="18" y2="34" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/><line x1="2" y1="18" x2="6.5" y2="18" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/><line x1="29.5" y1="18" x2="34" y2="18" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/><line x1="5.5" y1="5.5" x2="8.8" y2="8.8" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/><line x1="27.2" y1="27.2" x2="30.5" y2="30.5" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/><line x1="30.5" y1="5.5" x2="27.2" y2="8.8" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/><line x1="5.5" y1="30.5" x2="8.8" y2="27.2" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>'],
            ['name' => 'Rightmove', 'sub_en' => 'Real Estate', 'sub_ku' => 'خانووبەرە',
             'mark' => '<polyline points="4,22 13,13 22,22" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 24 v8 h12 v-8" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><line x1="21" y1="19" x2="32" y2="8" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/><polyline points="26,8 32,8 32,14" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>'],
            ['name' => 'Darin Group', 'sub_en' => 'Business Group', 'sub_ku' => 'گروپی بازرگانی',
             'mark' => '<line x1="18" y1="3" x2="18" y2="33" stroke="#fff" stroke-width="2" stroke-linecap="round"/><line x1="3" y1="18" x2="33" y2="18" stroke="#fff" stroke-width="2" stroke-linecap="round"/><line x1="7.2" y1="7.2" x2="28.8" y2="28.8" stroke="#fff" stroke-width="2" stroke-linecap="round"/><line x1="28.8" y1="7.2" x2="7.2" y2="28.8" stroke="#fff" stroke-width="2" stroke-linecap="round"/>'],
            ['name' => "Erbil Int'l Airport", 'sub_en' => 'Aviation', 'sub_ku' => 'فڕۆکەوانی',
             'mark' => '<polygon points="3,21 33,7 23,30 16,23 Z" stroke="#fff" stroke-width="2" stroke-linejoin="round"/><line x1="16" y1="23" x2="33" y2="7" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/><line x1="6" y1="32" x2="28" y2="32" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-dasharray="4 4"/>'],
            ['name' => 'Future City', 'sub_en' => 'Urban Development', 'sub_ku' => 'گەشەپێدانی شاری',
             'mark' => '<line x1="4" y1="32" x2="32" y2="32" stroke="#fff" stroke-width="2" stroke-linecap="round"/><rect x="7" y="18" width="6" height="14" stroke="#fff" stroke-width="1.8"/><rect x="15" y="10" width="6" height="22" stroke="#fff" stroke-width="1.8"/><rect x="23" y="14" width="6" height="18" stroke="#fff" stroke-width="1.8"/><line x1="18" y1="4" x2="18" y2="10" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/>'],
            ['name' => 'So Delicious', 'sub_en' => 'Café & Restaurant', 'sub_ku' => 'کافێ و چێشتخانە',
             'mark' => '<path d="M6 24 a12 11 0 0 1 24 0" stroke="#fff" stroke-width="2" stroke-linecap="round"/><line x1="4" y1="24" x2="32" y2="24" stroke="#fff" stroke-width="2" stroke-linecap="round"/><circle cx="18" cy="10.5" r="1.8" fill="#fff"/><line x1="12" y1="30" x2="24" y2="30" stroke="#fff" stroke-width="2" stroke-linecap="round"/>'],
            ['name' => 'Halat Group', 'sub_en' => 'Development', 'sub_ku' => 'گەشەپێدان',
             'mark' => '<polyline points="6,14 18,6 30,14" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><polyline points="6,22 18,14 30,22" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" opacity=".65"/><polyline points="6,30 18,22 30,30" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" opacity=".35"/>'],
            ['name' => 'GK Architects', 'sub_en' => 'Architecture Studio', 'sub_ku' => 'ستۆدیۆی تەڵارسازی',
             'mark' => '<circle cx="18" cy="7" r="3" stroke="#fff" stroke-width="1.8"/><line x1="16.4" y1="9.8" x2="9" y2="30" stroke="#fff" stroke-width="2" stroke-linecap="round"/><line x1="19.6" y1="9.8" x2="27" y2="30" stroke="#fff" stroke-width="2" stroke-linecap="round"/><path d="M11.5 24.5 a13 13 0 0 0 13 0" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/>'],
        ];
    }
};
