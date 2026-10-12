<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Construction progress: dated updates on a project as it is built — a few
 * site photographs and a line or two on where things stand, in English and
 * Kurdish. Shown newest first on the project's page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('title_en')->nullable();
            $table->string('title_ku')->nullable();
            $table->text('note_en')->nullable();
            $table->text('note_ku')->nullable();
            $table->json('images')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_updates');
    }
};
