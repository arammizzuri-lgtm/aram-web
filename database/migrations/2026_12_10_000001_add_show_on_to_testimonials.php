<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which language versions of the site show a quote — chosen in the admin
 * (["en","ku","ar"], any of them). Left empty, a quote shows in the
 * languages it is written in, as before (App\Models\Testimonial::showsIn).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->json('show_on')->nullable()->after('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropColumn('show_on');
        });
    }
};
