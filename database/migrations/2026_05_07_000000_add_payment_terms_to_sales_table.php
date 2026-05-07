<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('payment_terms')->nullable()->after('payment_method');
            $table->date('due_date')->nullable()->after('payment_terms');
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('paid')->after('due_date');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE sales MODIFY payment_method ENUM('cash', 'card', 'bank_transfer', 'gcash', 'paymaya', 'terms') DEFAULT 'cash'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE sales MODIFY payment_method ENUM('cash', 'card', 'bank_transfer', 'gcash', 'paymaya') DEFAULT 'cash'");
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['payment_terms', 'due_date', 'payment_status']);
        });
    }
};
