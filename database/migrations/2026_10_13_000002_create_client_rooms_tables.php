<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client rooms: a private page for each client of a project, behind a secret
 * link and a 4-digit PIN, where they follow the work — every progress update
 * (the public ones and those marked private), the drawings to approve or
 * send back, the payment stages — and comment on the updates.
 */
return new class extends Migration
{
    public function up(): void
    {
        // An update can now be for the client's eyes only.
        Schema::table('project_updates', function (Blueprint $table) {
            $table->boolean('is_private')->default(false)->after('images');
        });

        Schema::create('client_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('client_name', 120);
            $table->string('client_phone', 40)->nullable();
            $table->string('token', 64)->unique();      // the secret in the link
            $table->text('pin');                        // encrypted: the studio reads it out
            $table->string('lang', 2)->default('ku');
            $table->string('currency', 3)->default('USD');
            $table->text('note')->nullable();           // a welcome line at the top of the room
            $table->boolean('is_open')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('room_drawings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_room_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->text('note')->nullable();
            $table->string('file_path');                // private disk
            $table->string('file_name')->nullable();
            $table->string('status', 12)->default('pending');   // pending | approved | changes
            $table->text('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('decided_ip', 45)->nullable();
            $table->timestamp('seen_at')->nullable();   // the studio has read the decision
            $table->timestamps();
        });

        Schema::create('room_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_room_id')->constrained()->cascadeOnDelete();
            $table->string('label', 160);
            $table->decimal('amount', 16, 2);
            $table->date('due_on')->nullable();
            $table->date('paid_on')->nullable();
            $table->string('receipt_path')->nullable(); // private disk
            $table->string('receipt_name')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('room_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_update_id')->constrained()->cascadeOnDelete();
            $table->boolean('from_studio')->default(false);
            $table->text('body');
            $table->timestamp('seen_at')->nullable();   // a client's comment the studio has read
            $table->timestamps();

            $table->index(['client_room_id', 'project_update_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_comments');
        Schema::dropIfExists('room_payments');
        Schema::dropIfExists('room_drawings');
        Schema::dropIfExists('client_rooms');
        Schema::table('project_updates', function (Blueprint $table) {
            $table->dropColumn('is_private');
        });
    }
};
