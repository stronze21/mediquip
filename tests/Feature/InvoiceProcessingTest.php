<?php

namespace Tests\Feature;

use App\Livewire\Sales\PointOfSale;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductService;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiple_draft_product_invoices_can_be_processed_in_sequence(): void
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Main Warehouse', 'code' => 'MAIN']);
        $customer = Customer::create(['name' => 'Acme Clinic']);
        $product = $this->createStockedProduct($warehouse, 10);

        $first = $this->createDraftInvoice('INV-SEQ-001', $warehouse, $customer, $user, $product, 2);
        $second = $this->createDraftInvoice('INV-SEQ-002', $warehouse, $customer, $user, $product, 3);

        $this->actingAs($user);

        Livewire::test(PointOfSale::class)
            ->call('processInvoice', $first)
            ->set('paidAmount', 200)
            ->call('completeSale')
            ->call('processInvoice', $second)
            ->set('paidAmount', 300)
            ->call('completeSale')
            ->assertHasNoErrors();

        $this->assertSame('completed', $first->refresh()->status);
        $this->assertSame('completed', $second->refresh()->status);
        $this->assertSame(5, Inventory::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->value('quantity_on_hand'));
    }

    public function test_process_as_receivable_completes_a_draft_invoice(): void
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Main Warehouse', 'code' => 'MAIN']);
        $customer = Customer::create(['name' => 'Acme Clinic']);
        $product = $this->createStockedProduct($warehouse, 10);
        $draft = $this->createDraftInvoice('INV-REC-001', $warehouse, $customer, $user, $product, 2);

        $this->actingAs($user);

        Livewire::test(PointOfSale::class)
            ->call('processInvoice', $draft)
            ->call('proceedAsReceivable')
            ->assertHasNoErrors();

        $draft->refresh();

        $this->assertSame('completed', $draft->status);
        $this->assertSame('unpaid', $draft->payment_status);
        $this->assertEquals(0, $draft->paid_amount);
        $this->assertSame(8, Inventory::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->value('quantity_on_hand'));
    }

    public function test_service_invoice_can_be_processed_as_receivable(): void
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Main Warehouse', 'code' => 'MAIN']);
        $customer = Customer::create(['name' => 'Acme Clinic']);
        $service = ProductService::create([
            'name' => 'Equipment Calibration',
            'code' => 'SVC-CAL',
            'price' => 500,
            'status' => 'active',
        ]);

        $draft = Sale::create([
            'invoice_number' => 'INV-SVC-001',
            'invoice_date' => now()->toDateString(),
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $user->id,
            'invoice_type' => 'service',
            'subtotal' => 500,
            'tax_amount' => 0,
            'total_amount' => 500,
            'paid_amount' => 0,
            'change_amount' => 0,
            'payment_method' => 'terms',
            'payment_status' => 'unpaid',
            'status' => 'draft',
        ]);

        SaleItem::create([
            'sale_id' => $draft->id,
            'item_type' => 'service',
            'product_id' => null,
            'service_id' => $service->id,
            'product_name' => $service->name,
            'product_sku' => $service->code,
            'quantity' => 1,
            'unit_price' => 500,
            'total_price' => 500,
            'tax_type' => 'none',
            'tax_rate' => 0,
            'tax_amount' => 0,
            'cost_price' => 0,
        ]);

        $this->actingAs($user);

        Livewire::test(PointOfSale::class)
            ->call('processInvoice', $draft)
            ->call('proceedAsReceivable')
            ->assertHasNoErrors();

        $draft->refresh();

        $this->assertSame('completed', $draft->status);
        $this->assertSame('unpaid', $draft->payment_status);
    }

    private function createStockedProduct(Warehouse $warehouse, int $quantity): Product
    {
        $category = Category::create(['name' => 'Medical Supplies']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Syringe Pack',
            'sku' => 'SYR-001',
            'cost_price' => 25,
            'selling_price' => 100,
            'status' => 'active',
        ]);

        Inventory::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity_on_hand' => $quantity,
            'quantity_reserved' => 0,
            'average_cost' => 25,
        ]);

        return $product;
    }

    private function createDraftInvoice(
        string $invoiceNumber,
        Warehouse $warehouse,
        Customer $customer,
        User $user,
        Product $product,
        int $quantity
    ): Sale {
        $sale = Sale::create([
            'invoice_number' => $invoiceNumber,
            'invoice_date' => now()->toDateString(),
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $user->id,
            'subtotal' => $quantity * 100,
            'tax_amount' => 0,
            'total_amount' => $quantity * 100,
            'paid_amount' => 0,
            'change_amount' => 0,
            'payment_method' => 'terms',
            'payment_status' => 'unpaid',
            'status' => 'draft',
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'item_type' => 'product',
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'quantity' => $quantity,
            'unit_price' => 100,
            'total_price' => $quantity * 100,
            'tax_type' => 'none',
            'tax_rate' => 0,
            'tax_amount' => 0,
            'cost_price' => $product->cost_price,
        ]);

        return $sale;
    }
}
