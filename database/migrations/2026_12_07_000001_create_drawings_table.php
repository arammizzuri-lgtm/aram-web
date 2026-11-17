<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drawings traced in the admin (App\Models\Drawing): a new plan traced over a
 * survey scan, or a correction to the walls of one of the essays' drawings
 * (base = its key, e.g. h318). The scan stays private on the local disk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drawings', function (Blueprint $table) {
            $table->id();
            $table->string('title_en');
            $table->string('title_ku')->nullable();
            $table->string('title_ar')->nullable();
            $table->string('base')->nullable()->index();
            $table->string('scan')->nullable();
            $table->json('data')->nullable();
            $table->boolean('is_live')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drawings');
    }
};
