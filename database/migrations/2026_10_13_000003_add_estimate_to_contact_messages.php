<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An enquiry sent from the cost estimator keeps the estimate it was sent
 * with: every answer the visitor gave and the ranges it came to, worked out
 * again on the server at the rates of the day (App\Support\CostEstimate).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->json('estimate')->nullable()->after('saved_projects');
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropColumn('estimate');
        });
    }
};
