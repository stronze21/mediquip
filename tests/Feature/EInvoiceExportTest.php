<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EInvoiceExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_sale_can_be_exported_as_e_invoice_json(): void
    {
        $sale = $this->createCompletedSale();

        $response = $this
            ->actingAs($sale->user)
            ->get(route('invoice.e-invoice.json', $sale));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');

        $payload = json_decode($response->streamedContent(), true);

        $this->assertSame('e_invoice', $payload['document_type']);
        $this->assertSame($sale->invoice_number, $payload['invoice']['number']);
        $this->assertSame('PHP', $payload['invoice']['currency']);
        $this->assertSame('Acme Clinic', $payload['buyer']['name']);
        $this->assertSame('MED-001', $payload['lines'][0]['sku']);
        $this->assertSame('1500.00', $payload['totals']['total_amount']);
    }

    public function test_completed_sale_can_be_exported_as_e_invoice_xml(): void
    {
        $sale = $this->createCompletedSale();

        $response = $this
            ->actingAs($sale->user)
            ->get(route('invoice.e-invoice.xml', $sale));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/xml');

        $xml = $response->streamedContent();

        $this->assertStringContainsString('<EInvoice>', $xml);
        $this->assertStringContainsString('<DocumentType>e_invoice</DocumentType>', $xml);
        $this->assertStringContainsString('<Number>'.$sale->invoice_number.'</Number>', $xml);
        $this->assertStringContainsString('<Sku>MED-001</Sku>', $xml);
    }

    public function test_payment_terms_sale_exports_receivable_details(): void
    {
        $sale = $this->createCompletedSale([
            'paid_amount' => 500,
            'payment_method' => 'terms',
            'payment_terms' => 'Net 30',
            'due_date' => now()->addDays(30)->toDateString(),
            'payment_status' => 'partial',
        ]);

        $response = $this
            ->actingAs($sale->user)
            ->get(route('invoice.e-invoice.json', $sale));

        $payload = json_decode($response->streamedContent(), true);

        $response->assertOk();
        $this->assertSame('terms', $payload['invoice']['payment_method']);
        $this->assertSame('Net 30', $payload['invoice']['payment_terms']);
        $this->assertSame('partial', $payload['invoice']['payment_status']);
        $this->assertSame('1000.00', $payload['totals']['balance_due']);
    }

    private function createCompletedSale(array $overrides = []): Sale
    {
        $user = User::factory()->create([
            'role' => 'cashier',
            'is_active' => true,
            'permissions' => ['process_sales'],
        ]);

        $category = Category::create(['name' => 'Medical Supplies']);

        $warehouse = Warehouse::create([
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'address' => '123 Health Ave',
            'city' => 'Manila',
        ]);

        $customer = Customer::create([
            'name' => 'Acme Clinic',
            'email' => 'billing@example.test',
            'phone' => '09171234567',
            'address' => '456 Care St',
            'city' => 'Quezon City',
            'type' => 'business',
            'tax_id' => '123-456-789',
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Sterile Kit',
            'sku' => 'MED-001',
            'cost_price' => 800,
            'selling_price' => 1500,
        ]);

        $sale = Sale::create(array_merge([
            'invoice_number' => 'INV-TEST-0001',
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $user->id,
            'subtotal' => 1500,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 1500,
            'paid_amount' => 1500,
            'change_amount' => 0,
            'payment_method' => 'cash',
            'status' => 'completed',
            'completed_at' => now(),
        ], $overrides));

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => 'Sterile Kit',
            'product_sku' => 'MED-001',
            'quantity' => 1,
            'unit_price' => 1500,
            'discount_amount' => 0,
            'total_price' => 1500,
        ]);

        return $sale->refresh()->load(['customer', 'warehouse', 'user', 'items.product']);
    }
}
