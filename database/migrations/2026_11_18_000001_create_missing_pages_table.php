<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Addresses visitors asked for that do not exist (App\Http\Middleware\
 * RecordMissingPage): one row per address, how often, and the page that last
 * linked to it — the admin's "Broken links".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('missing_pages', function (Blueprint $table) {
            $table->id();
            $table->string('path', 255)->unique();
            $table->unsignedInteger('hits')->default(1);
            $table->string('referrer', 500)->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('missing_pages');
    }
};
