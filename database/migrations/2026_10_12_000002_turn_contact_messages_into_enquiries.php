<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The contact form becomes an enquiry form, and its messages become leads.
 *
 * - A phone number, because most of this studio's clients would rather be
 *   called or messaged on WhatsApp than e-mailed — so e-mail is no longer
 *   required, as long as one of the two is given.
 * - What the project is: city, plot area, budget band, timeline.
 * - Where it came from: the project page (or overlay) the visitor was on,
 *   the page address, and the language they wrote in.
 * - Where it stands: a status from new to won/lost, and private notes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('phone', 40)->nullable()->after('email');
            $table->string('city', 80)->nullable()->after('project_type');
            $table->unsignedInteger('plot_area')->nullable()->after('city');
            $table->string('budget', 20)->nullable()->after('plot_area');
            $table->string('timeline', 20)->nullable()->after('budget');
            $table->foreignId('project_id')->nullable()->after('timeline')
                ->constrained()->nullOnDelete();
            $table->string('page', 255)->nullable()->after('project_id');
            $table->string('lang', 2)->nullable()->after('page');
            $table->string('status', 20)->default('new')->index()->after('lang');
            $table->text('notes')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            // SQLite will not drop an indexed column while its index stands
            $table->dropIndex(['status']);
        });
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
            $table->dropColumn(['phone', 'city', 'plot_area', 'budget', 'timeline', 'page', 'lang', 'status', 'notes']);
        });
    }
};
