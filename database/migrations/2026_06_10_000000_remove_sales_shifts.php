<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sale_returns') && Schema::hasColumn('sale_returns', 'sales_shift_id')) {
            $hasShiftStatusIndex = $this->indexExists('sale_returns', 'sale_returns_shift_status_index');

            Schema::table('sale_returns', function (Blueprint $table) use ($hasShiftStatusIndex) {
                if ($hasShiftStatusIndex) {
                    $table->dropIndex('sale_returns_shift_status_index');
                }

                $table->dropForeign(['sales_shift_id']);
                $table->dropColumn('sales_shift_id');
            });
        }

        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'shift_id')) {
            $hasShiftIndex = $this->indexExists('sales', 'sales_shift_id_index');

            Schema::table('sales', function (Blueprint $table) use ($hasShiftIndex) {
                if ($hasShiftIndex) {
                    $table->dropIndex('sales_shift_id_index');
                }

                $table->dropForeign(['shift_id']);
                $table->dropColumn('shift_id');
            });
        }

        Schema::dropIfExists('sales_shifts');
    }

    public function down(): void
    {
        if (!Schema::hasTable('sales_shifts')) {
            Schema::create('sales_shifts', function (Blueprint $table) {
                $table->id();
                $table->string('shift_number')->unique();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('warehouse_id')->constrained()->onDelete('cascade');
                $table->timestamp('started_at');
                $table->timestamp('ended_at')->nullable();
                $table->decimal('opening_cash', 10, 2)->default(0);
                $table->decimal('closing_cash', 10, 2)->nullable();
                $table->decimal('expected_cash', 10, 2)->default(0);
                $table->decimal('cash_difference', 10, 2)->nullable();
                $table->decimal('total_sales', 10, 2)->default(0);
                $table->integer('total_transactions')->default(0);
                $table->decimal('cash_sales', 10, 2)->default(0);
                $table->decimal('card_sales', 10, 2)->default(0);
                $table->decimal('other_sales', 10, 2)->default(0);
                $table->integer('total_returns_count')->default(0);
                $table->decimal('total_returns_amount', 10, 2)->default(0);
                $table->integer('processed_returns_count')->default(0);
                $table->decimal('processed_returns_amount', 10, 2)->default(0);
                $table->text('opening_notes')->nullable();
                $table->text('closing_notes')->nullable();
                $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
                $table->timestamps();
            });
        }

        if (Schema::hasTable('sales') && !Schema::hasColumn('sales', 'shift_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->foreignId('shift_id')->nullable()->after('user_id')->constrained('sales_shifts')->nullOnDelete();
            });
        }

        if (Schema::hasTable('sale_returns') && !Schema::hasColumn('sale_returns', 'sales_shift_id')) {
            Schema::table('sale_returns', function (Blueprint $table) {
                $table->foreignId('sales_shift_id')->nullable()->after('user_id')->constrained('sales_shifts')->nullOnDelete();
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        if (!method_exists(Schema::getFacadeRoot(), 'getIndexes')) {
            return true;
        }

        return collect(Schema::getIndexes($table))->contains(fn($existing) => ($existing['name'] ?? null) === $index);
    }
};
