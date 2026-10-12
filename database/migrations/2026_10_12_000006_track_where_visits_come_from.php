<?php

use App\Support\TrafficSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Where visitors come from, and what they do.
 *
 * - page_views.source: Google, Instagram, WhatsApp… for each page load
 *   (App\Support\TrafficSource). Visits already recorded are sorted by the
 *   referrer they kept; their user agent was never stored, so in-app visits
 *   from before today can only be told apart when the referrer says so.
 * - contact_messages.source: where the person who wrote first came from.
 * - site_events: what happens without a page load — a project opened in
 *   the homepage overlay, a WhatsApp button pressed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            $table->string('source', 20)->nullable()->after('referrer');
            $table->index(['source', 'created_at']);
        });

        $ownHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        DB::table('page_views')->select('referrer')->distinct()->orderBy('referrer')
            ->pluck('referrer')
            ->each(function ($referrer) use ($ownHost) {
                DB::table('page_views')
                    ->where(fn ($q) => $referrer === null ? $q->whereNull('referrer') : $q->where('referrer', $referrer))
                    ->update(['source' => TrafficSource::classify($referrer, null, null, $ownHost)]);
            });

        Schema::table('contact_messages', function (Blueprint $table) {
            $table->string('source', 20)->nullable()->after('lang');
        });

        Schema::create('site_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);                 // project_open | whatsapp_click
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('placement', 30)->nullable(); // which WhatsApp button
            $table->string('source', 20)->nullable();    // where the visitor first came from
            $table->string('visitor_hash', 64);
            $table->timestamp('created_at')->nullable();

            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_events');
        Schema::table('contact_messages', fn (Blueprint $table) => $table->dropColumn('source'));
        Schema::table('page_views', function (Blueprint $table) {
            $table->dropIndex(['source', 'created_at']);
        });
        Schema::table('page_views', fn (Blueprint $table) => $table->dropColumn('source'));
    }
};
