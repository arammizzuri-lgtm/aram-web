<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('name_zh')->nullable()->after('name');
            $table->string('whatsapp')->nullable();
            $table->string('wechat_id')->nullable();
            $table->char('country', 2)->default('CN');
            $table->string('city')->nullable();
            $table->string('website')->nullable();
            $table->char('default_currency', 3)->default('USD');
            $table->string('default_incoterm')->default('FOB');
            $table->string('port_of_loading')->nullable();
            $table->unsignedSmallInteger('average_lead_time_days')->nullable();
            $table->decimal('deposit_percent', 5, 2)->default(30);
            $table->unsignedTinyInteger('rating')->nullable();
            $table->json('bank_details')->nullable();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->string('name_ku')->nullable()->after('name_ar');
            $table->string('whatsapp')->nullable();
            $table->string('city')->nullable();
            $table->string('area')->nullable();
            $table->foreignId('price_tier_id')->nullable()->constrained()->nullOnDelete();
            // credit_limit already exists on this table.
            $table->char('credit_limit_currency', 3)->default('USD');
            $table->char('default_currency', 3)->default('USD');
            $table->boolean('is_blocked')->default(false);
            $table->string('blocked_reason')->nullable();
            $table->foreignId('sales_rep_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('rating')->nullable();
        });

        foreach (['supplier_contacts' => 'supplier_id', 'customer_contacts' => 'customer_id'] as $tableName => $foreignKey) {
            Schema::create($tableName, function (Blueprint $table) use ($foreignKey) {
                $table->id();
                $table->foreignId($foreignKey)->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('role')->nullable();
                $table->string('phone')->nullable();
                $table->string('whatsapp')->nullable();
                $table->string('wechat_id')->nullable();
                $table->string('email')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_contacts');
        Schema::dropIfExists('supplier_contacts');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['price_tier_id']);
            $table->dropForeign(['sales_rep_id']);
            $table->dropColumn([
                'name_ar', 'name_ku', 'whatsapp', 'city', 'area', 'price_tier_id',
                'credit_limit_currency', 'default_currency',
                'is_blocked', 'blocked_reason', 'sales_rep_id', 'rating',
            ]);
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn([
                'name_zh', 'whatsapp', 'wechat_id', 'country', 'city', 'website',
                'default_currency', 'default_incoterm', 'port_of_loading',
                'average_lead_time_days', 'deposit_percent', 'rating', 'bank_details',
            ]);
        });
    }
};
