<?php

namespace App\Livewire\Sales;

use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductService;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SerialNumber;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

class PointOfSale extends Component
{
    use Toast;

    // Cart and sale data
    public $cartItems = [];
    public $subtotal = 0;
    public $discountAmount = 0;
    public $taxAmount = 0;
    public $totalAmount = 0;
    public $paidAmount = 0;
    public $changeAmount = 0;

    // Form fields
    public $searchProduct = '';
    public $selectedCustomer = null;
    public $selectedWarehouse = '';
    public $paymentMethod = 'cash';
    public $paymentTerms = 'Due on receipt';
    public $paymentDueDate = '';
    public $saleNotes = '';
    public $invoiceType = 'sales';

    // UI state
    public $showCustomerModal = false;
    public $showPaymentModal = false;
    public $showDiscountModal = false;
    public $showHoldSaleModal = false;
    public $showSearchCustomerModal = false;
    public $searchResults = [];

    // Customer form fields
    public $customerName = '';
    public $customerEmail = '';
    public $customerPhone = '';
    public $customerAddress = '';

    // Hold sale fields
    public $holdReference = '';
    public $holdNotes = '';

    // Discount fields
    public $discountType = 'percentage'; // percentage, fixed, senior, or pwd
    public $discountValue = '';

    // Customer search
    public $customerSearch = '';
    public $customerSearchResults = [];

    // Barcode scanning
    public $showBarcodeModal = false;
    public $barcodeInput = '';
    public $scannedItems = [];

    // Tax fields
    public $taxType = 'vat_12';
    public $taxRate = 12;

    public $showHeldSalesModal = false;
    public $heldSales = [];

    // Price selection properties
    public $showPriceModal = false;
    public $showBulkPriceModal = false;
    public $selectedCartIndex = null;
    public $availablePrices = [];
    public $showAddPriceModal = false;
    public $pendingProductId = null;
    public $bulkPriceType = 'selling_price';

    // Add these properties to the existing PointOfSale class
    public $showSerialModal = false;
    public $serialCartKey = null;
    public $serialProductId = null;
    public $requiredSerials = 1;
    public $enteredSerials = [];
    public $serialInput = '';
    public $availableSerials = [];
    public $bulkSerialInput = '';


    public $searchService = '';
    public $serviceResults = [];
    public $showServiceModal = false;

    public function mount()
    {
        $this->selectedWarehouse = Warehouse::where('is_active', true)->first()?->id;

        $this->loadHeldSales();
    }

    public function render()
    {
        $warehouses = Warehouse::where('is_active', true)->get();
        $customers = Customer::where('is_active', true)->orderBy('name')->get();

        return view('livewire.sales.point-of-sale', [
            'warehouses' => $warehouses,
            'customers' => $customers,
        ])->layout('layouts.pos', ['title' => 'Invoice']);
    }

    public function updatedInvoiceType($type)
    {
        $this->invoiceType = in_array($type, ['sales', 'service'], true) ? $type : 'sales';
        $this->searchProduct = '';
        $this->searchResults = [];
        $this->searchService = '';
        $this->serviceResults = [];
        $this->taxType = $this->invoiceType === 'service' ? 'ewt_service_2' : 'vat_12';
        $this->updatedTaxType($this->taxType);
        $this->updateCartTotals();
    }

    public function updatedTaxType($type)
    {
        $this->taxType = in_array($type, ['none', 'vat_12', 'ewt_sales_1', 'ewt_service_2'], true) ? $type : 'vat_12';
        $this->taxRate = $this->taxRateForType($this->taxType);
        $this->updateCartTotals();
    }

    public function taxRateForType(string $type): float
    {
        return match ($type) {
            'vat_12' => 12,
            'ewt_sales_1' => 1,
            'ewt_service_2' => 2,
            default => 0,
        };
    }

    public function taxLabel(): string
    {
        return match ($this->taxType) {
            'vat_12' => 'VAT (12% inclusive)',
            'ewt_sales_1' => 'EWT (1% on sales, net of VAT)',
            'ewt_service_2' => 'EWT (2% on services, net of VAT)',
            default => 'No Tax',
        };
    }

    public function calculateTaxAmount(float $amount): float
    {
        if ($this->taxType === 'vat_12') {
            return $amount - $this->calculateVatExclusiveAmount($amount);
        }

        if (in_array($this->taxType, ['ewt_sales_1', 'ewt_service_2'], true)) {
            return $this->calculateVatExclusiveAmount($amount) * ($this->taxRate / 100);
        }

        return $amount * ($this->taxRate / 100);
    }

    public function calculateVatExclusiveAmount(float $amount): float
    {
        return $amount / 1.12;
    }

    public function taxableGrossAmount(): float
    {
        return max(0, $this->subtotal - $this->discountAmount);
    }

    public function displaySubtotalAmount(): float
    {
        if (in_array($this->taxType, ['vat_12', 'ewt_sales_1', 'ewt_service_2'], true)) {
            return $this->calculateVatExclusiveAmount($this->taxableGrossAmount());
        }

        return $this->taxableGrossAmount();
    }

    public function subtotalLabel(): string
    {
        return in_array($this->taxType, ['vat_12', 'ewt_sales_1', 'ewt_service_2'], true)
            ? 'Subtotal (Net of VAT):'
            : 'Subtotal:';
    }

    public function calculateTotalAmount(float $amount, float $taxAmount): float
    {
        return match ($this->taxType) {
            'vat_12' => $amount,
            'ewt_sales_1', 'ewt_service_2' => max(0, $amount - $taxAmount),
            default => $amount + $taxAmount,
        };
    }

    public function addToCart($productId)
    {
        $product = Product::with(['inventory' => function ($query) {
            $query->where('warehouse_id', $this->selectedWarehouse);
        }])->find($productId);

        if (!$product) {
            $this->error('Product not found.');
            return;
        }

        $inventory = $product->inventory->first();
        $availableStock = $inventory ? $inventory->quantity_available : 0;

        if ($availableStock <= 0) {
            $this->error('Product is out of stock.');
            return;
        }

        $cartKey = 'product_' . $productId;

        if (isset($this->cartItems[$cartKey])) {
            if ($this->cartItems[$cartKey]['quantity'] >= $availableStock) {
                $this->error('Cannot add more items. Stock limit reached.');
                return;
            }
            $this->cartItems[$cartKey]['quantity']++;
            $this->cartItems[$cartKey]['subtotal'] =
                $this->cartItems[$cartKey]['quantity'] * $this->cartItems[$cartKey]['price'];

            if ($product->track_serial) {
                $this->cartItems[$cartKey]['serial_numbers'] = [];
            }
        } else {
            $this->cartItems[$cartKey] = [
                'item_type' => 'product',
                'product_id' => $product->id,
                'service_id' => null,
                'name' => $product->name,
                'code' => $product->sku,
                'price' => $product->selling_price,
                'quantity' => 1,
                'available_stock' => $availableStock,
                'subtotal' => $product->selling_price,
                'track_serial' => $product->track_serial,
                'serial_numbers' => [],
            ];
        }

        $this->updateCartTotals();
        $this->searchProduct = '';
        $this->searchResults = [];
        $this->success('Item added to cart!');
    }

    public function addServiceToCart($serviceId)
    {
        $service = ProductService::find($serviceId);

        if (!$service || $service->status !== 'active') {
            $this->error('Service not found or inactive.');
            return;
        }

        $cartKey = 'service_' . $serviceId;

        if (isset($this->cartItems[$cartKey])) {
            // Increase quantity for existing service
            $this->cartItems[$cartKey]['quantity']++;
            $this->cartItems[$cartKey]['subtotal'] =
                $this->cartItems[$cartKey]['quantity'] * $this->cartItems[$cartKey]['price'];
        } else {
            // Add new service to cart
            $this->cartItems[$cartKey] = [
                'item_type' => 'service',
                'product_id' => null,
                'service_id' => $serviceId,
                'name' => $service->name,
                'code' => $service->code,
                'price' => $service->price,
                'quantity' => 1,
                'subtotal' => $service->price,
                'track_serial' => false,  // Services never track serials
                'serial_numbers' => [],
                'available_stock' => null, // Services don't have stock
            ];
        }

        $this->updateCartTotals();
        $this->success('Service added to cart: ' . $service->name);

        // Clear search
        $this->searchService = '';
        $this->serviceResults = [];
    }

    public function removeFromCart($cartKey)
    {
        unset($this->cartItems[$cartKey]);
        $this->updateCartTotals();
        $this->success('Item removed from cart.');
    }

    public function increaseQuantity($cartKey)
    {
        if (!isset($this->cartItems[$cartKey])) return;

        $item = $this->cartItems[$cartKey];

        // Check stock limit for products only
        if ($item['item_type'] === 'product' && $item['quantity'] >= $item['available_stock']) {
            $this->error('Cannot add more items. Stock limit reached.');
            return;
        }

        $this->cartItems[$cartKey]['quantity']++;
        $this->cartItems[$cartKey]['subtotal'] =
            $this->cartItems[$cartKey]['quantity'] * $this->cartItems[$cartKey]['price'];

        $this->updateCartTotals();
    }

    public function decreaseQuantity($cartKey)
    {
        if (!isset($this->cartItems[$cartKey])) return;

        if ($this->cartItems[$cartKey]['quantity'] > 1) {
            $this->cartItems[$cartKey]['quantity']--;
            $this->cartItems[$cartKey]['subtotal'] =
                $this->cartItems[$cartKey]['quantity'] * $this->cartItems[$cartKey]['price'];
        } else {
            unset($this->cartItems[$cartKey]);
        }

        $this->updateCartTotals();
    }

    public function addSerialNumber()
    {
        if (empty($this->serialInput)) {
            $this->error('Please enter a serial number.');
            return;
        }

        // Check if already entered in current session
        if (in_array($this->serialInput, $this->enteredSerials)) {
            $this->error('Serial number already added to this item.');
            return;
        }

        // Check if we've reached the required quantity
        if (count($this->enteredSerials) >= $this->requiredSerials) {
            $this->error('All required serial numbers have been entered.');
            return;
        }

        // Simply add the serial number - no database validation
        $this->enteredSerials[] = $this->serialInput;
        $this->serialInput = '';

        $this->success('Serial number added successfully!');
    }

    public function removeSerialNumber($index)
    {
        if (isset($this->enteredSerials[$index])) {
            $removedSerial = $this->enteredSerials[$index];
            unset($this->enteredSerials[$index]);
            $this->enteredSerials = array_values($this->enteredSerials);
            $this->success('Removed serial: ' . $removedSerial);
        }
    }

    public function saveSerialNumbers()
    {
        if (count($this->enteredSerials) !== $this->requiredSerials) {
            $this->error("Please enter exactly {$this->requiredSerials} serial number(s).");
            return;
        }

        // Update the cart item with serial numbers
        $this->cartItems[$this->serialCartKey]['serial_numbers'] = $this->enteredSerials;

        $this->showSerialModal = false;
        $this->reset(['serialCartKey', 'serialProductId', 'requiredSerials', 'enteredSerials', 'serialInput']);

        $this->success('Serial numbers saved successfully!');
    }

    public function checkSerialRequirements()
    {
        $missingSerials = [];

        foreach ($this->cartItems as $cartKey => $item) {
            // Only check products that track serials
            if ($item['item_type'] !== 'product' || !isset($item['track_serial']) || !$item['track_serial']) {
                continue;
            }

            $serialCount = count($item['serial_numbers'] ?? []);
            $required = $item['quantity'];

            if ($serialCount < $required) {
                $missingSerials[] = [
                    'key' => $cartKey,
                    'name' => $item['name'],
                    'current' => $serialCount,
                    'required' => $required
                ];
            }
        }

        return $missingSerials;
    }

    // Method to auto-generate serial numbers if needed
    public function generateSerialNumbers()
    {
        $product = Product::find($this->serialProductId);
        $missing = $this->requiredSerials - count($this->enteredSerials);

        for ($i = 1; $i <= $missing; $i++) {
            $serialNumber = $product->sku . '-' . date('Ymd') . '-' . str_pad($i, 3, '0', STR_PAD_LEFT);
            $this->enteredSerials[] = $serialNumber;
        }

        $this->success("Generated {$missing} serial numbers automatically!");
    }


    public function addBulkSerials()
    {
        if (empty($this->bulkSerialInput)) {
            $this->error('Please enter serial numbers.');
            return;
        }

        // Split by newlines and clean up
        $serials = array_filter(array_map('trim', explode("\n", $this->bulkSerialInput)));
        $added = 0;
        $duplicates = 0;

        foreach ($serials as $serial) {
            if (empty($serial)) continue;

            // Check if already in current list
            if (in_array($serial, $this->enteredSerials)) {
                $duplicates++;
                continue;
            }

            // Check if we have space
            if (count($this->enteredSerials) >= $this->requiredSerials) {
                break;
            }

            $this->enteredSerials[] = $serial;
            $added++;
        }

        $this->bulkSerialInput = '';

        if ($added > 0) {
            $this->success("Added {$added} serial numbers!" . ($duplicates > 0 ? " ({$duplicates} duplicates skipped)" : ""));
        } else {
            $this->warning('No new serial numbers were added.');
        }
    }

    public function selectWalkInCustomer()
    {
        // Don't allow walk-in customers for serial tracking items
        if ($this->hasSerialTrackingItems()) {
            $this->error('Walk-in customers not allowed for items requiring serial number tracking. Please create a customer record.');
            return;
        }

        // Set a default walk-in customer or null for walk-in sales
        $this->selectedCustomer = null; // or set to a default walk-in customer ID
        $this->success('Walk-in customer selected');
    }

    // ===== EXISTING POS METHODS (UPDATED) =====
    public function updatedSearchProduct()
    {
        if (strlen($this->searchProduct) >= 2) {
            $this->searchResults = Product::where('status', 'active')
                ->where(function ($query) {
                    $query->where('name', 'like', '%' . $this->searchProduct . '%')
                        ->orWhere('sku', 'like', '%' . $this->searchProduct . '%')
                        ->orWhere('barcode', 'like', '%' . $this->searchProduct . '%');
                })
                ->with(['inventory' => function ($query) {
                    $query->where('warehouse_id', $this->selectedWarehouse);
                }])
                ->limit(10)
                ->get()
                ->toArray();
        } else {
            $this->searchResults = [];
        }
    }

    /**
     * Select price for individual cart item
     */
    public function selectPrice($priceType)
    {
        if (!$this->selectedCartIndex || !isset($this->availablePrices[$priceType])) {
            return;
        }

        $newPrice = $this->availablePrices[$priceType]['value'];
        $priceLabel = $this->availablePrices[$priceType]['label']; // Store label before clearing

        $this->cartItems[$this->selectedCartIndex]['price'] = $newPrice;
        $this->cartItems[$this->selectedCartIndex]['subtotal'] =
            $this->cartItems[$this->selectedCartIndex]['quantity'] * $newPrice;

        $this->updateCartTotals();
        $this->showPriceModal = false;
        $this->selectedCartIndex = null;
        $this->availablePrices = []; // Clear after storing the label

        $this->success('Price updated to ' . $priceLabel . ': ₱' . number_format($newPrice, 2));
    }

    /**
     * Open bulk price selection for all cart items
     */
    public function openBulkPriceSelection()
    {
        if (empty($this->cartItems)) {
            $this->error('Cart is empty.');
            return;
        }

        $this->bulkPriceType = 'selling_price';
        $this->showBulkPriceModal = true;
    }

    /**
     * Apply bulk price change to all compatible items
     */
    public function applyBulkPrice()
    {
        if (empty($this->cartItems)) {
            return;
        }

        $updatedCount = 0;

        foreach ($this->cartItems as $cartKey => $item) {
            $product = Product::find($item['product_id']);
            if (!$product) continue;

            $newPrice = null;
            switch ($this->bulkPriceType) {
                case 'selling_price':
                    $newPrice = $product->selling_price;
                    break;
                case 'wholesale_price':
                    $newPrice = $product->wholesale_price;
                    break;
                case 'alt_price1':
                    $newPrice = $product->alt_price1;
                    break;
                case 'alt_price2':
                    $newPrice = $product->alt_price2;
                    break;
                case 'alt_price3':
                    $newPrice = $product->alt_price3;
                    break;
            }

            if ($newPrice > 0) {
                $this->cartItems[$cartKey]['price'] = $newPrice;
                $this->cartItems[$cartKey]['subtotal'] =
                    $this->cartItems[$cartKey]['quantity'] * $newPrice;
                $updatedCount++;
            }
        }

        $this->updateCartTotals();
        $this->showBulkPriceModal = false;

        if ($updatedCount > 0) {
            $this->success($updatedCount . ' item(s) price updated successfully!');
        } else {
            $this->warning('No items were updated. Selected price type may not be available for current products.');
        }
    }

    /**
     * Enhanced addToCart with price selection option
     */
    public function addToCartWithPriceSelection($productId)
    {
        $product = Product::find($productId);
        if (!$product) {
            $this->error('Product not found.');
            return;
        }

        $availablePrices = $product->getAvailablePrices();

        // If only one price is available, add directly
        if (count($availablePrices) <= 1) {
            $this->addToCart($productId);
            return;
        }

        // Show price selection modal
        $this->pendingProductId = $productId;
        $this->availablePrices = $availablePrices;
        $this->showAddPriceModal = true;
    }

    public function addToCartWithPrice($priceType)
    {
        if (!$this->pendingProductId || !isset($this->availablePrices[$priceType])) {
            return;
        }

        $product = Product::with(['inventory' => function ($query) {
            $query->where('warehouse_id', $this->selectedWarehouse);
        }])->find($this->pendingProductId);

        if (!$product) {
            $this->error('Product not found.');
            return;
        }

        $inventory = $product->inventory->first();
        $availableStock = $inventory ? $inventory->quantity_available : 0;

        if ($availableStock <= 0) {
            $this->error('Product is out of stock.');
            return;
        }

        $selectedPrice = $this->availablePrices[$priceType]['value'];
        $priceLabel = $this->availablePrices[$priceType]['label']; // Store label before clearing
        $cartKey = $this->pendingProductId;

        if (isset($this->cartItems[$cartKey])) {
            if ($this->cartItems[$cartKey]['quantity'] >= $availableStock) {
                $this->error('Cannot add more items. Stock limit reached.');
                return;
            }
            $this->cartItems[$cartKey]['quantity']++;
            $this->cartItems[$cartKey]['subtotal'] =
                $this->cartItems[$cartKey]['quantity'] * $this->cartItems[$cartKey]['price'];
        } else {
            $this->cartItems[$cartKey] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => $selectedPrice,
                'quantity' => 1,
                'available_stock' => $availableStock,
                'subtotal' => $selectedPrice,
            ];
        }

        $this->updateCartTotals();
        $this->searchProduct = '';
        $this->searchResults = [];
        $this->showAddPriceModal = false;
        $this->pendingProductId = null;
        $this->availablePrices = []; // Clear after storing the label

        $this->success('Item added to cart with ' . $priceLabel . ': ₱' . number_format($selectedPrice, 2));
    }

    public function updateQuantity($cartKey, $quantity)
    {
        if ($quantity <= 0) {
            $this->removeFromCart($cartKey);
            return;
        }

        if (isset($this->cartItems[$cartKey])) {
            $availableStock = $this->cartItems[$cartKey]['available_stock'];

            if ($quantity > $availableStock) {
                $this->error('Quantity exceeds available stock (' . $availableStock . ')');
                return;
            }

            $this->cartItems[$cartKey]['quantity'] = $quantity;
            $this->cartItems[$cartKey]['subtotal'] = $this->cartItems[$cartKey]['price'] * $quantity;

            if ($this->cartItems[$cartKey]['track_serial'] ?? false) {
                $this->cartItems[$cartKey]['serial_numbers'] = [];
                $this->warning('Quantity changed. Please re-enter serial numbers.');
            }

            $this->updateCartTotals();
        }
    }

    public function recalculateDiscount()
    {
        // Only recalculate if there's an active discount
        if ($this->discountAmount > 0) {
            $this->discountAmount = $this->calculateDiscountAmount();
        }
    }

    public function applyDiscount()
    {
        $this->validate([
            'discountType' => 'required|in:percentage,fixed,senior,pwd',
            'discountValue' => 'required_if:discountType,percentage,fixed|nullable|numeric|min:0',
        ]);

        if ($this->discountType === 'percentage' && $this->discountValue > 100) {
            $this->error('Percentage discount cannot exceed 100%');
            return;
        }

        $this->discountAmount = $this->calculateDiscountAmount();

        $this->updateCartTotals();
        $this->showDiscountModal = false;
        $this->success('Discount applied successfully!');
    }

    public function updatedDiscountType()
    {
        if (in_array($this->discountType, ['senior', 'pwd'], true)) {
            $this->discountValue = 20;
        } else {
            $this->discountValue = '';
        }
    }

    public function calculateDiscountAmount(): float
    {
        return match ($this->discountType) {
            'percentage' => $this->subtotal * ((float) $this->discountValue / 100),
            'fixed' => min((float) $this->discountValue, $this->subtotal),
            'senior', 'pwd' => $this->subtotal * 0.20,
            default => 0,
        };
    }

    public function discountLabel(): string
    {
        return match ($this->discountType) {
            'percentage' => (float) $this->discountValue . '%',
            'fixed' => '₱' . number_format((float) $this->discountValue, 2),
            'senior' => 'Senior Citizen (20%)',
            'pwd' => 'PWD (20%)',
            default => 'Discount',
        };
    }

    public function updatedPaidAmount()
    {
        $this->calculateChange();
    }

    public function updatedPaymentMethod()
    {
        if ($this->paymentMethod === 'terms') {
            $this->changeAmount = 0;
            $this->paymentDueDate = $this->paymentDueDate ?: now()->addDays(30)->format('Y-m-d');
            return;
        }

        $this->paymentTerms = 'Due on receipt';
        $this->paymentDueDate = '';
        $this->calculateChange();
    }

    public function updatedPaymentTerms()
    {
        if ($this->paymentMethod !== 'terms') {
            return;
        }

        $days = match ($this->paymentTerms) {
            'Net 7' => 7,
            'Net 15' => 15,
            'Net 30' => 30,
            'Net 60' => 60,
            default => 0,
        };

        $this->paymentDueDate = now()->addDays($days)->format('Y-m-d');
    }

    public function calculateChange()
    {
        $this->changeAmount = $this->paymentMethod === 'terms'
            ? 0
            : max(0, (float) $this->paidAmount - $this->totalAmount);
    }

    #[On('open-payment-modal')]
    public function openPaymentModal()
    {
        if (empty($this->cartItems)) {
            $this->error('Cart is empty. Add items first.');
            return;
        }

        // Check if customer is selected for items requiring serial tracking
        $serialTrackingItems = array_filter($this->cartItems, function ($item) {
            // Only check products, skip services
            if ($item['item_type'] !== 'product' || !$item['product_id']) {
                return false;
            }

            $product = Product::find($item['product_id']);
            return $product && $product->track_serial;
        });

        if (!empty($serialTrackingItems) && !$this->selectedCustomer) {
            $this->error('Please select a customer before proceeding. This sale contains items that require serial number tracking.');
            return;
        }

        // Check for missing serial numbers
        $missingSerials = $this->checkSerialRequirements();
        if (!empty($missingSerials)) {
            $errorMessage = "Serial numbers required for:\n";
            foreach ($missingSerials as $missing) {
                $errorMessage .= "• {$missing['name']}: {$missing['current']}/{$missing['required']} entered\n";
            }
            $this->error($errorMessage);
            return;
        }

        $this->paidAmount = '';
        $this->paymentMethod = 'cash';
        $this->paymentTerms = 'Due on receipt';
        $this->paymentDueDate = '';
        $this->saleNotes = '';
        $this->showPaymentModal = true;
    }
    public function completeSale()
    {
        $this->paidAmount = (float) ($this->paidAmount ?: 0);
        $usesPaymentTerms = $this->paymentMethod === 'terms';

        if (!$usesPaymentTerms && $this->paidAmount < $this->totalAmount) {
            $this->error('Insufficient payment amount.');
            return;
        }

        if ($usesPaymentTerms && !$this->selectedCustomer) {
            $this->error('Please select a customer before using payment terms.');
            return;
        }

        if ($usesPaymentTerms && empty($this->paymentDueDate)) {
            $this->error('Please set a due date for the payment terms.');
            return;
        }

        if ($usesPaymentTerms && $this->paidAmount > $this->totalAmount) {
            $this->error('Amount received cannot exceed the invoice total for payment terms.');
            return;
        }

        $paymentStatus = $this->paidAmount <= 0
            ? 'unpaid'
            : ($this->paidAmount < $this->totalAmount ? 'partial' : 'paid');
        $this->changeAmount = $usesPaymentTerms ? 0 : $this->changeAmount;

        try {
            DB::beginTransaction();

            // Check inventory for products only WITHOUT locking - immediate response
            $inventoryIssues = [];
            $inventorySnapshots = [];

            foreach ($this->cartItems as $cartKey => $item) {
                // Skip inventory checks for services
                if ($item['item_type'] === 'service') {
                    continue;
                }

                $currentInventory = Inventory::where('product_id', $item['product_id'])
                    ->where('warehouse_id', $this->selectedWarehouse)
                    ->first();

                $availableQty = $currentInventory ? $currentInventory->quantity_available : 0;

                // Store current state for later verification
                $inventorySnapshots[$item['product_id']] = [
                    'current_quantity' => $currentInventory ? $currentInventory->quantity_on_hand : 0,
                    'updated_at' => $currentInventory ? $currentInventory->updated_at : null
                ];

                if ($availableQty < $item['quantity']) {
                    $inventoryIssues[] = [
                        'product' => $item['name'],
                        'requested' => $item['quantity'],
                        'available' => $availableQty
                    ];
                }
            }

            // If insufficient stock, fail immediately
            if (!empty($inventoryIssues)) {
                DB::rollBack();
                $this->handleInventoryConflict($inventoryIssues);
                return;
            }

            // Create sale record
            $sale = Sale::create([
                'customer_id' => $this->selectedCustomer,
                'promotion_code' => $this->discountAmount > 0 ? $this->discountLabel() : null,
                'invoice_type' => $this->invoiceType,
                'tax_type' => $this->taxType,
                'tax_rate' => $this->taxRate,
                'warehouse_id' => $this->selectedWarehouse,
                'user_id' => auth()->id(),
                'subtotal' => $this->subtotal,
                'discount_amount' => $this->discountAmount,
                'tax_amount' => $this->taxAmount,
                'total_amount' => $this->totalAmount,
                'paid_amount' => $this->paidAmount,
                'change_amount' => $this->changeAmount,
                'payment_method' => $this->paymentMethod,
                'payment_terms' => $usesPaymentTerms ? $this->paymentTerms : null,
                'due_date' => $usesPaymentTerms ? $this->paymentDueDate : null,
                'payment_status' => $usesPaymentTerms ? $paymentStatus : 'paid',
                'status' => 'completed',
                'notes' => $this->saleNotes,
                'completed_at' => now(),
            ]);

            // Process cart items (both products and services)
            foreach ($this->cartItems as $cartKey => $item) {
                if ($item['item_type'] === 'product') {
                    // Handle product sales
                    $this->processProductSale($sale, $item, $inventorySnapshots);
                } else {
                    // Handle service sales
                    $this->processServiceSale($sale, $item);
                }
            }

            // Update customer stats
            if ($this->selectedCustomer) {
                $customer = Customer::find($this->selectedCustomer);
                $customer->increment('total_orders');
                $customer->increment('total_purchases', $this->totalAmount);
                $customer->update(['last_purchase_at' => now()]);
            }

            DB::commit();

            $this->success(($usesPaymentTerms ? 'Invoice completed with payment terms! Invoice: ' : 'Invoice completed successfully! Invoice: ') . $sale->invoice_number);
            $this->resetSale();
            $this->showPaymentModal = false;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error completing sale: ' . $e->getMessage());
        }
    }

    private function processProductSale($sale, $item, $inventorySnapshots)
    {
        $product = Product::find($item['product_id']);

        // Create sale item
        $saleItem = SaleItem::create([
            'sale_id' => $sale->id,
            'item_type' => 'product',
            'product_id' => $item['product_id'],
            'service_id' => null,
            'product_name' => $item['name'],
            'product_sku' => $item['code'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['price'],
            'total_price' => $item['subtotal'],
            'cost_price' => $product->cost_price ?? 0,
        ]);

        // Handle serial numbers if product tracks serials
        if (!empty($item['serial_numbers'])) {
            $saleItem->serial_numbers = $item['serial_numbers'];
            $saleItem->save();

            // Create serial number records for each serial entered
            foreach ($item['serial_numbers'] as $serialNumber) {
                // Check if serial already exists
                $existingSerial = SerialNumber::where('serial_number', $serialNumber)
                    ->where('product_id', $product->id)
                    ->first();

                if ($existingSerial) {
                    // Update existing serial
                    $existingSerial->update([
                        'status' => 'sold',
                        'sold_to_customer_id' => $sale->customer_id,
                        'sold_at' => $sale->completed_at,
                        'warehouse_id' => $this->selectedWarehouse
                    ]);
                } else {
                    // Create new serial record
                    SerialNumber::create([
                        'product_id' => $product->id,
                        'warehouse_id' => $this->selectedWarehouse,
                        'serial_number' => $serialNumber,
                        'status' => 'sold',
                        'sold_to_customer_id' => $sale->customer_id,
                        'sold_at' => $sale->completed_at,
                        'warranty_expires_at' => $product->warranty_months ?
                            $sale->completed_at->addMonths($product->warranty_months) : null,
                        'notes' => 'Created during sale #' . $sale->invoice_number
                    ]);
                }
            }
        }

        // Optimistic update with version check
        $snapshot = $inventorySnapshots[$item['product_id']];

        $updateResult = DB::table('inventories')
            ->where('product_id', $item['product_id'])
            ->where('warehouse_id', $this->selectedWarehouse)
            ->where('quantity_on_hand', $snapshot['current_quantity']) // Ensure quantity hasn't changed
            ->where('updated_at', $snapshot['updated_at']) // Ensure record hasn't been modified
            ->update([
                'quantity_on_hand' => $snapshot['current_quantity'] - $item['quantity'],
                'updated_at' => now()
            ]);

        // If update failed, someone else modified the inventory
        if ($updateResult === 0) {
            DB::rollBack();
            $this->error('Inventory was modified by another user. Please refresh and try again.');
            $this->refreshCartInventory();
            return;
        }

        // Create stock movement
        StockMovement::create([
            'product_id' => $item['product_id'],
            'warehouse_id' => $this->selectedWarehouse,
            'type' => 'sale',
            'quantity_before' => $snapshot['current_quantity'],
            'quantity_changed' => -$item['quantity'],
            'quantity_after' => $snapshot['current_quantity'] - $item['quantity'],
            'unit_cost' => $product->cost_price ?? 0,
            'reference_id' => $sale->id,
            'reference_type' => Sale::class,
            'user_id' => auth()->id(),
            'notes' => 'Sale: ' . $sale->invoice_number,
        ]);
    }

    private function processServiceSale($sale, $item)
    {
        $service = ProductService::find($item['service_id']);

        // Create sale item for service
        $saleItem = SaleItem::create([
            'sale_id' => $sale->id,
            'item_type' => 'service',
            'product_id' => null,
            'service_id' => $item['service_id'],
            'product_name' => $item['name'], // Keep for compatibility
            'product_sku' => $item['code'],  // Keep for compatibility
            'quantity' => $item['quantity'],
            'unit_price' => $item['price'],
            'total_price' => $item['subtotal'],
            'cost_price' => 0, // Services typically have no cost
        ]);

        // Services don't require inventory updates or stock movements
        // They also don't have serial numbers
    }

    // Updated helper method for mixed cart
    public function hasSerialTrackingItems()
    {
        foreach ($this->cartItems as $item) {
            if (isset($item['item_type']) && $item['item_type'] === 'product' && isset($item['track_serial']) && $item['track_serial']) {
                return true;
            }
        }
        return false;
    }

    // Updated validation for mixed cart
    public function validateCustomerForSerials()
    {
        if ($this->hasSerialTrackingItems() && !$this->selectedCustomer) {
            return false;
        }
        return true;
    }

    // Refresh inventory for products only
    private function refreshCartInventory()
    {
        foreach ($this->cartItems as $cartKey => $item) {
            if ($item['item_type'] === 'product') {
                $currentInventory = Inventory::where('product_id', $item['product_id'])
                    ->where('warehouse_id', $this->selectedWarehouse)
                    ->first();

                $availableQty = $currentInventory ? $currentInventory->quantity_available : 0;

                // Update cart item quantity if it exceeds available stock
                if ($this->cartItems[$cartKey]['quantity'] > $availableQty) {
                    $this->cartItems[$cartKey]['quantity'] = max(0, $availableQty);
                    $this->cartItems[$cartKey]['subtotal'] = $this->cartItems[$cartKey]['quantity'] * $this->cartItems[$cartKey]['price'];
                }

                // Update available stock info
                $this->cartItems[$cartKey]['available_stock'] = $availableQty;
            }
        }

        // Remove items with zero quantity
        $this->cartItems = array_filter($this->cartItems, fn($item) => $item['quantity'] > 0);

        $this->updateCartTotals();
    }

    private function handleInventoryConflict($inventoryIssues)
    {
        $errorMessage = "❌ Insufficient inventory detected:\n\n";
        foreach ($inventoryIssues as $issue) {
            $errorMessage .= "• {$issue['product']}: Need {$issue['requested']}, Only {$issue['available']} available\n";
        }
        $errorMessage .= "\n🔄 Cart has been updated with current stock levels.";

        $this->error($errorMessage);
        $this->refreshCartInventory();
    }

    public function validateCartItems()
    {
        $hasChanges = false;

        foreach ($this->cartItems as $index => $item) {
            $currentInventory = Inventory::where('product_id', $item['product_id'])
                ->where('warehouse_id', $this->selectedWarehouse)
                ->first();

            $availableQty = $currentInventory ? $currentInventory->quantity_available : 0;

            if ($item['quantity'] > $availableQty) {
                $this->cartItems[$index]['quantity'] = max(0, $availableQty);
                $this->cartItems[$index]['subtotal'] = $this->cartItems[$index]['quantity'] * $this->cartItems[$index]['price'];
                $hasChanges = true;
            }
        }

        if ($hasChanges) {
            $this->cartItems = array_filter($this->cartItems, fn($item) => $item['quantity'] > 0);
            $this->cartItems = array_values($this->cartItems);
            $this->calculateTotals();
            $this->warning('Cart updated due to inventory changes by other users.');
        }
    }

    public function resetSale()
    {
        $this->cartItems = [];
        $this->selectedCustomer = null;
        $this->invoiceType = 'sales';
        $this->taxType = 'vat_12';
        $this->taxRate = 12;
        $this->discountType = 'percentage';
        $this->discountValue = '';
        $this->discountAmount = 0;
        $this->paidAmount = 0;
        $this->changeAmount = 0;
        $this->paymentMethod = 'cash';
        $this->paymentTerms = 'Due on receipt';
        $this->paymentDueDate = '';
        $this->saleNotes = '';
        $this->updateCartTotals();
    }

    public function scanBarcode($barcode)
    {
        $product = Product::where('barcode', $barcode)
            ->where('status', 'active')
            ->first();

        if ($product) {
            $this->addToCart($product->id);
        } else {
            $this->error('Product not found with barcode: ' . $barcode);
        }
    }

    /**
     * Set quick cash amount for payment
     */
    public function setQuickCash($amount)
    {
        if (count($this->cartItems) === 0) {
            $this->addError('cart', 'Cart is empty.');
            return;
        }

        $this->paidAmount = $amount;
        $this->paymentMethod = 'cash';

        // Auto-open payment modal if total is less than or equal to quick cash amount
        if ($this->totalAmount <= $amount) {
            $this->calculateChange();
            $this->showPaymentModal = true;
        } else {
            $this->error('Insufficient Payment', 'Set quick cash is less than the order`s total amount.');
        }
    }

    /**
     * Set exact cash amount (same as total)
     */
    public function setExactCash()
    {
        if (count($this->cartItems) === 0) {
            $this->addError('cart', 'Cart is empty.');
            return;
        }

        $this->paidAmount = $this->totalAmount;
        $this->paymentMethod = 'cash';

        // Auto-open payment modal
        $this->showPaymentModal = true;
    }

    // ===== BARCODE SCANNING METHODS =====
    #[On('open-barcode-modal')]
    public function openBarcodeModal()
    {
        $this->barcodeInput = '';
        $this->scannedItems = [];
        $this->showBarcodeModal = true;

        // Dispatch event to focus input (handled by JavaScript)
        $this->dispatch('barcode-modal-opened');
    }

    public function processBarcodeInput()
    {
        if (empty($this->barcodeInput)) {
            return;
        }

        $product = Product::where('barcode', $this->barcodeInput)
            ->where('status', 'active')
            ->with(['inventory' => function ($query) {
                $query->where('warehouse_id', $this->selectedWarehouse);
            }])
            ->first();

        if ($product) {
            $inventory = $product->inventory->first();
            $availableStock = $inventory ? $inventory->quantity_available : 0;

            if ($availableStock <= 0) {
                $this->error('Product out of stock: ' . $product->name);
                $this->barcodeInput = '';
                return;
            }

            // Check if item already scanned in this batch
            $existingIndex = null;
            foreach ($this->scannedItems as $index => $item) {
                if ($item['product_id'] == $product->id) {
                    $existingIndex = $index;
                    break;
                }
            }

            if ($existingIndex !== null) {
                if ($this->scannedItems[$existingIndex]['quantity'] >= $availableStock) {
                    $this->error('Cannot add more. Stock limit: ' . $availableStock);
                    $this->barcodeInput = '';
                    return;
                }
                $this->scannedItems[$existingIndex]['quantity']++;
                $this->scannedItems[$existingIndex]['subtotal'] =
                    $this->scannedItems[$existingIndex]['quantity'] * $this->scannedItems[$existingIndex]['price'];
            } else {
                // Add new item to scanned batch with default selling price
                $this->scannedItems[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'price' => $product->selling_price, // Uses selling price by default
                    'quantity' => 1,
                    'available_stock' => $availableStock,
                    'subtotal' => $product->selling_price,
                ];
            }

            $this->success('Scanned: ' . $product->name);
        } else {
            $this->error('Product not found: ' . $this->barcodeInput);
        }

        $this->barcodeInput = '';
    }

    public function addScannedItemsToCart()
    {
        if (empty($this->scannedItems)) {
            $this->error('No items scanned yet.');
            return;
        }

        $addedCount = 0;
        foreach ($this->scannedItems as $scannedItem) {
            $cartKey = $scannedItem['product_id'];

            if (isset($this->cartItems[$cartKey])) {
                // Update existing cart item
                $this->cartItems[$cartKey]['quantity'] += $scannedItem['quantity'];
                $this->cartItems[$cartKey]['subtotal'] =
                    $this->cartItems[$cartKey]['quantity'] * $this->cartItems[$cartKey]['price'];
            } else {
                // Add as new cart item
                $this->cartItems[$cartKey] = $scannedItem;
            }
            $addedCount++;
        }

        $this->updateCartTotals();
        $this->scannedItems = [];
        $this->showBarcodeModal = false;
        $this->success($addedCount . ' item(s) added to cart successfully!');
    }

    public function removeScannedItem($index)
    {
        if (isset($this->scannedItems[$index])) {
            $itemName = $this->scannedItems[$index]['name'];
            unset($this->scannedItems[$index]);
            $this->scannedItems = array_values($this->scannedItems); // Re-index array
            $this->success('Removed: ' . $itemName);
        }
    }

    public function updateScannedItemQuantity($index, $quantity)
    {
        if (isset($this->scannedItems[$index])) {
            if ($quantity <= 0) {
                $this->removeScannedItem($index);
                return;
            }

            $maxStock = $this->scannedItems[$index]['available_stock'];
            if ($quantity > $maxStock) {
                $this->error('Quantity exceeds available stock (' . $maxStock . ')');
                return;
            }

            $this->scannedItems[$index]['quantity'] = $quantity;
            $this->scannedItems[$index]['subtotal'] =
                $this->scannedItems[$index]['quantity'] * $this->scannedItems[$index]['price'];
        }
    }

    public function updatedBarcodeInput()
    {
        // Auto-process when barcode is entered (typical barcode length is 8-13 characters)
        if (strlen($this->barcodeInput) >= 8) {
            $this->processBarcodeInput();
        }
    }

    public function clearBarcodeInput()
    {
        $this->barcodeInput = '';
    }

    public function clearScannedItems()
    {
        $this->scannedItems = [];
        $this->success('Scanned items cleared.');
    }

    // ===== NEW CUSTOMER METHODS =====
    public function openCustomerModal()
    {
        $this->resetCustomerForm();
        $this->showCustomerModal = true;
    }

    public function createCustomer()
    {
        $this->validate([
            'customerName' => 'required|string|max:255',
            'customerEmail' => 'nullable|email|unique:customers,email',
            'customerPhone' => 'nullable|string|max:20',
        ]);

        try {
            $customer = Customer::create([
                'name' => $this->customerName,
                'email' => $this->customerEmail,
                'phone' => $this->customerPhone,
                'address' => $this->customerAddress,
                'type' => 'individual',
                'is_active' => true,
            ]);

            $this->selectedCustomer = $customer->id;
            $this->showCustomerModal = false;
            $this->success('New customer created and selected: ' . $customer->name);
            $this->resetCustomerForm();
        } catch (\Exception $e) {
            $this->error('Error creating customer: ' . $e->getMessage());
        }
    }

    private function resetCustomerForm()
    {
        $this->customerName = '';
        $this->customerEmail = '';
        $this->customerPhone = '';
        $this->customerAddress = '';
    }

    // ===== CUSTOMER SEARCH METHODS =====
    public function openSearchCustomerModal()
    {
        $this->customerSearch = '';
        $this->customerSearchResults = [];
        $this->showSearchCustomerModal = true;
    }

    public function searchCustomers()
    {
        if (strlen($this->customerSearch) >= 2) {
            $this->customerSearchResults = Customer::where('is_active', true)
                ->where(function ($query) {
                    $query->where('name', 'like', '%' . $this->customerSearch . '%')
                        ->orWhere('email', 'like', '%' . $this->customerSearch . '%')
                        ->orWhere('phone', 'like', '%' . $this->customerSearch . '%');
                })
                ->limit(10)
                ->get()
                ->toArray();
        } else {
            $this->customerSearchResults = [];
        }
    }

    public function selectSearchedCustomer($customerId)
    {
        $this->selectedCustomer = $customerId;
        $this->showSearchCustomerModal = false;
        $customer = Customer::find($customerId);
        $this->success('Customer selected: ' . $customer->name);
    }

    public function updatedCustomerSearch()
    {
        $this->searchCustomers();
    }

    // ===== DISCOUNT METHODS =====
    public function openDiscountModal()
    {
        if (empty($this->cartItems)) {
            $this->error('Cart is empty. Add items first.');
            return;
        }
        $this->discountType = 'percentage';
        $this->discountValue = '';
        $this->showDiscountModal = true;
    }

    public function removeDiscount()
    {
        $this->discountType = 'percentage';
        $this->discountValue = '';
        $this->discountAmount = 0;
        $this->updateCartTotals();
        $this->success('Discount removed!');
    }

    // ===== HOLD SALE METHODS =====
    #[On('open-hold-sale-modal')]
    public function openHoldSaleModal()
    {
        if (empty($this->cartItems)) {
            $this->error('Cart is empty. Add items first.');
            return;
        }
        $this->holdReference = 'HOLD-' . date('YmdHis');
        $this->holdNotes = '';
        $this->showHoldSaleModal = true;
    }

    public function holdSale()
    {
        $this->validate([
            'holdReference' => 'required|string|max:255',
        ]);

        try {
            // Create a held sale record
            $heldSale = Sale::create([
                'invoice_number' => $this->holdReference,
                'customer_id' => $this->selectedCustomer,
                'promotion_code' => $this->discountAmount > 0 ? $this->discountLabel() : null,
                'invoice_type' => $this->invoiceType,
                'tax_type' => $this->taxType,
                'tax_rate' => $this->taxRate,
                'warehouse_id' => $this->selectedWarehouse,
                'user_id' => auth()->id(),
                'subtotal' => $this->subtotal,
                'discount_amount' => $this->discountAmount,
                'tax_amount' => $this->taxAmount,
                'total_amount' => $this->totalAmount,
                'paid_amount' => 0,
                'change_amount' => 0,
                'payment_method' => 'cash',
                'status' => 'draft',
                'notes' => 'HELD SALE: ' . $this->holdNotes,
                'completed_at' => null,
            ]);

            // Create sale items
            foreach ($this->cartItems as $item) {
                $product = $item['item_type'] === 'product' ? Product::find($item['product_id']) : null;

                SaleItem::create([
                    'sale_id' => $heldSale->id,
                    'item_type' => $item['item_type'],
                    'product_id' => $item['product_id'],
                    'service_id' => $item['service_id'] ?? null,
                    'product_name' => $item['name'],
                    'product_sku' => $item['code'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                    'total_price' => $item['subtotal'],
                    'cost_price' => $product->cost_price ?? 0,
                ]);
            }

            $this->success('Invoice held successfully! Reference: ' . $this->holdReference);
            $this->resetSale();
            $this->showHoldSaleModal = false;
        } catch (\Exception $e) {
            $this->error('Error holding sale: ' . $e->getMessage());
        }
    }

    public function loadHeldSales()
    {
        $this->heldSales = Sale::where('status', 'draft')
            ->where('user_id', auth()->id()) // Only show current user's held sales
            ->with(['customer', 'items'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($sale) {
                return [
                    'id' => $sale->id,
                    'invoice_number' => $sale->invoice_number,
                    'customer_name' => $sale->customer?->name ?? 'Walk-in Customer',
                    'total_amount' => $sale->total_amount,
                    'items_count' => $sale->items->count(),
                    'created_at' => $sale->created_at->format('M d, Y H:i'),
                    'notes' => $sale->notes,
                ];
            })
            ->toArray();
    }

    #[On('open-held-sales-modal')]
    public function openHeldSalesModal()
    {
        $this->loadHeldSales();
        $this->showHeldSalesModal = true;
    }

    public function retrieveHeldSale($saleId)
    {
        try {
            $heldSale = Sale::with(['customer', 'items.product', 'items.service'])->find($saleId);

            if (!$heldSale || $heldSale->status !== 'draft') {
                $this->error('Held sale not found or already processed.');
                return;
            }

            // Clear current cart
            $this->resetSale();

            // Load held sale data
            $this->selectedCustomer = $heldSale->customer_id;
            $this->selectedWarehouse = $heldSale->warehouse_id;
            $this->invoiceType = $heldSale->invoice_type ?? 'sales';
            $this->taxType = $heldSale->tax_type ?? 'vat_12';
            $this->taxRate = (float) ($heldSale->tax_rate ?? $this->taxRateForType($this->taxType));
            $this->discountAmount = $heldSale->discount_amount;
            $this->saleNotes = str_replace('HELD SALE: ', '', $heldSale->notes);

            // Load cart items from held sale
            foreach ($heldSale->items as $item) {
                if ($item->item_type === 'product' && $item->product) {
                    $product = $item->product;
                    $inventory = $product->inventory()
                        ->where('warehouse_id', $this->selectedWarehouse)
                        ->first();

                    $availableStock = $inventory ? $inventory->quantity_available : 0;

                    $this->cartItems['product_' . $product->id] = [
                        'item_type' => 'product',
                        'product_id' => $product->id,
                        'service_id' => null,
                        'name' => $product->name,
                        'code' => $product->sku,
                        'price' => $item->unit_price,
                        'quantity' => $item->quantity,
                        'available_stock' => $availableStock,
                        'subtotal' => $item->total_price,
                        'track_serial' => $product->track_serial,
                        'serial_numbers' => [],
                    ];
                } elseif ($item->item_type === 'service' && $item->service) {
                    $service = $item->service;
                    $this->cartItems['service_' . $service->id] = [
                        'item_type' => 'service',
                        'product_id' => null,
                        'service_id' => $service->id,
                        'name' => $service->name,
                        'code' => $service->code,
                        'price' => $item->unit_price,
                        'quantity' => $item->quantity,
                        'subtotal' => $item->total_price,
                    ];
                }
            }

            $this->updateCartTotals();

            // Delete the held sale record since we're resuming it
            $heldSale->delete();

            $this->showHeldSalesModal = false;
            $this->success('Held invoice retrieved successfully! Reference: ' . $heldSale->invoice_number);
        } catch (\Exception $e) {
            $this->error('Error retrieving held sale: ' . $e->getMessage());
        }
    }

    public function deleteHeldSale($saleId)
    {
        try {
            $heldSale = Sale::find($saleId);

            if (!$heldSale || $heldSale->status !== 'draft') {
                $this->error('Held sale not found or already processed.');
                return;
            }

            $heldSale->delete();
            $this->loadHeldSales();
            $this->success('Held sale deleted successfully!');
        } catch (\Exception $e) {
            $this->error('Error deleting held sale: ' . $e->getMessage());
        }
    }

    public function updatedSearchService()
    {
        if (strlen($this->searchService) >= 2) {
            $this->serviceResults = ProductService::active()
                ->where(function ($query) {
                    $query->where('name', 'like', '%' . $this->searchService . '%')
                        ->orWhere('code', 'like', '%' . $this->searchService . '%');
                })
                ->orderBy('name')
                ->limit(10)
                ->get();
        } else {
            $this->serviceResults = [];
        }
    }

    public function openServiceModal()
    {
        $this->showServiceModal = true;
        $this->searchService = '';
        $this->serviceResults = [];
    }

    public function closeServiceModal()
    {
        $this->showServiceModal = false;
        $this->searchService = '';
        $this->serviceResults = [];
    }

    public function updateCartItemQuantity($cartKey, $newQuantity)
    {
        if (!isset($this->cartItems[$cartKey])) {
            return;
        }

        $newQuantity = max(1, intval($newQuantity));
        $item = $this->cartItems[$cartKey];

        // Check stock limit for products only
        if ($item['item_type'] === 'product' && $newQuantity > ($item['available_stock'] ?? 0)) {
            $this->error('Cannot set quantity to ' . $newQuantity . '. Available stock: ' . ($item['available_stock'] ?? 0));
            return;
        }

        $this->cartItems[$cartKey]['quantity'] = $newQuantity;
        $this->cartItems[$cartKey]['subtotal'] = $newQuantity * $this->cartItems[$cartKey]['price'];

        $this->updateCartTotals();
    }

    public function updatePrice($cartKey, $newPrice)
    {
        if (!isset($this->cartItems[$cartKey])) {
            return;
        }

        // Only allow price changes for products, not services
        if ($this->cartItems[$cartKey]['item_type'] === 'service') {
            $this->error('Service prices cannot be modified.');
            return;
        }

        $newPrice = max(0, floatval($newPrice));
        $this->cartItems[$cartKey]['price'] = $newPrice;
        $this->cartItems[$cartKey]['subtotal'] = $this->cartItems[$cartKey]['quantity'] * $newPrice;

        $this->updateCartTotals();
    }

    public function openPriceSelection($cartKey)
    {
        if (!isset($this->cartItems[$cartKey])) {
            return;
        }

        // Only allow price selection for products
        if ($this->cartItems[$cartKey]['item_type'] === 'service') {
            $this->error('Service prices cannot be modified.');
            return;
        }

        $this->selectedCartKey = $cartKey;
        $this->showPriceModal = true;

        // Load available prices for the product
        $product = Product::find($this->cartItems[$cartKey]['product_id']);
        $this->availablePrices = $product ? $product->getAvailablePrices() : [];
    }

    #[On('clear-cart')]
    public function clearCart()
    {
        $this->cartItems = [];
        $this->updateCartTotals();
        $this->success('Cart cleared.');
    }

    public function openSerialModal($cartKey)
    {
        if (!isset($this->cartItems[$cartKey])) {
            return;
        }

        $item = $this->cartItems[$cartKey];

        // Only products can have serial numbers
        if ($item['item_type'] !== 'product' || !isset($item['track_serial']) || !$item['track_serial']) {
            return;
        }

        if (!$this->selectedCustomer) {
            $this->error('Please select a customer before entering serial numbers.');
            return;
        }

        $this->selectedCartKey = $cartKey;
        $this->requiredSerials = $item['quantity'];
        $this->enteredSerials = $item['serial_numbers'] ?? [];
        $this->showSerialModal = true;
    }

    // Update the existing updateCartTotals method to handle mixed cart
    public function updateCartTotals()
    {
        $this->subtotal = 0;

        foreach ($this->cartItems as $item) {
            $this->subtotal += $item['subtotal'];
        }

        $this->recalculateDiscount();
        $taxableAmount = $this->taxableGrossAmount();
        $this->taxAmount = $this->calculateTaxAmount($taxableAmount);
        $this->totalAmount = $this->calculateTotalAmount($taxableAmount, $this->taxAmount);
        $this->calculateChange();
    }
}
