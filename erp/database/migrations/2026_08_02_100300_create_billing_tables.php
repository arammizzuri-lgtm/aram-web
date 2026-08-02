<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Customer invoices, customer money, supplier money.
 *
 * Two rules shape all of this:
 *
 * 1. An invoice is a snapshot, never a live view of the deal. A document you
 *    have handed someone must not silently change when you edit the deal — that
 *    is how arguments start. Corrections happen by cancelling and re-issuing,
 *    which leaves a visible trail.
 *
 * 2. Money lands on the customer's account first and is matched to invoices
 *    afterwards. That is what makes an advance paid before the order exists an
 *    ordinary event rather than a special case needing a workaround.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('number')->unique();

            /*
             * goods    — the products
             * shipping — billed separately once the freight bill arrives, which
             *            is why one deal produces more than one invoice
             * other    — anything else agreed
             */
            $table->string('type')->default('goods')->index();

            // draft → issued → paid | cancelled
            $table->string('status')->default('draft')->index();

            $table->string('currency', 3)->default('IQD');
            $table->decimal('exchange_rate', 19, 6)->nullable();
            $table->decimal('subtotal', 19, 4)->default(0);
            $table->decimal('discount', 19, 4)->default(0);
            $table->decimal('total', 19, 4)->default(0);
            $table->decimal('total_base', 19, 4)->default(0);

            // Kept as a stored figure so "what is still owed" needs no summing.
            $table->decimal('amount_paid', 19, 4)->default(0);

            $table->date('invoice_date');
            $table->date('due_date')->nullable();

            // en | ckb — taken from the customer, so printing asks nothing.
            $table->string('language', 5)->default('en');

            $table->text('notes')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });

        /*
         * Copied at issue, not referenced.
         *
         * Cost is absent by design: this is a customer document and must not
         * carry purchase prices even in the database row that backs it.
         */
        Schema::create('customer_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_line_id')->nullable()->constrained()->nullOnDelete();

            $table->string('description');
            $table->string('description_ku')->nullable();
            $table->text('specification')->nullable();
            $table->decimal('quantity', 19, 4);
            $table->string('unit')->default('pcs');
            $table->decimal('unit_price', 19, 4);
            $table->decimal('line_total', 19, 4);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['customer_invoice_id', 'display_order']);
        });

        /*
         * Money in from a customer.
         *
         * Belongs to the customer, not to an invoice. What is not yet matched to
         * an invoice is their credit balance — which is how an advance payment
         * taken before the deal exists has somewhere to sit.
         */
        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('number')->unique();

            $table->decimal('amount', 19, 4);
            $table->string('currency', 3)->default('IQD');
            $table->decimal('exchange_rate', 19, 6)->nullable();
            $table->decimal('base_amount', 19, 4);

            /*
             * Negative amounts are refunds — money going back to the customer.
             * Kept in the same table so one running balance covers both
             * directions rather than needing two ledgers that must agree.
             */
            $table->string('direction')->default('in');   // in | refund

            $table->string('method')->default('cash');    // cash | transfer | exchange
            $table->string('reference')->nullable();
            $table->date('paid_at');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'paid_at']);
        });

        /*
         * Which invoices a payment covers.
         *
         * A payment may be split across several invoices, and may be left
         * partly or wholly unmatched. The unmatched remainder is credit; it is
         * never lost, only unassigned.
         */
        Schema::create('customer_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 19, 4);
            $table->decimal('base_amount', 19, 4);
            $table->boolean('was_suggested')->default(false);
            $table->timestamps();

            $table->unique(['customer_payment_id', 'customer_invoice_id']);
        });

        /*
         * Money out to a supplier.
         *
         * Two amounts, deliberately. The supplier is owed ¥50,000; getting that
         * money to China cost you $7,100 when the quoted rate said $6,944. The
         * $156 gap is real money and, if not recorded, silently inflates the
         * profit on every deal by an amount that never looks like an error.
         */
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('deal_purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number')->unique();

            // What the supplier received, in their currency.
            $table->decimal('amount', 19, 4);
            $table->string('currency', 3)->default('CNY');
            $table->decimal('exchange_rate', 19, 6)->nullable();
            $table->decimal('base_amount', 19, 4);

            /*
             * What leaving your hands actually cost, in USD. Null means you did
             * not record it and base_amount stands. When present, the difference
             * is a real cost carried by the purchase.
             */
            $table->decimal('actual_cost_base', 19, 4)->nullable();

            $table->string('method')->default('exchange');   // exchange | bank | cash
            $table->string('reference')->nullable();
            $table->date('paid_at');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['supplier_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('customer_payment_allocations');
        Schema::dropIfExists('customer_payments');
        Schema::dropIfExists('customer_invoice_lines');
        Schema::dropIfExists('customer_invoices');
    }
};
