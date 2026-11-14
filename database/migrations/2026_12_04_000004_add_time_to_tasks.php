<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Time on a task: what it was thought to take, what it took, and a timer while it runs. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedInteger('estimate_minutes')->nullable()->after('labels');
            $table->unsignedInteger('spent_minutes')->default(0)->after('estimate_minutes');
            $table->dateTime('timer_started_at')->nullable()->after('spent_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['estimate_minutes', 'spent_minutes', 'timer_started_at']);
        });
    }
};
