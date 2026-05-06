<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'discount_type')) {
                $table->string('discount_type')->default('regular')->after('due_date');
            }

            if (!Schema::hasColumn('purchase_orders', 'discount_value')) {
                $table->decimal('discount_value', 10, 2)->default(0)->after('discount_type');
            }

            if (!Schema::hasColumn('purchase_orders', 'discount_amount')) {
                $table->decimal('discount_amount', 10, 2)->default(0)->after('discount_value');
            }

            if (!Schema::hasColumn('purchase_orders', 'tax_type')) {
                $table->string('tax_type')->default('vat_12')->after('discount_amount');
            }

            if (!Schema::hasColumn('purchase_orders', 'tax_rate')) {
                $table->decimal('tax_rate', 5, 2)->default(12)->after('tax_type');
            }

            if (!Schema::hasColumn('purchase_orders', 'tax_amount')) {
                $table->decimal('tax_amount', 10, 2)->default(0)->after('tax_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            foreach (['discount_type', 'discount_value', 'discount_amount', 'tax_type', 'tax_rate', 'tax_amount'] as $column) {
                if (Schema::hasColumn('purchase_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
