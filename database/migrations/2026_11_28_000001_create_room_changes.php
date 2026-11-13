<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Changes with a price, in a client room: the studio offers one (or prices
 * one the client asked for) with what it costs and the days it adds; the
 * client accepts it — and it becomes a payment stage of its own — or
 * declines it, saying why.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_room_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->text('details')->nullable();        // the change, in the words of whoever proposed it
            $table->text('note')->nullable();           // the studio's note with its price
            $table->boolean('asked_by_client')->default(false);
            $table->decimal('price', 16, 2)->nullable(); // null: the client asked, not priced yet
            $table->unsignedSmallInteger('extra_days')->nullable();
            $table->string('file_path')->nullable();    // a picture or drawing of it, private disk
            $table->string('file_name')->nullable();
            $table->string('status', 12)->default('priced');   // asked | priced | accepted | declined
            $table->text('decline_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('decided_ip', 45)->nullable();
            $table->foreignId('room_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('seen_at')->nullable();   // the studio has read what the client did
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_changes');
    }
};
