<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The studio's build journal (/journal): a construction update can also be
 * published as a post of its own — with its own address — so the work on
 * site is followed in public, step by step. Off by default; a private update
 * is never in the journal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_updates', function (Blueprint $table) {
            $table->boolean('in_journal')->default(false)->after('is_private');
            $table->string('slug')->nullable()->unique()->after('in_journal');
        });
    }

    public function down(): void
    {
        Schema::table('project_updates', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['in_journal', 'slug']);
        });
    }
};
