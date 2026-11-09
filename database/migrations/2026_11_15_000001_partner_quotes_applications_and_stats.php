<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partners, part three:
 *  - a quote from the partner about working with the studio (three
 *    languages, who said it and their role) — shown only when written;
 *  - applications from the "Become a partner" form: they arrive as hidden
 *    partners marked as applications, with the contact person, email and
 *    message the studio needs (never shown on the website);
 *  - partner_events: anonymous counts of what visitors do with a partner —
 *    open it, call, WhatsApp, visit the website, ask for directions, or
 *    arrive from the partner's own badge. A daily visitor hash, as for
 *    page views; never who they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->text('quote_en')->nullable();
            $table->text('quote_ku')->nullable();
            $table->text('quote_ar')->nullable();
            $table->string('quote_by', 120)->nullable();
            $table->string('quote_role_en', 160)->nullable();
            $table->string('quote_role_ku', 160)->nullable();
            $table->string('quote_role_ar', 160)->nullable();
            $table->boolean('is_application')->default(false)->index();
            $table->string('contact_person', 120)->nullable();
            $table->string('email', 190)->nullable();
            $table->text('message')->nullable();
            $table->string('lang', 2)->nullable();
            $table->timestamp('applied_at')->nullable();
        });

        Schema::create('partner_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('visitor_hash', 64)->nullable();
            $table->timestamp('created_at')->index();
            $table->index(['partner_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_events');
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn(['quote_en', 'quote_ku', 'quote_ar', 'quote_by', 'quote_role_en', 'quote_role_ku', 'quote_role_ar',
                'is_application', 'contact_person', 'email', 'message', 'lang', 'applied_at']);
        });
    }
};
