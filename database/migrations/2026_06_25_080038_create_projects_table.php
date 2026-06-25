<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Projects table
|--------------------------------------------------------------------------
| One row per architecture project shown on the public site. Mirrors the
| fields that used to live in the hard-coded PROJECT_DATA array in script.js.
| JSON columns (materials / related / imgs) hold the list values.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('num')->nullable();              // display number, e.g. "01"
            $table->string('name');                         // project title
            $table->string('category')->index();            // cultural / hospitality / residential ...
            $table->string('status')->nullable();           // Completed / Under Construction / Concept / Planning
            $table->string('size')->default('default');     // bento layout: default | large | wide
            $table->string('area')->nullable();             // e.g. "8,400 m²"
            $table->string('typology')->nullable();         // e.g. "Cultural / Civic"
            $table->string('location')->nullable();         // e.g. "Erbil, Kurdistan Region"
            $table->string('year')->nullable();             // e.g. "2022 – 2024"
            $table->text('desc')->nullable();               // main description paragraph
            $table->text('narrative')->nullable();          // architect's narrative quote
            $table->json('materials')->nullable();          // ["Limestone", "Concrete", ...]
            $table->json('related')->nullable();            // [indexes/ids of related projects]
            $table->json('imgs')->nullable();               // ["https://...", "projects/x.jpg", ...]
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_published')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
