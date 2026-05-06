<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('invoice_type')->default('sales')->after('promotion_code');
            $table->string('tax_type')->default('vat_12')->after('tax_amount');
            $table->decimal('tax_rate', 5, 2)->default(12)->after('tax_type');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_type',
                'tax_type',
                'tax_rate',
            ]);
        });
    }
};
