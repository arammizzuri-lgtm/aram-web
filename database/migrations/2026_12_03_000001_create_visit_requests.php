<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visits asked for on the website: a studio visit, a site visit or a phone
 * call, on the day and at the time the visitor would like. The studio
 * confirms it or suggests another, then tells them on WhatsApp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('phone', 40);
            $table->string('kind', 12);                       // studio | site | call
            $table->text('about');
            $table->date('wanted_on');
            $table->string('wanted_time', 5);                 // "16:30"
            $table->string('lang', 2)->default('en');         // the language to answer in
            $table->string('status', 12)->default('waiting'); // waiting | confirmed | suggested
            $table->date('set_on')->nullable();               // the day confirmed or suggested
            $table->string('set_time', 5)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_requests');
    }
};
