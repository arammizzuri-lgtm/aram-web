<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Careers: the positions the studio is hiring for, and the applications
 * that come in — each with a CV kept on the private disk, where nothing on
 * the public site can reach it; only the admin opens it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_openings', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 191)->unique();
            $table->string('title_en');
            $table->string('title_ku')->nullable();
            $table->string('type', 20)->default('full-time');   // full-time | part-time | internship | contract
            $table->string('location_en')->nullable();
            $table->string('location_ku')->nullable();
            $table->text('summary_en')->nullable();
            $table->text('summary_ku')->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_ku')->nullable();
            $table->boolean('is_open')->default(true);
            $table->date('closes_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_opening_id')->nullable()->constrained()->nullOnDelete();   // null = open application
            $table->string('name');
            $table->string('email');
            $table->string('phone', 40)->nullable();
            $table->string('portfolio_url', 500)->nullable();
            $table->text('message')->nullable();
            $table->string('cv_path');                 // on the private ("local") disk
            $table->string('cv_name');                 // the file name as the applicant had it
            $table->unsignedInteger('cv_size')->default(0);
            $table->string('lang', 2)->nullable();
            $table->string('source', 20)->nullable();  // where they first came from
            $table->string('status', 20)->default('new')->index();
            $table->text('notes')->nullable();
            $table->boolean('is_read')->default(false);
            $table->ipAddress('ip_address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('job_openings');
    }
};
