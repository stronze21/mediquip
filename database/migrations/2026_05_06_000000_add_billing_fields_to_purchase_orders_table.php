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
            ]);
        });
    }
};
