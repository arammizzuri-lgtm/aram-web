<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sketches visitors draw for the studio (the blank sheet on /projects, "Add
 * a sketch" on the enquiry form): kept on the private disk, opened by an
 * address no one can guess, and tied to the enquiry sent with them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sketches', function (Blueprint $table) {
            $table->id();
            $table->string('token', 40)->unique();
            $table->string('path');
            $table->foreignId('contact_message_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sketches');
    }
};
