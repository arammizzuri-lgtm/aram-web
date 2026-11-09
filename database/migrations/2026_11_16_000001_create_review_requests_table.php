<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Review links: a private address the studio sends a client after handover,
 * where they write their words about the studio — and may record their voice
 * and add a photo for the pencil portrait. What they send arrives as a
 * hidden client quote (from_client), waiting for the studio's approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_requests', function (Blueprint $table) {
            $table->id();
            $table->string('token', 40)->unique();
            $table->string('name', 120);
            $table->string('lang', 2)->default('en');
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('testimonial_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::table('testimonials', function (Blueprint $table) {
            $table->boolean('from_client')->default(false)->index();
            $table->timestamp('submitted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_requests');
        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropColumn(['from_client', 'submitted_at']);
        });
    }
};
