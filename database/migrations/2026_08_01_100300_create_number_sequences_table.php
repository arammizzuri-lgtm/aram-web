<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('document_type');
            $table->unsignedSmallInteger('year');
            $table->string('prefix', 12);
            $table->string('format')->default('{prefix}-{year}-{number}');
            $table->unsignedTinyInteger('padding')->default(4);
            $table->unsignedInteger('next_number')->default(1);
            $table->timestamps();

            // One counter per document type per year; the row is locked while allocating,
            // which is what keeps numbers gapless under concurrent requests.
            $table->unique(['document_type', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
