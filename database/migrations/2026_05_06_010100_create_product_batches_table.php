<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('warehouse_id')->constrained()->onDelete('cascade');
            $table->foreignId('purchase_order_item_id')->nullable()->constrained()->onDelete('set null');
            $table->string('batch_number');
            $table->string('lot_number')->nullable();
            $table->date('manufactured_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->integer('quantity_received')->default(0);
            $table->integer('quantity_on_hand')->default(0);
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->timestamp('received_at')->nullable();
            $table->string('supplier_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'warehouse_id', 'batch_number']);
            $table->index(['expiry_date', 'quantity_on_hand']);
            $table->index(['product_id', 'quantity_on_hand']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_batches');
    }
};
