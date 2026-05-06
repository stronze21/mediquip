<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            // Remove foreign key constraint first
            $table->dropForeign(['product_brand_id']);
            // Then remove the composite index that still references product_brand_id.
            $table->dropIndex('products_product_brand_id_status_index');
            // Remove columns
            $table->dropColumn(['product_brand_id', 'description', 'part_number', 'oem_number']);
        });
        Schema::table('motorcycle_models', function (Blueprint $table) {
            // Remove foreign key constraint first
            $table->dropForeign(['brand_id']);
        });
        Schema::dropIfExists('product_compatibility');
        Schema::dropIfExists('motorcycle_models');
        Schema::dropIfExists('motorcycle_brands');
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('product_brand_id')->nullable()->after('subcategory_id');
            $table->text('description')->nullable()->after('rfid_tag');
            $table->string('part_number')->nullable();
            $table->string('oem_number')->nullable();

            $table->foreign('product_brand_id')->references('id')->on('product_brands')->onDelete('set null');
        });

        // Motorcycle Brands
        Schema::create('motorcycle_brands', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Motorcycle Models
        Schema::create('motorcycle_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained('motorcycle_brands')->onDelete('cascade');
            $table->string('name');
            $table->string('slug');
            $table->string('engine_type')->nullable(); // 4T, 2T, etc.
            $table->integer('engine_cc')->nullable();
            $table->year('year_from')->nullable();
            $table->year('year_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['brand_id', 'slug']);
        });

        Schema::create('product_compatibility', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('motorcycle_model_id')->constrained()->onDelete('cascade');
            $table->year('year_from')->nullable();
            $table->year('year_to')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'motorcycle_model_id']);
        });
    }
};
