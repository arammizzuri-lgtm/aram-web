<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Quotations, and the record of what the customer actually agreed to.
 *
 * The valuable part here is not the document — it is the snapshot. When the
 * customer approves, the lines and photos are copied and frozen. Editing the
 * deal afterwards cannot change what was approved.
 *
 * Without that, "you approved this model" is your word against theirs and the
 * evidence is somewhere in a WhatsApp thread.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->string('number')->unique();

            /*
             * Editing an approved deal supersedes the quotation rather than
             * altering it, so there is always a visible trail of what changed.
             */
            $table->unsignedInteger('version')->default(1);

            // draft → sent → approved | rejected | superseded
            $table->string('status')->default('draft')->index();

            $table->string('currency', 3)->default('IQD');
            $table->decimal('exchange_rate', 19, 6)->nullable();
            $table->decimal('total', 19, 4)->default(0);
            $table->decimal('total_base', 19, 4)->default(0);

            $table->date('quotation_date');
            $table->date('valid_until')->nullable();

            // Which language this was printed in — set from the customer.
            $table->string('language', 5)->default('en');

            $table->text('terms')->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('sent_at')->nullable();

            /*
             * The approval record.
             *
             * `approved_by_name` is the customer's person, typed in — not a
             * system user. They do not log in; you record who told you yes.
             */
            $table->timestamp('approved_at')->nullable();
            $table->string('approved_by_name')->nullable();
            $table->string('approval_channel')->nullable();   // whatsapp, phone, in person
            $table->text('approval_note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            $table->unique(['deal_id', 'version']);
        });

        /*
         * Frozen copies, not references.
         *
         * These duplicate the deal line on purpose. A reference would let a
         * later edit rewrite history, which is exactly what this table exists
         * to prevent. Cost is deliberately absent — a quotation is a customer
         * document and must not carry it even in the database row.
         */
        Schema::create('quotation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_line_id')->nullable()->constrained()->nullOnDelete();

            $table->string('description');
            $table->string('description_ku')->nullable();
            $table->text('specification')->nullable();
            $table->decimal('quantity', 19, 4);
            $table->string('unit')->default('pcs');
            $table->decimal('unit_price', 19, 4);
            $table->decimal('line_total', 19, 4);

            /*
             * The photo the customer approved, copied by path rather than by
             * media id — so replacing the product's picture later cannot change
             * what this quotation showed.
             */
            $table->string('photo_path')->nullable();

            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['quotation_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_lines');
        Schema::dropIfExists('quotations');
    }
};
