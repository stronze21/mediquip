<?php

namespace Tests\Feature;

use App\Livewire\Purchasing\PurchaseOrderManagement;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PurchaseOrderDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_received_purchase_order_can_be_hard_deleted_when_inventory_can_be_reverted(): void
    {
        [$user, $purchaseOrder, $item, $inventory] = $this->receivedPurchaseOrderFixture();

        ProductBatch::create([
            'product_id' => $item->product_id,
            'warehouse_id' => $purchaseOrder->warehouse_id,
            'purchase_order_item_id' => $item->id,
            'batch_number' => 'BATCH-001',
            'quantity_received' => 5,
            'quantity_on_hand' => 5,
            'unit_cost' => 100,
            'received_at' => now(),
        ]);

        StockMovement::create([
            'product_id' => $item->product_id,
            'warehouse_id' => $purchaseOrder->warehouse_id,
            'type' => 'purchase',
            'quantity_before' => 0,
            'quantity_changed' => 5,
            'quantity_after' => 5,
            'unit_cost' => 100,
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $purchaseOrder->id,
            'user_id' => $user->id,
            'notes' => 'Received from PO',
        ]);

        Livewire::actingAs($user)
            ->test(PurchaseOrderManagement::class)
            ->call('deletePO', $purchaseOrder);

        $this->assertDatabaseMissing('purchase_orders', ['id' => $purchaseOrder->id]);
        $this->assertDatabaseMissing('purchase_order_items', ['id' => $item->id]);
        $this->assertDatabaseMissing('product_batches', ['purchase_order_item_id' => $item->id]);
        $this->assertDatabaseMissing('stock_movements', [
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $purchaseOrder->id,
        ]);
        $this->assertSame(0, $inventory->refresh()->quantity_on_hand);
    }

    public function test_received_purchase_order_delete_is_blocked_when_inventory_cannot_be_reverted(): void
    {
        [$user, $purchaseOrder, $item, $inventory] = $this->receivedPurchaseOrderFixture([
            'quantity_on_hand' => 3,
        ]);

        Livewire::actingAs($user)
            ->test(PurchaseOrderManagement::class)
            ->call('deletePO', $purchaseOrder);

        $this->assertDatabaseHas('purchase_orders', ['id' => $purchaseOrder->id]);
        $this->assertDatabaseHas('purchase_order_items', ['id' => $item->id]);
        $this->assertSame(3, $inventory->refresh()->quantity_on_hand);
    }

    private function receivedPurchaseOrderFixture(array $inventoryOverrides = []): array
    {
        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'Acme Medical Supply']);
        $warehouse = Warehouse::create(['name' => 'Main Warehouse', 'code' => 'MAIN']);
        $category = Category::create(['name' => 'Medical Supplies']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Sterile Kit',
            'sku' => 'MED-PO-001',
            'cost_price' => 100,
            'selling_price' => 150,
        ]);

        $purchaseOrder = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'requested_by' => $user->id,
            'status' => 'completed',
            'total_amount' => 500,
            'order_date' => '2026-06-29',
            'expected_date' => '2026-07-06',
        ]);

        $item = PurchaseOrderItem::create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $product->id,
            'quantity_ordered' => 5,
            'quantity_received' => 5,
            'unit_cost' => 100,
            'total_cost' => 500,
            'tax_type' => 'vat_12',
            'tax_rate' => 12,
            'tax_amount' => 0,
        ]);

        $inventory = Inventory::create(array_merge([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
            'average_cost' => 100,
        ], $inventoryOverrides));

        return [$user, $purchaseOrder, $item, $inventory];
    }
}
