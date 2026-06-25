<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| page_views — lightweight, privacy-friendly visitor analytics
|--------------------------------------------------------------------------
| One row per public page load. We never store the raw IP: visitor_hash is a
| one-way daily hash so we can count unique visitors per day without holding
| personal data.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_hash', 64)->index(); // anonymized daily visitor id
            $table->string('path')->nullable();
            $table->string('referrer')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
