<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A client room's documents shelf: contract, permits, invoices and other
 * papers, from the studio or uploaded by the client. Some must be confirmed
 * as read; some expire, and the studio is reminded 30 days before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_room_id')->constrained()->cascadeOnDelete();
            $table->string('group', 12)->default('other');      // contract | permits | invoices | other
            $table->string('title', 160);
            $table->string('file_path');                        // private disk
            $table->string('file_name')->nullable();
            $table->boolean('from_client')->default(false);
            $table->boolean('needs_reading')->default(false);   // the client confirms "I have read it"
            $table->timestamp('read_at')->nullable();
            $table->string('read_ip', 45)->nullable();
            $table->date('expires_on')->nullable();
            $table->timestamp('reminded_at')->nullable();       // the studio was e-mailed it is expiring
            $table->timestamp('first_opened_at')->nullable();   // the client opened it
            $table->timestamp('seen_at')->nullable();           // the studio has seen the client's upload or reading
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_documents');
    }
};
