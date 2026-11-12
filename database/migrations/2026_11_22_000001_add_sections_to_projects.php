<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * More to tell on a project's page: chapters of text, a statement, figures,
 * a drawing — and where the photographs fall between them
 * (App\Models\Project::BLOCKS). Empty for every project until the admin adds some.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('projects', 'sections')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->json('sections')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('sections');
        });
    }
};
