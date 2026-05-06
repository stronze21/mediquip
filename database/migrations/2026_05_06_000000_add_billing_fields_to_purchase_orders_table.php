<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('tin')->nullable()->after('expected_date');
            $table->string('business_style')->nullable()->after('tin');
            $table->text('address')->nullable()->after('business_style');
            $table->string('contact_person')->nullable()->after('address');
            $table->string('contact_number')->nullable()->after('contact_person');
            $table->string('terms')->nullable()->after('contact_number');
            $table->date('due_date')->nullable()->after('terms');
            $table->string('discount_type')->default('regular')->after('due_date');
            $table->decimal('discount_value', 10, 2)->default(0)->after('discount_type');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('discount_value');
            $table->string('tax_type')->default('vat_12')->after('discount_amount');
            $table->decimal('tax_rate', 5, 2)->default(12)->after('tax_type');
            $table->decimal('tax_amount', 10, 2)->default(0)->after('tax_rate');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn([
                'tin',
                'business_style',
                'address',
                'contact_person',
                'contact_number',
                'terms',
                'due_date',
                'discount_type',
                'discount_value',
                'discount_amount',
                'tax_type',
                'tax_rate',
                'tax_amount',
            ]);
        });
    }
};
