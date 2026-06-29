<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_order_numbers_use_yearly_sequence_format(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'Acme Medical Supply']);
        $warehouse = Warehouse::create(['name' => 'Main Warehouse', 'code' => 'MAIN']);

        $first = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'requested_by' => $user->id,
            'status' => 'draft',
            'total_amount' => 0,
            'order_date' => '2026-06-29',
            'expected_date' => '2026-07-06',
        ]);

        $second = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'requested_by' => $user->id,
            'status' => 'draft',
            'total_amount' => 0,
            'order_date' => '2026-08-01',
            'expected_date' => '2026-08-08',
        ]);

        $this->assertSame('PO-2026001', $first->po_number);
        $this->assertSame('PO-2026002', $second->po_number);
    }

    public function test_legacy_purchase_order_numbers_can_be_updated_by_command(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'Acme Medical Supply']);
        $warehouse = Warehouse::create(['name' => 'Main Warehouse', 'code' => 'MAIN']);

        $existingCurrent = $this->createPurchaseOrder($supplier->id, $warehouse->id, $user->id, [
            'po_number' => 'PO-2026005',
            'order_date' => '2026-01-05',
            'expected_date' => '2026-01-12',
        ]);
        $firstLegacy = $this->createPurchaseOrder($supplier->id, $warehouse->id, $user->id, [
            'po_number' => 'PO-20260629-0001',
            'order_date' => '2026-06-29',
            'expected_date' => '2026-07-06',
        ]);
        $secondLegacy = $this->createPurchaseOrder($supplier->id, $warehouse->id, $user->id, [
            'po_number' => 'PO-20260801-0002',
            'order_date' => '2026-08-01',
            'expected_date' => '2026-08-08',
        ]);
        $nextYearLegacy = $this->createPurchaseOrder($supplier->id, $warehouse->id, $user->id, [
            'po_number' => 'PO-20270110-0001',
            'order_date' => '2027-01-10',
            'expected_date' => '2027-01-17',
        ]);

        $this->artisan('purchase-orders:update-legacy-numbers', ['--dry-run' => true])
            ->expectsOutput('Found 3 legacy purchase order number(s).')
            ->assertExitCode(0);

        $this->assertSame('PO-20260629-0001', $firstLegacy->refresh()->po_number);

        $this->artisan('purchase-orders:update-legacy-numbers')
            ->expectsOutput('Found 3 legacy purchase order number(s).')
            ->assertExitCode(0);

        $this->assertSame('PO-2026005', $existingCurrent->refresh()->po_number);
        $this->assertSame('PO-2026006', $firstLegacy->refresh()->po_number);
        $this->assertSame('PO-2026007', $secondLegacy->refresh()->po_number);
        $this->assertSame('PO-2027001', $nextYearLegacy->refresh()->po_number);
    }

    private function createPurchaseOrder(int $supplierId, int $warehouseId, int $userId, array $overrides = []): PurchaseOrder
    {
        return PurchaseOrder::create(array_merge([
            'supplier_id' => $supplierId,
            'warehouse_id' => $warehouseId,
            'requested_by' => $userId,
            'status' => 'draft',
            'total_amount' => 0,
            'order_date' => '2026-06-29',
            'expected_date' => '2026-07-06',
        ], $overrides));
    }
}
