<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->enum('product_type', ['medical_equipment', 'medical_supply', 'drug_medicine'])
                ->default('medical_supply')
                ->after('subcategory_id');
            $table->boolean('track_batch')->default(false)->after('track_warranty');
            $table->boolean('track_expiry')->default(false)->after('track_batch');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['product_type', 'track_batch', 'track_expiry']);
        });
    }
};
