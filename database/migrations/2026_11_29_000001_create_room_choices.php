<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finish choices, in a client room: the studio sets out options for one
 * decision — a worktop, tiles, a colour — each with a picture, what it adds
 * to the price and its supplier; the client picks one, final (only the
 * studio can reopen it), and an extra cost becomes a payment stage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_choices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_room_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->text('note')->nullable();               // the studio's note
            $table->string('status', 12)->default('pending');   // pending | chosen
            $table->unsignedBigInteger('chosen_option_id')->nullable();
            $table->text('client_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('decided_ip', 45)->nullable();
            $table->foreignId('room_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('seen_at')->nullable();       // the studio has read the pick
            $table->timestamps();
        });

        Schema::create('room_choice_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_choice_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->text('details')->nullable();
            $table->string('image_path')->nullable();       // private disk
            $table->decimal('extra_cost', 16, 2)->default(0);
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_choice_options');
        Schema::dropIfExists('room_choices');
    }
};
