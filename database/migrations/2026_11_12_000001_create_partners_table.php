<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The studio's partners and suppliers (/partners): the brands and firms the
 * work is built with — stone, furniture, lighting, contractors — each with a
 * line about what they bring, a logo and a link to their own website.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category', 40)->default('other')->index();
            $table->string('description_en')->nullable();
            $table->string('description_ku')->nullable();
            $table->string('description_ar')->nullable();
            $table->string('logo')->nullable();
            $table->string('website')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
