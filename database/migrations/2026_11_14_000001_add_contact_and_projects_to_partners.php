<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partners & suppliers, richer: how to reach them (phone, WhatsApp, address
 * and a pin), the one-colour logo the logo wall shows (made on save, like the
 * clients'), and the studio projects done together.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->string('logo_mono')->nullable()->after('logo');
            $table->string('phone', 40)->nullable()->after('website');
            $table->boolean('whatsapp')->default(false)->after('phone');
            $table->string('address_en')->nullable()->after('whatsapp');
            $table->string('address_ku')->nullable()->after('address_en');
            $table->string('address_ar')->nullable()->after('address_ku');
            $table->decimal('lat', 10, 7)->nullable()->after('address_ar');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
        });

        Schema::create('partner_project', function (Blueprint $table) {
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->primary(['partner_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_project');
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn(['logo_mono', 'phone', 'whatsapp', 'address_en', 'address_ku', 'address_ar', 'lat', 'lng']);
        });
    }
};
