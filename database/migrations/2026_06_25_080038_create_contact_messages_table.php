<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Contact messages table
|--------------------------------------------------------------------------
| Stores submissions from the public contact form so they can be read in the
| admin "Messages" inbox. Previously the form only showed a fake success note.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('project_type')->nullable();
            $table->text('message');
            $table->boolean('is_read')->default(false)->index();
            $table->ipAddress('ip_address')->nullable();    // for spam diagnostics
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
