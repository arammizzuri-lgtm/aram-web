<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The journal's essays written in the admin (App\Models\Essay): a title, a
 * line and a cover photograph for the card, and a page composed of the
 * heritage pages' sections, in English, Kurdish and Arabic. The essays with
 * drawings of their own stay in code (App\Support\Articles).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('essays', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->date('date');
            $table->string('title_en');
            $table->string('title_ku')->nullable();
            $table->string('title_ar')->nullable();
            $table->text('summary_en')->nullable();
            $table->text('summary_ku')->nullable();
            $table->text('summary_ar')->nullable();
            $table->string('image')->nullable();
            $table->string('image_credit')->nullable();
            $table->string('image_source')->nullable();
            $table->json('sections')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('essays');
    }
};
