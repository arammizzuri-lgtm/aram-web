<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $table->string('type')->default('operating');
            // Logistics costs can be pushed into a container's landed cost; office
            // rent cannot. This flag is what keeps that boundary honest.
            $table->boolean('is_shipment_allocatable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('bank');
            $table->char('currency', 3)->default('USD');
            $table->string('account_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('iban')->nullable();
            $table->decimal('opening_balance', 19, 4)->default(0);
            $table->decimal('current_balance', 19, 4)->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->date('expense_date');
            $table->string('description');
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('vendor_name')->nullable();
            $table->decimal('amount', 19, 4)->default(0);
            $table->char('currency', 3)->default('USD');
            $table->decimal('exchange_rate', 19, 8)->default(1);
            $table->decimal('base_amount', 19, 4)->default(0);
            $table->string('payment_method')->nullable();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            // Linking an expense to a container is what routes it into landed cost
            // instead of letting it leak into general overhead.
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_allocated_to_shipment')->default(false);
            $table->string('status')->default('draft');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['expense_date', 'expense_category_id']);
            $table->index('shipment_id');
        });

        // Append-only cash ledger, same discipline as stock_movements.
        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->restrictOnDelete();
            $table->date('transaction_date');
            $table->string('direction');
            $table->decimal('amount', 19, 4);
            $table->char('currency', 3)->default('USD');
            $table->decimal('exchange_rate', 19, 8)->default(1);
            $table->decimal('base_amount', 19, 4)->default(0);
            $table->nullableMorphs('reference');
            $table->string('description')->nullable();
            $table->decimal('balance_after', 19, 4)->default(0);
            $table->timestamps();

            $table->index(['bank_account_id', 'transaction_date']);
        });

        Schema::create('kpi_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->decimal('revenue_base', 19, 4)->default(0);
            $table->decimal('cogs_base', 19, 4)->default(0);
            $table->decimal('gross_profit_base', 19, 4)->default(0);
            $table->decimal('expenses_base', 19, 4)->default(0);
            $table->decimal('net_profit_base', 19, 4)->default(0);
            $table->decimal('inventory_value_base', 19, 4)->default(0);
            $table->decimal('goods_in_transit_base', 19, 4)->default(0);
            $table->decimal('receivables_base', 19, 4)->default(0);
            $table->decimal('payables_base', 19, 4)->default(0);
            $table->decimal('cash_balance_base', 19, 4)->default(0);
            $table->unsignedInteger('orders_count')->default(0);
            $table->unsignedInteger('invoices_count')->default(0);
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('shipment_costs', function (Blueprint $table) {
            $table->foreign('expense_id')->references('id')->on('expenses')->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $t) => $t->dropForeign(['bank_account_id']));
        Schema::table('shipment_costs', fn (Blueprint $t) => $t->dropForeign(['expense_id']));

        Schema::dropIfExists('kpi_daily');
        Schema::dropIfExists('cash_transactions');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('expense_categories');
    }
};
