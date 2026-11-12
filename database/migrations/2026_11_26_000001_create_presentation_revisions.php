<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A presentation as it was when a revision was issued (Rev A, Rev B…): its
 * whole document, to open as a copy or go back to. The files it shows are
 * the presentation's own, kept while any revision still shows them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presentation_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentation_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40)->nullable();   // its line in the document's revision history
            $table->string('label', 20);
            $table->string('note', 500)->nullable();
            $table->longText('doc');   // raw JSON, as it was saved
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentation_revisions');
    }
};
