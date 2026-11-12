<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Project presentations (admin → Clients → Project presentations): each one a
 * document the editor keeps as JSON, the files uploaded for it, and what is
 * reused between them — saved templates, saved page layouts, the branding.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presentations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // copied out of the document on every save, for the list's search
            $table->string('project_name')->nullable()->index();
            $table->string('client_name')->nullable()->index();
            $table->unsignedSmallInteger('page_count')->default(0);
            $table->longText('doc');   // raw JSON, exactly as the editor sent it
            $table->timestamps();
        });

        Schema::create('presentation_assets', function (Blueprint $table) {
            $table->id();
            // null: the studio's own (its logo, partners' logos)
            $table->foreignId('presentation_id')->nullable()->constrained()->cascadeOnDelete();
            // the file is named by its SHA-256, so a copy of a presentation shares it
            $table->string('hash', 64)->index();
            $table->string('kind', 10);   // image | pdf
            $table->string('ext', 5);
            $table->string('name');
            $table->unsignedBigInteger('size');
            $table->json('meta')->nullable();   // image: w, h, orientation — pdf: pages [{w, h}]
            $table->timestamps();
            $table->unique(['presentation_id', 'hash']);
        });

        Schema::create('presentation_presets', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10)->index();   // theme | layout | brand
            $table->string('name');
            $table->longText('data');   // raw JSON
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentation_presets');
        Schema::dropIfExists('presentation_assets');
        Schema::dropIfExists('presentations');
    }
};
