<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The studio's own tasks (admin → Tasks): a board and a timeline. A task can
 * belong to a project or a client room, repeat, carry a checklist, and come
 * from the website by itself (source: a visit request, a change asked for…).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->text('notes')->nullable();
            $table->string('status', 8)->default('todo');      // todo | doing | done
            $table->string('priority', 8)->default('normal');  // high | normal | low
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_room_id')->nullable()->constrained()->nullOnDelete();
            $table->date('starts_on')->nullable();
            $table->dateTime('due_at');
            $table->dateTime('done_at')->nullable();
            $table->string('repeat', 8)->nullable();           // daily | weekly | monthly
            $table->json('checklist')->nullable();             // [{text, done}]
            $table->nullableMorphs('source');                  // what on the website made it
            $table->timestamps();
            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
