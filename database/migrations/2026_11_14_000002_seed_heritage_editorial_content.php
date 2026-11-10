<?php

use Database\Seeders\HeritageContentSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * The researched pages of the 21 landmarks: their openings, sections and
 * credited photographs in three languages (database/seeders/heritage/), set
 * on the landmarks the collection was first seeded with. Runs on deploy so
 * the live site gets them without a hand to run a seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new HeritageContentSeeder)->run();
    }

    public function down(): void
    {
        // the texts stay; nothing to take back
    }
};
