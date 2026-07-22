<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Project categories and statuses, now database-managed so they can be added /
 * removed / reordered in the admin instead of being hard-coded. Projects store
 * the category *key* and the status *name*; these tables provide the labels,
 * bilingual filter text and badge styling. Seeded from the previous constants
 * so existing projects and the public site render identically.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();     // slug stored on projects.category
            $table->string('name');              // English label
            $table->string('name_ku')->nullable(); // Kurdish label (public filter)
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();    // full text stored on projects.status
            $table->string('badge')->nullable(); // short label on the grid card
            $table->string('tone')->default('done'); // colour: done | build | concept
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();

        // Order mirrors the public filter row (residential first).
        $categories = [
            ['key' => 'residential', 'name' => 'Residential',     'name_ku' => 'نیشتەجێبوون'],
            ['key' => 'commercial',  'name' => 'Commercial',      'name_ku' => 'بازرگانی'],
            ['key' => 'hospitality', 'name' => 'Hospitality',     'name_ku' => 'میوانپەروەری'],
            ['key' => 'mixed-use',   'name' => 'Mixed-Use',       'name_ku' => 'تێکەڵ'],
            ['key' => 'cultural',    'name' => 'Cultural',        'name_ku' => 'کولتووری'],
            ['key' => 'urban',       'name' => 'Master Planning', 'name_ku' => 'شارستانی'],
        ];
        foreach ($categories as $i => $c) {
            DB::table('categories')->insert($c + ['sort_order' => $i, 'created_at' => $now, 'updated_at' => $now]);
        }

        $statuses = [
            ['name' => 'Completed',          'badge' => 'Completed',          'tone' => 'done'],
            ['name' => 'Under Construction', 'badge' => 'Under Construction', 'tone' => 'build'],
            ['name' => 'Concept / Planning', 'badge' => 'Concept',            'tone' => 'concept'],
        ];
        foreach ($statuses as $i => $s) {
            DB::table('statuses')->insert($s + ['sort_order' => $i, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('statuses');
        Schema::dropIfExists('categories');
    }
};
