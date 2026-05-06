<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Subcategory;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MedicalInventorySeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Starting medical inventory seeder...');

        DB::transaction(function () {
            $warehouse = $this->seedWarehouse();
            $this->seedSuppliers();
            $categories = $this->seedCategories();
            $this->seedProducts($categories, $warehouse);
        });

        $this->command->info('Medical inventory seeder completed successfully.');
    }

    private function seedWarehouse(): Warehouse
    {
        return Warehouse::firstOrCreate(
            ['code' => 'MAIN01'],
            [
                'name' => 'Main Medical Store',
                'slug' => 'main-medical-store',
                'address' => 'Bacarra, Ilocos Norte',
                'city' => 'Bacarra',
                'manager_name' => 'Inventory Manager',
                'phone' => '+63 919 920 1865',
                'type' => 'main',
                'is_active' => true,
            ]
        );
    }

    private function seedSuppliers(): void
    {
        $suppliers = [
            ['name' => 'North Luzon Medical Equipment Trading', 'tin' => '009-321-654-000', 'business_style' => 'Medical Equipment Distributor', 'contact_person' => 'Rafael Santos', 'email' => 'sales@nlmedical.ph', 'phone' => '+63 2 8123 4100', 'address' => 'Quezon City', 'city' => 'Quezon City', 'rating' => 4.7, 'lead_time_days' => 10],
            ['name' => 'MediSupply Philippines Inc.', 'tin' => '006-742-118-000', 'business_style' => 'Medical Supplies Wholesale', 'contact_person' => 'Clara Reyes', 'email' => 'orders@medisupply.ph', 'phone' => '+63 2 8455 2200', 'address' => 'Mandaluyong City', 'city' => 'Mandaluyong', 'rating' => 4.5, 'lead_time_days' => 5],
            ['name' => 'PharmaCare Distribution Corp.', 'tin' => '004-889-210-000', 'business_style' => 'Pharmaceutical Distributor', 'contact_person' => 'Joel Garcia', 'email' => 'procurement@pharmacare.ph', 'phone' => '+63 2 8777 3100', 'address' => 'Pasig City', 'city' => 'Pasig', 'rating' => 4.8, 'lead_time_days' => 7],
            ['name' => 'Medical Trading', 'business_style' => 'Medical Trading', 'phone' => '09499935856', 'country' => 'Philippines', 'rating' => 4.0, 'lead_time_days' => 7],
            ['name' => 'Meed Pharma', 'business_style' => 'Pharmaceutical Supplier', 'phone' => '09177108524', 'country' => 'Philippines', 'rating' => 4.0, 'lead_time_days' => 7],
            ['name' => 'Skymed', 'business_style' => 'Medical Supplier', 'phone' => '09605008027', 'country' => 'Philippines', 'rating' => 4.0, 'lead_time_days' => 7],
            ['name' => 'MEDSMART', 'business_style' => 'Medical Supplier', 'phone' => '09685408571', 'country' => 'Philippines', 'rating' => 4.0, 'lead_time_days' => 7],
            ['name' => 'MRL', 'business_style' => 'Medical Supplier', 'phone' => '09770095816', 'country' => 'Philippines', 'rating' => 4.0, 'lead_time_days' => 7],
            ['name' => 'REGIMED', 'business_style' => 'Pharmaceutical Supplier', 'phone' => '09175727232', 'country' => 'Philippines', 'rating' => 4.0, 'lead_time_days' => 7],
            ['name' => 'MEDICAL MART', 'business_style' => 'Medical Supplies Retailer', 'phone' => '09269241517', 'country' => 'Philippines', 'rating' => 4.0, 'lead_time_days' => 7],
            ['name' => 'METROGEN', 'business_style' => 'Medical Supplier', 'phone' => '09177257106', 'country' => 'Philippines', 'rating' => 4.0, 'lead_time_days' => 7],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::updateOrCreate(['name' => $supplier['name']], $supplier + ['is_active' => true]);
        }
    }

    private function seedCategories(): array
    {
        $definitions = [
            'Medical Equipment' => ['Diagnostic Equipment', 'Patient Monitoring', 'Therapy Equipment'],
            'Medical Supplies' => ['Personal Protective Equipment', 'Syringes & Needles', 'Wound Care'],
            'Drugs and Medicines' => ['Analgesics', 'Antibiotics', 'Vitamins & Supplements'],
        ];

        $categories = [];

        foreach ($definitions as $categoryName => $subcategories) {
            $category = Category::firstOrCreate(
                ['name' => $categoryName],
                [
                    'slug' => Str::slug($categoryName),
                    'description' => "Products under {$categoryName}",
                    'icon' => 'o-archive-box',
                    'sort_order' => count($categories) + 1,
                    'is_active' => true,
                ]
            );

            foreach ($subcategories as $index => $subcategoryName) {
                Subcategory::firstOrCreate(
                    ['category_id' => $category->id, 'name' => $subcategoryName],
                    [
                        'slug' => Str::slug($subcategoryName),
                        'description' => "{$subcategoryName} products",
                        'sort_order' => $index + 1,
                        'is_active' => true,
                    ]
                );
            }

            $categories[$categoryName] = $category;
        }

        return $categories;
    }

    private function seedProducts(array $categories, Warehouse $warehouse): void
    {
        $products = [
            [
                'name' => 'Digital Blood Pressure Monitor',
                'sku' => 'MEQ-BPM-001',
                'category' => 'Medical Equipment',
                'subcategory' => 'Diagnostic Equipment',
                'product_type' => 'medical_equipment',
                'cost_price' => 1850,
                'selling_price' => 2600,
                'quantity' => 18,
                'track_serial' => true,
                'track_warranty' => true,
                'track_batch' => false,
                'track_expiry' => false,
                'warranty_months' => 12,
            ],
            [
                'name' => 'Portable Pulse Oximeter',
                'sku' => 'MEQ-OXI-001',
                'category' => 'Medical Equipment',
                'subcategory' => 'Patient Monitoring',
                'product_type' => 'medical_equipment',
                'cost_price' => 420,
                'selling_price' => 750,
                'quantity' => 35,
                'track_serial' => true,
                'track_warranty' => true,
                'track_batch' => false,
                'track_expiry' => false,
                'warranty_months' => 6,
            ],
            [
                'name' => 'Disposable Face Mask 50s',
                'sku' => 'MSP-MASK-050',
                'category' => 'Medical Supplies',
                'subcategory' => 'Personal Protective Equipment',
                'product_type' => 'medical_supply',
                'cost_price' => 55,
                'selling_price' => 95,
                'quantity' => 500,
                'track_batch' => true,
                'track_expiry' => true,
                'batches' => [
                    ['batch_number' => 'MASK-202603-A', 'quantity' => 300, 'expiry_date' => now()->addMonths(20)],
                    ['batch_number' => 'MASK-202511-B', 'quantity' => 200, 'expiry_date' => now()->addMonths(7)],
                ],
            ],
            [
                'name' => 'Sterile Syringe 5 mL',
                'sku' => 'MSP-SYR-005',
                'category' => 'Medical Supplies',
                'subcategory' => 'Syringes & Needles',
                'product_type' => 'medical_supply',
                'cost_price' => 3.50,
                'selling_price' => 8,
                'quantity' => 1200,
                'track_batch' => true,
                'track_expiry' => true,
                'batches' => [
                    ['batch_number' => 'SYR5-202601-A', 'quantity' => 900, 'expiry_date' => now()->addMonths(22)],
                    ['batch_number' => 'SYR5-202405-X', 'quantity' => 300, 'expiry_date' => now()->subMonth()],
                ],
            ],
            [
                'name' => 'Paracetamol 500 mg Tablet',
                'sku' => 'DRG-PARA-500',
                'category' => 'Drugs and Medicines',
                'subcategory' => 'Analgesics',
                'product_type' => 'drug_medicine',
                'cost_price' => 0.90,
                'selling_price' => 2.50,
                'quantity' => 2500,
                'track_batch' => true,
                'track_expiry' => true,
                'batches' => [
                    ['batch_number' => 'PCM500-202604-A', 'quantity' => 1800, 'expiry_date' => now()->addMonths(24)],
                    ['batch_number' => 'PCM500-202408-B', 'quantity' => 700, 'expiry_date' => now()->addDays(65)],
                ],
            ],
            [
                'name' => 'Amoxicillin 500 mg Capsule',
                'sku' => 'DRG-AMOX-500',
                'category' => 'Drugs and Medicines',
                'subcategory' => 'Antibiotics',
                'product_type' => 'drug_medicine',
                'cost_price' => 4.25,
                'selling_price' => 8.50,
                'quantity' => 900,
                'track_batch' => true,
                'track_expiry' => true,
                'batches' => [
                    ['batch_number' => 'AMOX500-202602-A', 'quantity' => 650, 'expiry_date' => now()->addMonths(18)],
                    ['batch_number' => 'AMOX500-202407-C', 'quantity' => 250, 'expiry_date' => now()->addDays(45)],
                ],
            ],
        ];

        foreach ($products as $index => $definition) {
            $category = $categories[$definition['category']];
            $subcategory = Subcategory::where('category_id', $category->id)
                ->where('name', $definition['subcategory'])
                ->first();

            $product = Product::updateOrCreate(
                ['sku' => $definition['sku']],
                [
                    'name' => $definition['name'],
                    'slug' => Str::slug($definition['name']) . '-' . strtolower($definition['sku']),
                    'barcode' => '480MED' . str_pad($index + 1, 6, '0', STR_PAD_LEFT),
                    'category_id' => $category->id,
                    'subcategory_id' => $subcategory?->id,
                    'product_type' => $definition['product_type'],
                    'cost_price' => $definition['cost_price'],
                    'selling_price' => $definition['selling_price'],
                    'wholesale_price' => round($definition['selling_price'] * 0.85, 2),
                    'warranty_months' => $definition['warranty_months'] ?? 0,
                    'track_serial' => $definition['track_serial'] ?? false,
                    'track_warranty' => $definition['track_warranty'] ?? false,
                    'track_batch' => $definition['track_batch'] ?? false,
                    'track_expiry' => $definition['track_expiry'] ?? false,
                    'min_stock_level' => $definition['product_type'] === 'medical_equipment' ? 3 : 50,
                    'max_stock_level' => $definition['product_type'] === 'medical_equipment' ? 50 : 3000,
                    'reorder_point' => $definition['product_type'] === 'medical_equipment' ? 5 : 100,
                    'reorder_quantity' => $definition['product_type'] === 'medical_equipment' ? 10 : 500,
                    'status' => 'active',
                    'internal_notes' => 'Seeded for medical inventory demo.',
                ]
            );

            Inventory::updateOrCreate(
                ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
                [
                    'quantity_on_hand' => $definition['quantity'],
                    'quantity_reserved' => 0,
                    'average_cost' => $definition['cost_price'],
                    'last_counted_at' => now(),
                ]
            );

            foreach ($definition['batches'] ?? [] as $batch) {
                ProductBatch::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'warehouse_id' => $warehouse->id,
                        'batch_number' => $batch['batch_number'],
                    ],
                    [
                        'lot_number' => $batch['batch_number'],
                        'manufactured_date' => now()->subMonths(3),
                        'expiry_date' => $batch['expiry_date'],
                        'quantity_received' => $batch['quantity'],
                        'quantity_on_hand' => $batch['quantity'],
                        'unit_cost' => $definition['cost_price'],
                        'received_at' => now()->subDays(20),
                        'supplier_name' => $definition['product_type'] === 'drug_medicine'
                            ? 'PharmaCare Distribution Corp.'
                            : 'MediSupply Philippines Inc.',
                        'notes' => 'Seed batch for expiry and lot tracking.',
                    ]
                );
            }
        }
    }
}
