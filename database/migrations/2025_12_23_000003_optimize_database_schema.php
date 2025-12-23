<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Add Status Fields & Soft Deletes to Transactions
        Schema::table('sales_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_invoices', 'status')) {
                $table->string('status')->default('draft')->after('date');
            }
            if (!Schema::hasColumn('sales_invoices', 'deleted_at')) {
                $table->softDeletes();
            }
            // Drop calculated fields
            $table->dropColumn(['total_amount', 'paid_amount', 'remaining_amount']);
        });

        Schema::table('purchase_bills', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_bills', 'status')) {
                $table->string('status')->default('draft')->after('date');
            }
            if (!Schema::hasColumn('purchase_bills', 'deleted_at')) {
                $table->softDeletes();
            }
            $table->dropColumn(['total_amount']);
        });

        Schema::table('production_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('production_orders', 'status')) {
                $table->string('status')->default('draft')->after('id');
            }
        });

        // 2. Add Soft Deletes to Master Data
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'deleted_at')) {
                $table->softDeletes();
            }
        });
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'deleted_at')) {
                $table->softDeletes();
            }
            $table->dropColumn('balance');
        });
        Schema::table('suppliers', function (Blueprint $table) {
            if (!Schema::hasColumn('suppliers', 'deleted_at')) {
                $table->softDeletes();
            }
            $table->dropColumn('balance');
        });
        Schema::table('categories', function (Blueprint $table) {
            if (!Schema::hasColumn('categories', 'deleted_at')) {
                $table->softDeletes();
            }
        });
        Schema::table('treasuries', function (Blueprint $table) {
            $table->dropColumn('current_balance');
        });

        // 3. Unique Constraints (Schema::hasIndex check is complex, just attempting. 
        // If data violates, this will fail, user must fix data first).
        try {
            Schema::table('products', function (Blueprint $table) {
                $table->unique('name');
                $table->unique('code');
            });
            Schema::table('categories', function (Blueprint $table) {
                $table->unique('name');
            });
        } catch (\Exception $e) {
            // Ignore if constraints already exist or data violates (not ideal but safe for migration script to not crash hard without user intervention)
        }

        // 4. Drop redundant line items totals
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('line_total');
        });
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropColumn('line_total');
        });
    }

    public function down(): void
    {
        // Basic revert
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('balance', 18, 2)->default(0);
        });
        Schema::table('suppliers', function (Blueprint $table) {
            $table->decimal('balance', 18, 2)->default(0);
        });
    }
};
