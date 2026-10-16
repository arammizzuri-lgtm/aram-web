<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Three ways into a project's photographs and its thinking:
 *   callouts     numbered notes on a photograph, as a drawing's callouts —
 *                [{img, x, y, en, ku}], placed by clicking in the admin
 *   photo_rooms  the room each photograph shows — {img: room} — so every
 *                kitchen, every façade can be seen together (/photos)
 *   voice_en/ku  the architect's own voice note on the project
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->json('callouts')->nullable();
            $table->json('photo_rooms')->nullable();
            $table->string('voice_en')->nullable();
            $table->string('voice_ku')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['callouts', 'photo_rooms', 'voice_en', 'voice_ku']);
        });
    }
};
