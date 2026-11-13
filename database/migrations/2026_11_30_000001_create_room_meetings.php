<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meeting notes, in a client room: the date, who came and what was decided
 * (numbered), with a photo or PDF if there is one. The client answers that
 * they agree, or that something is not right and what.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_room_id')->constrained()->cascadeOnDelete();
            $table->date('met_on');
            $table->string('title', 160)->nullable();
            $table->string('attendees')->nullable();
            $table->text('decisions');                      // one per line, shown numbered
            $table->string('file_path')->nullable();        // a photo or PDF, private disk
            $table->string('file_name')->nullable();
            $table->string('status', 12)->default('pending');   // pending | agreed | objected
            $table->text('client_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('decided_ip', 45)->nullable();
            $table->timestamp('seen_at')->nullable();       // the studio has read the answer
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_meetings');
    }
};
