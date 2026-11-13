<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A client room's timeline: its stages (Concept, Permit, …), each with the
 * dates planned and the dates it actually started and finished, and how far
 * along it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_room_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->date('planned_start')->nullable();
            $table->date('planned_end')->nullable();
            $table->date('started_on')->nullable();
            $table->date('finished_on')->nullable();
            $table->unsignedTinyInteger('percent')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_stages');
    }
};
