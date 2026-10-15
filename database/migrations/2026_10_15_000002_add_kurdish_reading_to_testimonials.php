<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A quote written in Kurdish can be read aloud from a recording made once,
 * when it is saved (App\Support\ReadAloud): visitors' phones rarely have a
 * Kurdish voice of their own. reading_ku_for remembers what the recording
 * was made from — the words, the voice and the speed — so it is only made
 * again when one of them changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->string('reading_ku')->nullable()->after('media_consent');
            $table->string('reading_ku_for', 100)->nullable()->after('reading_ku');
        });
    }

    public function down(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropColumn(['reading_ku', 'reading_ku_for']);
        });
    }
};
