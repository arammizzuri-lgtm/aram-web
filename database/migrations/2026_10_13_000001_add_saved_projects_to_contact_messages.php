<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The projects a visitor had hearted when they wrote (their "Saved" list, or
 * a list shared with them), as project ids in the order they were saved —
 * so the studio knows their taste before the first call.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->json('saved_projects')->nullable()->after('project_id');
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropColumn('saved_projects');
        });
    }
};
