<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A quote can carry the client's own voice (a WhatsApp voice note) and a
 * pencil portrait drawn from their photo (App\Support\PortraitSketch). The
 * photo itself stays private: only the drawing is ever published, and only
 * once the client has agreed (media_consent).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->string('voice')->nullable()->after('project_id');
            $table->string('portrait')->nullable()->after('voice');
            $table->string('portrait_sketch')->nullable()->after('portrait');
            $table->string('sketch_detail', 16)->default('balanced')->after('portrait_sketch');
            $table->boolean('media_consent')->default(false)->after('sketch_detail');
        });
    }

    public function down(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropColumn(['voice', 'portrait', 'portrait_sketch', 'sketch_detail', 'media_consent']);
        });
    }
};
