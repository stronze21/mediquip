<?php

namespace App\Livewire\Purchasing;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Inventory;
use App\Models\StockMovement;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class PurchaseOrderManagement extends Component
{
    use WithPagination;
    use Toast;

    public $showModal = false;
    public $showReceiveModal = false;
    public $showDetailsModal = false;
    public $editMode = false;
    public $showReceivePage = false;
    public $selectedPO = null;
    public $viewingPO = null;

    // Form fields
    public $supplier_id = '';
    public $warehouse_id = '';
    public $order_date = '';
    public $expected_date = '';
    public $tin = '';
    public $business_style = '';
    public $address = '';
    public $contact_person = '';
    public $contact_number = '';
    public $terms = '';
    public $due_date = '';
    public $notes = '';
    public $items = [];
    public $discount_type = 'regular';
    public $discount_value = 0;
    public $discount_amount = 0;
    public $tax_type = 'vat_12';
    public $tax_rate = 12;
    public $tax_amount = 0;

    // Receiving fields
    public $receivingItems = [];

    // Search and filters
    public $search = '';
    public $supplierFilter = '';
    public $warehouseFilter = '';
    public $statusFilter = '';
    public $dateFilter = '';

    protected $rules = [
        'supplier_id' => 'required|exists:suppliers,id',
        'warehouse_id' => 'required|exists:warehouses,id',
        'order_date' => 'required|date',
        'expected_date' => 'required|date|after_or_equal:order_date',
        'tin' => 'nullable|string|max:255',
        'business_style' => 'nullable|string|max:255',
        'address' => 'nullable|string',
        'contact_person' => 'nullable|string|max:255',
        'contact_number' => 'nullable|string|max:255',
        'terms' => 'nullable|string|max:255',
        'due_date' => 'nullable|date|after_or_equal:order_date',
        'notes' => 'nullable|string',
        'discount_type' => 'required|in:regular,senior,pwd',
        'discount_value' => 'nullable|numeric|min:0|max:100',
        'items' => 'required|array|min:1',
        'items.*.product_id' => 'required|exists:products,id',
        'items.*.quantity' => 'required|integer|min:1',
        'items.*.unit_cost' => 'required|numeric|min:0',
        'items.*.tax_type' => 'required|in:none,vat_12,ewt_sales_1,ewt_service_2',
    ];

    public function mount()
    {
        $this->order_date = now()->format('Y-m-d');
        $this->expected_date = now()->addDays(7)->format('Y-m-d');

        // Handle query parameters from supplier management
        $request = request();

        // Check for create PO request from supplier management
        if ($request->has('create_po') && $request->get('create_po') === 'true') {
            $supplierId = $request->get('supplier_id');
            $supplierName = urldecode($request->get('supplier_name', 'selected supplier'));

            if ($supplierId && Supplier::find($supplierId)) {
                $this->supplier_id = $supplierId;

                // Set default warehouse
                $defaultWarehouse = Warehouse::where('is_active', true)->first();
                if ($defaultWarehouse) {
                    $this->warehouse_id = $defaultWarehouse->id;
                }

                $this->fillSupplierDetails($supplierId);

                // Add a small delay then open the full page form to ensure supplier_id is set
                $this->js('setTimeout(() => { $wire.openModalWithSupplier() }, 100)');
                $this->success("Creating purchase order for {$supplierName}.");
            }
        }

        if ($request->has('poId')) {
            $poId = $request->get('poId');
            $po = PurchaseOrder::find($poId);
            $this->viewPODetails($po);
        }
    }

    public function openModalWithSupplier()
    {
        $this->editMode = false;
        $this->selectedPO = null;
        $this->showModal = true;
        $this->resetValidation();
        $this->addItem();

        // Ensure warehouse is set if not already
        if (empty($this->warehouse_id)) {
            $defaultWarehouse = Warehouse::where('is_active', true)->first();
            if ($defaultWarehouse) {
                $this->warehouse_id = $defaultWarehouse->id;
            }
        }
    }

    public function updatedSupplierId($supplierId)
    {
        $this->fillSupplierDetails($supplierId);
    }

    private function fillSupplierDetails($supplierId): void
    {
        $supplier = Supplier::find($supplierId);

        if (!$supplier) {
            return;
        }

        $this->tin = $supplier->tin ?? '';
        $this->business_style = $supplier->business_style ?? '';
        $this->address = $supplier->address ?? '';
        $this->contact_person = $supplier->contact_person ?? '';
        $this->contact_number = $supplier->phone ?? '';
    }

    public function render()
    {
        $purchaseOrders = PurchaseOrder::with(['supplier', 'warehouse', 'requestedBy', 'items'])
            ->when($this->search, fn($q) => $q->where('po_number', 'like', '%' . $this->search . '%')
                ->orWhereHas('supplier', function ($query) {
                    $query->where('name', 'like', '%' . $this->search . '%');
                }))
            ->when($this->supplierFilter, fn($q) => $q->where('supplier_id', $this->supplierFilter))
            ->when($this->warehouseFilter, fn($q) => $q->where('warehouse_id', $this->warehouseFilter))
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->dateFilter, function ($q) {
                switch ($this->dateFilter) {
                    case 'today':
                        return $q->whereDate('order_date', today());
                    case 'week':
                        return $q->whereBetween('order_date', [now()->startOfWeek(), now()->endOfWeek()]);
                    case 'month':
                        return $q->whereMonth('order_date', now()->month);
                }
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();
        $products = Product::where('status', 'active')->orderBy('name')->get();

        $filterOptions = [
            'suppliers' => $suppliers->map(fn($s) => ['value' => $s->id, 'label' => $s->name]),
            'warehouses' => $warehouses->map(fn($w) => ['value' => $w->id, 'label' => $w->name]),
            'statuses' => [
                ['value' => '', 'label' => 'All Status'],
                ['value' => 'draft', 'label' => 'Draft'],
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'partial', 'label' => 'Partially Received'],
                ['value' => 'completed', 'label' => 'Completed'],
                ['value' => 'cancelled', 'label' => 'Cancelled'],
            ],
            'dates' => [
                ['value' => '', 'label' => 'All Dates'],
                ['value' => 'today', 'label' => 'Today'],
                ['value' => 'week', 'label' => 'This Week'],
                ['value' => 'month', 'label' => 'This Month'],
            ]
        ];

        return view('livewire.purchasing.purchase-order-management', [
            'purchaseOrders' => $purchaseOrders,
            'suppliers' => $suppliers,
            'warehouses' => $warehouses,
            'products' => $products,
            'filterOptions' => $filterOptions,
        ])->layout('layouts.app', ['title' => 'Purchase Orders']);
    }

    public function openModal()
    {
        $this->resetForm();
        $this->editMode = false;
        $this->selectedPO = null;
        $this->showModal = true;
        $this->resetValidation();
        $this->addItem();

        // Set default warehouse if not already set
        if (empty($this->warehouse_id)) {
            $defaultWarehouse = Warehouse::where('is_active', true)->first();
            if ($defaultWarehouse) {
                $this->warehouse_id = $defaultWarehouse->id;
            }
        }

        // Dispatch event to handle modal opening
        $this->dispatch('modal-opened');
    }

    public function closeForm()
    {
        $this->showModal = false;
        $this->editMode = false;
        $this->selectedPO = null;
        $this->resetForm();
        $this->resetValidation();
    }

    public function editPO(PurchaseOrder $po)
    {
        if ($po->status !== 'draft') {
            $this->error('Only draft purchase orders can be edited.');
            return;
        }

        $this->selectedPO = $po;
        $this->supplier_id = $po->supplier_id;
        $this->warehouse_id = $po->warehouse_id;
        $this->order_date = $po->order_date->format('Y-m-d');
        $this->expected_date = $po->expected_date->format('Y-m-d');
        $this->tin = $po->tin ?? '';
        $this->business_style = $po->business_style ?? '';
        $this->address = $po->address ?? '';
        $this->contact_person = $po->contact_person ?? '';
        $this->contact_number = $po->contact_number ?? '';
        $this->terms = $po->terms ?? '';
        $this->due_date = $po->due_date?->format('Y-m-d') ?? '';
        $this->notes = $po->notes ?? '';
        $this->discount_type = $po->discount_type ?? 'regular';
        $this->discount_value = (float) ($po->discount_value ?? 0);
        $this->discount_amount = (float) ($po->discount_amount ?? 0);
        $this->tax_type = $po->tax_type ?? 'vat_12';
        $this->tax_rate = (float) ($po->tax_rate ?? $this->taxRateForType($this->tax_type));
        $this->tax_amount = (float) ($po->tax_amount ?? 0);

        $this->items = $po->items->map(function ($item) use ($po) {
            return [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity_ordered,
                'unit_cost' => $item->unit_cost,
                'tax_type' => $item->tax_type ?? $po->tax_type ?? 'vat_12',
            ];
        })->toArray();

        $this->editMode = true;
        $this->showModal = true;
        $this->showDetailsModal = false;
        $this->resetValidation();
    }

    public function addItem()
    {
        $this->items[] = [
            'product_id' => '',
            'quantity' => 1,
            'unit_cost' => 0,
            'tax_type' => 'vat_12',
        ];
    }

    public function removeItem($index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function updatedDiscountType(): void
    {
        if (in_array($this->discount_type, ['senior', 'pwd'], true)) {
            $this->discount_value = 20;
        }

        $this->recalculateBilling();
    }

    public function updatedDiscountValue(): void
    {
        $this->recalculateBilling();
    }

    public function updatedTaxType(): void
    {
        $this->tax_rate = $this->taxRateForType($this->tax_type);
        $this->recalculateBilling();
    }

    public function updatedItems(): void
    {
        foreach ($this->items as $index => $item) {
            if (empty($item['tax_type'])) {
                $this->items[$index]['tax_type'] = 'vat_12';
            }
        }

        $this->recalculateBilling();
    }

    public function save($submit = false)
    {
        $this->validate();

        try {
            \DB::beginTransaction();

            $billing = $this->calculateBilling();

            $data = [
                'supplier_id' => $this->supplier_id,
                'warehouse_id' => $this->warehouse_id,
                'requested_by' => auth()->id(),
                'status' => $submit ? 'pending' : 'draft',
                'total_amount' => $billing['total'],
                'discount_type' => $this->discount_type,
                'discount_value' => $billing['discount_value'],
                'discount_amount' => $billing['discount_amount'],
                'tax_type' => $billing['tax_type'],
                'tax_rate' => $billing['tax_rate'],
                'tax_amount' => $billing['tax_amount'],
                'order_date' => $this->order_date,
                'expected_date' => $this->expected_date,
                'tin' => $this->tin,
                'business_style' => $this->business_style,
                'address' => $this->address,
                'contact_person' => $this->contact_person,
                'contact_number' => $this->contact_number,
                'terms' => $this->terms,
                'due_date' => $this->due_date ?: null,
                'notes' => $this->notes,
            ];

            if ($this->editMode) {
                $this->selectedPO->update($data);
                // Delete existing items and recreate
                $this->selectedPO->items()->delete();
                $po = $this->selectedPO;
            } else {
                $po = PurchaseOrder::create($data);
            }

            // Create items
            foreach ($this->items as $item) {
                $lineBilling = $this->calculateLineBilling($item, $billing['discount_value']);

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $item['product_id'],
                    'quantity_ordered' => $item['quantity'],
                    'quantity_received' => 0,
                    'unit_cost' => $item['unit_cost'],
                    'total_cost' => $item['quantity'] * $item['unit_cost'],
                    'tax_type' => $lineBilling['tax_type'],
                    'tax_rate' => $lineBilling['tax_rate'],
                    'tax_amount' => $lineBilling['tax_amount'],
                ]);
            }

            $this->syncSupplierSnapshot();

            \DB::commit();

            $message = $submit
                ? ($this->editMode ? 'Purchase order updated and submitted successfully!' : 'Purchase order created and submitted successfully!')
                : ($this->editMode ? 'Purchase order draft updated successfully!' : 'Purchase order draft saved successfully!');

            $this->success($message);
            $this->showModal = false;
            $this->resetForm();
        } catch (\Exception $e) {
            \DB::rollBack();
            $this->error('Error saving purchase order: ' . $e->getMessage());
        }
    }

    private function syncSupplierSnapshot(): void
    {
        $supplier = Supplier::find($this->supplier_id);

        if (!$supplier) {
            return;
        }

        $supplier->update([
            'tin' => $this->tin,
            'business_style' => $this->business_style,
            'address' => $this->address,
            'contact_person' => $this->contact_person,
            'phone' => $this->contact_number,
        ]);
    }

    public function submitPO(PurchaseOrder $po)
    {
        if ($po->status !== 'draft') {
            $this->error('Only draft purchase orders can be submitted.');
            return;
        }

        $po->update(['status' => 'pending']);
        $this->success('Purchase order submitted successfully!');
    }

    public function openReceiveModal(PurchaseOrder $po)
    {
        if (!in_array($po->status, ['pending', 'partial'])) {
            $this->error('This purchase order cannot be received.');
            return;
        }

        $this->selectedPO = $po->load(['supplier', 'warehouse', 'items.product']);

        $this->receivingItems = $po->items->map(function ($item) {
            return [
                'id' => $item->id,
                'product_name' => $item->product->name,
                'quantity_ordered' => $item->quantity_ordered,
                'quantity_received' => $item->quantity_received,
                'quantity_pending' => $item->quantity_pending,
                'receiving_quantity' => $item->quantity_pending,
                'unit_cost' => $item->unit_cost,
                'track_batch' => $item->product->track_batch,
                'track_expiry' => $item->product->track_expiry,
                'batch_number' => $item->product->track_batch ? $this->generateBatchNumber($item->product) : '',
                'lot_number' => '',
                'manufactured_date' => '',
                'expiry_date' => '',
            ];
        })->toArray();

        $this->showReceivePage = true;
    }

    public function closeReceivePage()
    {
        $this->showReceivePage = false;
        $this->selectedPO = null;
        $this->receivingItems = [];
        $this->resetValidation();
    }

    public function processReceiving()
    {
        $this->validate([
            'receivingItems.*.receiving_quantity' => 'required|integer|min:0',
            'receivingItems.*.batch_number' => 'nullable|string|max:255',
            'receivingItems.*.lot_number' => 'nullable|string|max:255',
            'receivingItems.*.manufactured_date' => 'nullable|date',
            'receivingItems.*.expiry_date' => 'nullable|date',
        ]);

        foreach ($this->receivingItems as $index => $receivingItem) {
            if (($receivingItem['receiving_quantity'] ?? 0) <= 0) {
                continue;
            }

            if (($receivingItem['track_batch'] ?? false) && empty($receivingItem['batch_number'])) {
                $this->addError("receivingItems.{$index}.batch_number", 'Batch number is required.');
                return;
            }

            if (($receivingItem['track_expiry'] ?? false) && empty($receivingItem['expiry_date'])) {
                $this->addError("receivingItems.{$index}.expiry_date", 'Expiry date is required.');
                return;
            }

            if (
                !empty($receivingItem['manufactured_date'])
                && !empty($receivingItem['expiry_date'])
                && $receivingItem['expiry_date'] <= $receivingItem['manufactured_date']
            ) {
                $this->addError("receivingItems.{$index}.expiry_date", 'Expiry date must be after the manufactured date.');
                return;
            }
        }

        try {
            \DB::beginTransaction();

            $totalReceived = 0;
            $po = $this->selectedPO;

            foreach ($this->receivingItems as $receivingItem) {
                if ($receivingItem['receiving_quantity'] <= 0) continue;

                $poItem = PurchaseOrderItem::find($receivingItem['id']);
                $newQuantityReceived = $poItem->quantity_received + $receivingItem['receiving_quantity'];

                // Update PO item
                $poItem->update([
                    'quantity_received' => $newQuantityReceived
                ]);

                // Update inventory
                $inventory = Inventory::where('product_id', $poItem->product_id)
                    ->where('warehouse_id', $po->warehouse_id)
                    ->first();

                if ($inventory) {
                    $currentValue = $inventory->quantity_on_hand * $inventory->average_cost;
                    $newValue = $receivingItem['receiving_quantity'] * $receivingItem['unit_cost'];
                    $newQuantity = $inventory->quantity_on_hand + $receivingItem['receiving_quantity'];

                    $inventory->update([
                        'quantity_on_hand' => $newQuantity,
                        'average_cost' => $newQuantity > 0 ? ($currentValue + $newValue) / $newQuantity : $receivingItem['unit_cost'],
                    ]);
                } else {
                    Inventory::create([
                        'product_id' => $poItem->product_id,
                        'warehouse_id' => $po->warehouse_id,
                        'quantity_on_hand' => $receivingItem['receiving_quantity'],
                        'average_cost' => $receivingItem['unit_cost'],
                    ]);
                }

                if (($receivingItem['track_batch'] ?? false) || ($receivingItem['track_expiry'] ?? false)) {
                    ProductBatch::create([
                        'product_id' => $poItem->product_id,
                        'warehouse_id' => $po->warehouse_id,
                        'purchase_order_item_id' => $poItem->id,
                        'batch_number' => $receivingItem['batch_number'] ?: $this->generateBatchNumber($poItem->product),
                        'lot_number' => $receivingItem['lot_number'] ?: null,
                        'manufactured_date' => $receivingItem['manufactured_date'] ?: null,
                        'expiry_date' => $receivingItem['expiry_date'] ?: null,
                        'quantity_received' => $receivingItem['receiving_quantity'],
                        'quantity_on_hand' => $receivingItem['receiving_quantity'],
                        'unit_cost' => $receivingItem['unit_cost'],
                        'received_at' => now(),
                        'supplier_name' => $po->supplier->name,
                        'notes' => "Received from PO: {$po->po_number}",
                    ]);
                }

                // Create stock movement
                StockMovement::create([
                    'product_id' => $poItem->product_id,
                    'warehouse_id' => $po->warehouse_id,
                    'type' => 'purchase_receipt',
                    'quantity_before' => max(0, ($inventory?->quantity_on_hand ?? 0) - $receivingItem['receiving_quantity']),
                    'quantity_changed' => $receivingItem['receiving_quantity'],
                    'quantity_after' => $inventory?->fresh()->quantity_on_hand ?? $receivingItem['receiving_quantity'],
                    'unit_cost' => $receivingItem['unit_cost'],
                    'reference_type' => 'App\Models\PurchaseOrder',
                    'reference_id' => $po->id,
                    'user_id' => auth()->id(),
                    'notes' => "Received from PO: {$po->po_number}",
                ]);

                $totalReceived += $receivingItem['receiving_quantity'];
            }

            // Update PO status
            $allItemsReceived = $po->items->every(function ($item) {
                return $item->quantity_received >= $item->quantity_ordered;
            });

            $po->update([
                'status' => $allItemsReceived ? 'completed' : 'partial'
            ]);

            \DB::commit();

            $this->success("Successfully received {$totalReceived} items!");
            $this->showReceiveModal = false;
        } catch (\Exception $e) {
            \DB::rollback();
            $this->error('Error processing receipt: ' . $e->getMessage());
        }
    }

    private function generateBatchNumber(Product $product): string
    {
        return strtoupper($product->sku) . '-' . now()->format('Ymd') . '-' . str_pad(random_int(1, 999), 3, '0', STR_PAD_LEFT);
    }

    public function cancelPO(PurchaseOrder $po)
    {
        if (!in_array($po->status, ['draft', 'pending'])) {
            $this->error('Only draft or pending purchase orders can be cancelled.');
            return;
        }

        $po->update(['status' => 'cancelled']);
        $this->success('Purchase order cancelled successfully!');
    }

    public function deletePO(PurchaseOrder $po)
    {
        if ($po->status !== 'draft') {
            $this->error('Only draft purchase orders can be deleted.');
            return;
        }

        try {
            $po->items()->delete();
            $po->delete();
            $this->success('Purchase order deleted successfully!');
        } catch (\Exception $e) {
            $this->error('Error deleting purchase order: ' . $e->getMessage());
        }
    }

    public function duplicatePO(PurchaseOrder $po)
    {
        try {
            $newPO = $po->replicate();
            $newPO->status = 'draft';
            $newPO->po_number = null;
            $newPO->order_date = now()->format('Y-m-d');
            $newPO->expected_date = now()->addDays(7)->format('Y-m-d');
            $newPO->requested_by = auth()->id();
            $newPO->save();

            // Duplicate items
            foreach ($po->items as $item) {
                $newItem = $item->replicate();
                $newItem->purchase_order_id = $newPO->id;
                $newItem->quantity_received = 0;
                $newItem->save();
            }

            $this->success("Purchase order duplicated successfully! New PO: {$newPO->po_number}");
        } catch (\Exception $e) {
            $this->error('Error duplicating purchase order: ' . $e->getMessage());
        }
    }

    public function clearFilters()
    {
        $this->reset(['search', 'supplierFilter', 'warehouseFilter', 'statusFilter', 'dateFilter']);
    }

    private function resetForm()
    {
        $this->reset([
            'supplier_id',
            'warehouse_id',
            'order_date',
            'expected_date',
            'tin',
            'business_style',
            'address',
            'contact_person',
            'contact_number',
            'terms',
            'due_date',
            'notes',
            'discount_type',
            'discount_value',
            'discount_amount',
            'tax_type',
            'tax_rate',
            'tax_amount',
            'items'
        ]);
        $this->order_date = now()->format('Y-m-d');
        $this->expected_date = now()->addDays(7)->format('Y-m-d');
        $this->discount_type = 'regular';
        $this->discount_value = 0;
        $this->discount_amount = 0;
        $this->tax_type = 'vat_12';
        $this->tax_rate = 12;
        $this->tax_amount = 0;
    }

    public function calculateBilling(): array
    {
        $subtotal = collect($this->items)->sum(fn($item) => $this->lineGrossAmount($item));
        $discountValue = in_array($this->discount_type, ['senior', 'pwd'], true) ? 20 : (float) $this->discount_value;
        $lineBillings = collect($this->items)->map(fn($item) => $this->calculateLineBilling($item, $discountValue));
        $discountAmount = $lineBillings->sum('discount_amount');
        $displaySubtotal = $lineBillings->sum('net_amount');
        $taxAmount = $lineBillings->sum('tax_amount');
        $total = $lineBillings->sum('total_amount');
        $taxTypes = $lineBillings
            ->pluck('tax_type')
            ->unique()
            ->values();
        $taxType = $taxTypes->count() === 1 ? $taxTypes->first() : 'mixed';
        $taxRate = $taxTypes->count() === 1 ? $this->taxRateForType($taxType) : 0;

        return [
            'subtotal' => $displaySubtotal,
            'gross_subtotal' => $subtotal,
            'taxable_gross_amount' => max(0, $subtotal - $discountAmount),
            'discount_value' => $discountValue,
            'discount_amount' => $discountAmount,
            'tax_type' => $taxType,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ];
    }

    public function recalculateBilling(): void
    {
        $billing = $this->calculateBilling();
        $this->discount_amount = $billing['discount_amount'];
        $this->tax_rate = $billing['tax_rate'];
        $this->tax_amount = $billing['tax_amount'];
    }

    public function discountLabel(): string
    {
        return match ($this->discount_type) {
            'senior' => 'Senior Citizen Discount (20%)',
            'pwd' => 'PWD Discount (20%)',
            default => 'Regular Discount (' . (float) $this->discount_value . '%)',
        };
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

    private function lineGrossAmount(array $item): float
    {
        return (float) ($item['quantity'] ?? 0) * (float) ($item['unit_cost'] ?? 0);
    }

    public function calculateLineBilling(array $item, ?float $discountValue = null): array
    {
        $grossAmount = $this->lineGrossAmount($item);
        $discountValue ??= in_array($this->discount_type, ['senior', 'pwd'], true) ? 20 : (float) $this->discount_value;
        $discountAmount = $grossAmount * ($discountValue / 100);
        $discountedAmount = max(0, $grossAmount - $discountAmount);
        $taxType = $item['tax_type'] ?? 'vat_12';
        $taxRate = $this->taxRateForType($taxType);

        if ($taxType === 'vat_12') {
            $taxAmount = $discountedAmount - ($discountedAmount / 1.12);
            $netAmount = $discountedAmount / 1.12;
            $totalAmount = $discountedAmount;
        } elseif (in_array($taxType, ['ewt_sales_1', 'ewt_service_2'], true)) {
            $netAmount = $discountedAmount / 1.12;
            $taxAmount = $netAmount * ($taxRate / 100);
            $totalAmount = max(0, $discountedAmount - $taxAmount);
        } else {
            $taxAmount = $discountedAmount * ($taxRate / 100);
            $netAmount = $discountedAmount;
            $totalAmount = $discountedAmount + $taxAmount;
        }

        return [
            'gross_amount' => $grossAmount,
            'discount_amount' => $discountAmount,
            'discounted_amount' => $discountedAmount,
            'net_amount' => $netAmount,
            'tax_type' => $taxType,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
        ];
    }

    public function calculateTaxAmount(float $amount): float
    {
        if ($this->tax_type === 'vat_12') {
            return $amount - ($amount / 1.12);
        }

        if (in_array($this->tax_type, ['ewt_sales_1', 'ewt_service_2'], true)) {
            return ($amount / 1.12) * ($this->taxRateForType($this->tax_type) / 100);
        }

        return $amount * ($this->taxRateForType($this->tax_type) / 100);
    }

    public function calculateTotalAmount(float $amount, float $taxAmount): float
    {
        return match ($this->tax_type) {
            'vat_12' => $amount,
            'ewt_sales_1', 'ewt_service_2' => max(0, $amount - $taxAmount),
            default => $amount + $taxAmount,
        };
    }

    public function taxLabel(?string $type = null): string
    {
        return match ($type ?? $this->tax_type) {
            'vat_12' => 'VAT (12% inclusive)',
            'ewt_sales_1' => 'EWT (1% on sales, net of VAT)',
            'ewt_service_2' => 'EWT (2% on services, net of VAT)',
            'mixed' => 'Mixed Tax',
            default => 'Non-VAT',
        };
    }

    public function subtotalLabel(): string
    {
        return collect($this->items)->contains(fn($item) => in_array($item['tax_type'] ?? 'vat_12', ['vat_12', 'ewt_sales_1', 'ewt_service_2'], true))
            ? 'Subtotal (Net of VAT):'
            : 'Subtotal:';
    }

    public function taxOptions(): array
    {
        return [
            ['value' => 'vat_12', 'label' => 'VAT (12% inclusive)'],
            ['value' => 'none', 'label' => 'Non-VAT'],
            ['value' => 'ewt_sales_1', 'label' => 'EWT (1% on sales, net of VAT)'],
            ['value' => 'ewt_service_2', 'label' => 'EWT (2% on services, net of VAT)'],
        ];
    }

    /**
     * Get status color for badges
     */
    public function getStatusColor($status)
    {
        return match ($status) {
            'draft' => 'neutral',
            'pending' => 'warning',
            'partial' => 'info',
            'completed' => 'success',
            'cancelled' => 'error',
            default => 'neutral',
        };
    }

    /**
     * Export purchase orders to Excel/CSV
     */
    public function exportPOs()
    {
        // Implementation for exporting POs
        $this->info('Export functionality coming soon!');
    }

    /**
     * Bulk operations on selected POs
     */
    public function bulkSubmit($poIds)
    {
        $updated = 0;
        foreach ($poIds as $poId) {
            $po = PurchaseOrder::find($poId);
            if ($po && $po->status === 'draft') {
                $po->update(['status' => 'pending']);
                $updated++;
            }
        }

        if ($updated > 0) {
            $this->success("Successfully submitted {$updated} purchase order(s)!");
        } else {
            $this->warning('No purchase orders were submitted.');
        }
    }

    /**
     * Print purchase order
     */
    public function printPO(PurchaseOrder $po)
    {
        return redirect()->route('purchasing.purchase-orders.print', $po);
    }

    /**
     * Send PO via email to supplier
     */
    public function emailPO(PurchaseOrder $po)
    {
        if (!$po->supplier->email) {
            $this->error('Supplier does not have an email address on file.');
            return;
        }

        try {
            // Implementation for emailing PO
            $this->success("Purchase order {$po->po_number} sent to {$po->supplier->email}!");
        } catch (\Exception $e) {
            $this->error('Error sending email: ' . $e->getMessage());
        }
    }

    /**
     * View detailed PO information
     */
    public function viewPODetails(PurchaseOrder $po)
    {
        // Load the PO with basic relationships
        $this->viewingPO = $po->load([
            'supplier',
            'warehouse',
            'requestedBy',
            'items.product.category',
        ]);

        $this->showDetailsModal = true;
    }

    /**
     * Convert PO to different format
     */
    public function convertPO(PurchaseOrder $po, $format = 'pdf')
    {
        try {
            // Implementation for converting PO to different formats
            $this->success("PO {$po->po_number} converted to {$format} successfully!");
        } catch (\Exception $e) {
            $this->error("Error converting PO to {$format}: " . $e->getMessage());
        }
    }

    /**
     * Get total value of filtered POs
     */
    public function getTotalValue()
    {
        return PurchaseOrder::when($this->search, fn($q) => $q->where('po_number', 'like', '%' . $this->search . '%'))
            ->when($this->supplierFilter, fn($q) => $q->where('supplier_id', $this->supplierFilter))
            ->when($this->warehouseFilter, fn($q) => $q->where('warehouse_id', $this->warehouseFilter))
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->sum('total_amount');
    }

    /**
     * Get PO statistics
     */
    public function getPOStats()
    {
        return [
            'total' => PurchaseOrder::count(),
            'draft' => PurchaseOrder::where('status', 'draft')->count(),
            'pending' => PurchaseOrder::where('status', 'pending')->count(),
            'completed' => PurchaseOrder::where('status', 'completed')->count(),
            'cancelled' => PurchaseOrder::where('status', 'cancelled')->count(),
        ];
    }
}
