<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client quotes: what a client said, in English and Kurdish, who said it,
 * and — optionally — which client and which project it is about. A quote
 * tied to a project also appears on that project's page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->text('quote_en')->nullable();
            $table->text('quote_ku')->nullable();
            $table->string('person_name');
            $table->string('person_name_ku')->nullable();
            $table->string('person_role_en')->nullable();
            $table->string('person_role_ku')->nullable();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
