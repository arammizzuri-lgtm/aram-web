<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->default('en')->after('phone');
            $table->string('theme_preference')->default('system')->after('locale');
            $table->string('avatar_path')->nullable()->after('theme_preference');
            $table->timestamp('last_login_at')->nullable()->after('avatar_path');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'locale', 'theme_preference', 'avatar_path', 'last_login_at', 'last_login_ip',
            ]);
        });
    }
};
