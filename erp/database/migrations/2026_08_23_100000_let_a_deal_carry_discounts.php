<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Discounts, given once the list of items is complete.
 *
 * Two quite different things share the word, and keeping them apart is the
 * whole design here:
 *
 *   The supplier's discount comes off what you PAY. It is their concession on
 *   the finished order — "take 5% off the lot" — and by default it is yours to
 *   keep, which is why `pass_supplier_discount_on` exists rather than being
 *   assumed either way.
 *
 *   The profit discount comes off what the CUSTOMER pays, and out of your
 *   margin alone. No supplier price moves; you are simply taking less.
 *
 * Both are held as lumps against the order rather than spread across the item
 * rows, for the same reason the deal commission is: the supplier quoted ¥50 and
 * the record should go on saying ¥50. A discount smeared back into the unit
 * costs would quietly rewrite the one figure you can check against their
 * invoice. The screen shows each item's discounted cost beside the quoted one,
 * so the per-item view is not lost — it is just not what gets stored.
 *
 * The cost of the lump form is that per-item profit becomes an estimate, which
 * the deal already reports for the commission and now reports for these too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            /*
             * The supplier's discount on the whole order.
             *
             * Percentage and typed amount are both allowed and both apply — a
             * supplier who says "5% off, and I'll knock another ¥200 off the
             * carriage" is describing one concession in two parts, and making
             * you pick one of them would lose half of it.
             *
             * This is the deal-wide one. Where several suppliers each gave
             * their own, those live on `deal_purchases` below, and this comes
             * off whatever is left after them.
             */
            $table->decimal('supplier_discount_percent', 9, 4)->nullable()->after('deal_commission_currency');
            $table->decimal('supplier_discount_amount', 19, 4)->default(0)->after('supplier_discount_percent');
            $table->string('supplier_discount_currency', 3)->nullable()->after('supplier_discount_amount');

            /*
             * Whether the customer sees any of it.
             *
             * False — you keep it — is the default because that is the common
             * case: the discount was negotiated by you, on your order, and the
             * customer has already agreed a price. Turning it on takes the same
             * percentage off their side, so your margin percentage survives
             * untouched and the concession is genuinely passed through.
             */
            $table->boolean('pass_supplier_discount_on')->default(false)->after('supplier_discount_currency');

            /*
             * Your own discount, out of margin.
             *
             * The percentage is a share of the profit the ITEMS make — their
             * selling total less what they cost you after the supplier's
             * discount. Deliberately not the deal's bottom line: freight and
             * expenses land weeks later, and a discount agreed on the phone
             * must not quietly change size when the freight bill arrives.
             *
             * The amount is a flat sum in the currency the customer is billed
             * in, because "take fifty thousand dinars off" is how it is said.
             */
            $table->decimal('profit_discount_percent', 9, 4)->nullable()->after('pass_supplier_discount_on');
            $table->decimal('profit_discount_amount', 19, 4)->default(0)->after('profit_discount_percent');

            /*
             * Frozen results, stamped on save like every other base figure here.
             *
             * Worked out from the fields above and the lines as they stand, then
             * left alone. Recomputing at read time would let a later rate change
             * move a discount that was agreed months ago — the same reason the
             * line totals are frozen.
             *
             * `supplier_discount_base` is the deal-wide one only; each purchase
             * carries its own. `customer_discount` is what comes off the
             * customer's total, held twice: once in their currency because that
             * is what the invoice prints, once in dollars because that is what
             * profit is measured in.
             */
            $table->decimal('supplier_discount_base', 19, 4)->default(0)->after('profit_discount_amount');
            $table->decimal('customer_discount', 19, 4)->default(0)->after('supplier_discount_base');
            $table->decimal('customer_discount_base', 19, 4)->default(0)->after('customer_discount');
        });

        /*
         * One supplier's discount on their own order.
         *
         * Separate from the deal-wide one because a deal bought from three
         * suppliers is three negotiations. Supplier A's 5% has nothing to do
         * with supplier B, and what you owe each of them has to be right on its
         * own — the purchase document is what you settle against.
         */
        Schema::table('deal_purchases', function (Blueprint $table) {
            $table->decimal('discount_percent', 9, 4)->nullable()->after('currency');
            $table->decimal('discount_amount', 19, 4)->default(0)->after('discount_percent');
            $table->decimal('discount_base', 19, 4)->default(0)->after('discount_amount');
        });

        /*
         * The quotation had a total and nothing to explain it.
         *
         * A discounted quotation that shows only the final figure invites the
         * question "why is this not what you told me per piece?", which is the
         * argument the frozen snapshot exists to prevent. Subtotal, discount,
         * total — the same three rows the invoice already prints.
         */
        Schema::table('quotations', function (Blueprint $table) {
            $table->decimal('subtotal', 19, 4)->default(0)->after('exchange_rate');
            $table->decimal('discount', 19, 4)->default(0)->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn(['subtotal', 'discount']);
        });

        Schema::table('deal_purchases', function (Blueprint $table) {
            $table->dropColumn(['discount_percent', 'discount_amount', 'discount_base']);
        });

        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn([
                'supplier_discount_percent', 'supplier_discount_amount',
                'supplier_discount_currency', 'pass_supplier_discount_on',
                'profit_discount_percent', 'profit_discount_amount',
                'supplier_discount_base', 'customer_discount', 'customer_discount_base',
            ]);
        });
    }
};
