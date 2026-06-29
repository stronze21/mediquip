<div>
    {{-- Page Header --}}
    <x-mary-header title="Purchase Orders" subtitle="Manage supplier purchase orders and receiving" separator>
        @unless ($showModal)
            <x-slot:middle class="!justify-end">
                <x-mary-input placeholder="Search purchase orders..." wire:model.live.debounce="search" clearable
                    icon="o-magnifying-glass" />
            </x-slot:middle>
        @endunless
        <x-slot:actions>
            @if ($showModal)
                <x-mary-button icon="o-arrow-left" wire:click="closeForm" class="btn-ghost">
                    Back to Purchase Orders
                </x-mary-button>
            @else
                <x-mary-button icon="o-plus" wire:click="openModal" class="btn-primary">
                    Create Purchase Order
                </x-mary-button>
            @endif
        </x-slot:actions>
    </x-mary-header>

    @if ($showReceivePage)
        {{-- Full Page Receiving Form --}}
        <div class="space-y-6">
            <x-mary-card>
                <div class="flex flex-col gap-3 mb-6 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-2xl font-bold">
                            Receive Items - {{ $selectedPO?->po_number }}
                        </h2>
                        <p class="text-sm text-gray-500">
                            Process pending items for this purchase order.
                        </p>
                    </div>

                    <x-mary-badge value="{{ $selectedPO?->status_display }}" class="badge-warning" />
                </div>

                <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-2">
                    <div class="p-4 rounded-lg bg-base-200">
                        <div class="text-sm text-gray-500">Supplier</div>
                        <div class="font-semibold">{{ $selectedPO?->supplier?->name }}</div>
                    </div>

                    <div class="p-4 rounded-lg bg-base-200">
                        <div class="text-sm text-gray-500">Warehouse</div>
                        <div class="font-semibold">{{ $selectedPO?->warehouse?->name }}</div>
                    </div>
                </div>

                <div class="space-y-4">
                    @foreach ($receivingItems as $index => $item)
                        <div class="p-5 border rounded-xl bg-base-100">
                            <div class="flex flex-col gap-4">

                                <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                                    <div>
                                        <div class="text-xs text-gray-500">Product</div>
                                        <div class="text-lg font-semibold">
                                            {{ $item['product_name'] }}
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-3 gap-3 text-center md:min-w-80">
                                        <div class="p-3 rounded-lg bg-base-200">
                                            <div class="text-xs text-gray-500">Ordered</div>
                                            <div class="font-bold">{{ $item['quantity_ordered'] }}</div>
                                        </div>

                                        <div class="p-3 rounded-lg bg-base-200">
                                            <div class="text-xs text-gray-500">Received</div>
                                            <div class="font-bold">{{ $item['quantity_received'] }}</div>
                                        </div>

                                        <div class="p-3 rounded-lg bg-warning/10">
                                            <div class="text-xs text-warning">Pending</div>
                                            <div class="font-bold text-warning">{{ $item['quantity_pending'] }}</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
                                    <x-mary-input
                                        label="Receiving Now"
                                        wire:model="receivingItems.{{ $index }}.receiving_quantity"
                                        type="number"
                                        min="0"
                                        max="{{ $item['quantity_pending'] }}" />

                                    @if ($item['track_batch'] || $item['track_expiry'])
                                        <x-mary-input
                                            label="Batch Number"
                                            wire:model="receivingItems.{{ $index }}.batch_number"
                                            placeholder="Batch number" />

                                        <x-mary-input
                                            label="Lot Number"
                                            wire:model="receivingItems.{{ $index }}.lot_number"
                                            placeholder="Optional" />

                                        <x-mary-input
                                            label="Manufactured Date"
                                            wire:model="receivingItems.{{ $index }}.manufactured_date"
                                            type="date" />

                                        @if ($item['track_expiry'])
                                            <x-mary-input
                                                label="Expiry Date"
                                                wire:model="receivingItems.{{ $index }}.expiry_date"
                                                type="date" />
                                        @else
                                            <div class="p-4 rounded-lg bg-base-200">
                                                <div class="text-xs text-gray-500">Expiry Date</div>
                                                <div class="font-medium text-gray-400">No expiry tracking</div>
                                            </div>
                                        @endif
                                    @else
                                        <div class="p-4 rounded-lg md:col-span-4 bg-base-200">
                                            <div class="text-xs text-gray-500">Tracking</div>
                                            <div class="font-medium text-gray-400">
                                                This product does not require batch or expiry tracking.
                                            </div>
                                        </div>
                                    @endif
                                </div>

                            </div>
                        </div>
                    @endforeach
                </div>
            </x-mary-card>

            <div class="sticky bottom-0 z-10 p-4 border rounded-lg bg-base-100/95 backdrop-blur">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-end">
                    <x-mary-button label="Cancel" wire:click="closeReceivePage" class="btn-ghost" />

                    <x-mary-button
                        label="Process Receiving"
                        wire:click="processReceiving"
                        class="btn-primary"
                        wire:confirm="Process receiving for this purchase order?" />
                </div>
            </div>
        </div>

    @elseif ($showModal)
        {{-- Full Page Create/Edit Purchase Order Form --}}
        <div class="space-y-6">
            <x-mary-card>
                <div class="flex flex-col gap-3 mb-6 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-2xl font-bold">
                            {{ $editMode ? 'Edit Draft Purchase Order' : 'Create Purchase Order Draft' }}
                        </h2>
                        <p class="text-sm text-gray-500">
                            Save as draft while preparing the order, or submit when it is ready for receiving.
                        </p>
                    </div>
                    <x-mary-badge value="Draft Workflow" class="badge-warning" />
                </div>

                <div class="space-y-6">
                    {{-- Basic Information --}}
                    <div>
                        <h3 class="mb-3 text-lg font-semibold">Supplier & Order Information</h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <x-mary-select label="Supplier *" :options="$suppliers" wire:model.live="supplier_id"
                                option-value="id" option-label="name" placeholder="Select supplier" />

                            <x-mary-select label="Warehouse *" :options="$warehouses" wire:model="warehouse_id"
                                option-value="id" option-label="name" placeholder="Select warehouse" />

                            <x-mary-input label="Order Date *" wire:model="order_date" type="date" />

                            <x-mary-input label="Expected Date *" wire:model="expected_date" type="date" />
                        </div>
                    </div>

                    {{-- Purchase Order Form Details --}}
                    <div>
                        <div class="flex flex-col gap-1 mb-3">
                            <h3 class="text-lg font-semibold">Purchase Order Form Details</h3>
                            <p class="text-sm text-gray-500">
                                These values are copied into the PO for traceability. Editing them here also updates the
                                selected supplier profile.
                            </p>
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <x-mary-input label="TIN" wire:model="tin" placeholder="Supplier TIN" />

                            <x-mary-input label="Business Style" wire:model="business_style"
                                placeholder="Registered business style" />

                            <div class="md:col-span-2">
                                <x-mary-textarea label="Address" wire:model="address"
                                    placeholder="Billing or supplier address" />
                            </div>

                            <x-mary-input label="Contact Person" wire:model="contact_person"
                                placeholder="Primary contact person" />

                            <x-mary-input label="Contact Number" wire:model="contact_number"
                                placeholder="Phone or mobile number" />

                            <x-mary-input label="Terms" wire:model="terms" placeholder="Payment terms" />

                            <x-mary-input label="Due Date" wire:model="due_date" type="date" />
                        </div>
                    </div>

                    <x-mary-textarea label="Notes" wire:model="notes"
                        placeholder="Additional notes for this purchase order" />

                    {{-- Billing Section --}}
                    <div>
                        <h3 class="mb-3 text-lg font-semibold">Discounts & Taxes</h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <x-mary-select label="Purchase Discount" :options="[
                                ['value' => 'regular', 'label' => 'Regular Discount'],
                                ['value' => 'senior', 'label' => 'Senior Citizen Discount'],
                                ['value' => 'pwd', 'label' => 'PWD Discount'],
                            ]" wire:model.live="discount_type" option-value="value" option-label="label" />

                            <x-mary-input label="Discount Rate (%)" wire:model.live="discount_value" type="number"
                                step="0.01" min="0" max="100"
                                :disabled="in_array($discount_type, ['senior', 'pwd'], true)" />
                        </div>
                    </div>

                    {{-- Items Section --}}
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold">Items</h3>
                            <x-mary-button label="Add Item" wire:click="addItem" class="btn-outline btn-sm"
                                icon="o-plus" />
                        </div>

                        @if (count($items) > 0)
                            <div class="overflow-x-auto">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Quantity</th>
                                            <th>VAT</th>
                                            <th>Unit Cost</th>
                                            <th>Total</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($items as $index => $item)
                                            <tr>
                                                <td class="min-w-80">
                                                    <x-mary-choices-offline :options="$products"
                                                        wire:model="items.{{ $index }}.product_id"
                                                        option-value="id" option-label="name"
                                                        placeholder="Select product" single clearable searchable />
                                                </td>
                                                <td class="min-w-32">
                                                    <x-mary-input wire:model.live="items.{{ $index }}.quantity"
                                                        type="number" min="1" />
                                                </td>
                                                <td class="min-w-56">
                                                    <x-mary-select :options="$this->taxOptions()"
                                                        wire:model.live="items.{{ $index }}.tax_type"
                                                        option-value="value" option-label="label" />
                                                </td>
                                                <td class="min-w-36">
                                                    <x-mary-input wire:model.live="items.{{ $index }}.unit_cost"
                                                        type="number" step="0.01" min="0" />
                                                </td>
                                                <td>
                                                    <div class="font-medium">
                                                        &#8369;{{ number_format(($item['quantity'] ?? 0) * ($item['unit_cost'] ?? 0), 2) }}
                                                    </div>
                                                </td>
                                                <td>
                                                    <x-mary-button icon="o-trash"
                                                        wire:click="removeItem({{ $index }})"
                                                        class="btn-ghost btn-sm text-error" />
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        @php $billing = $this->calculateBilling(); @endphp
                                        <tr>
                                            <td colspan="4" class="text-right">{{ $this->subtotalLabel() }}</td>
                                            <td>&#8369;{{ number_format($billing['subtotal'], 2) }}</td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td colspan="4" class="text-right">{{ $this->discountLabel() }}:</td>
                                            <td>&#8369;{{ number_format($billing['discount_amount'], 2) }}</td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td colspan="4" class="text-right">{{ $this->taxLabel($billing['tax_type']) }}:</td>
                                            <td>&#8369;{{ number_format($billing['tax_amount'], 2) }}</td>
                                            <td></td>
                                        </tr>
                                        <tr class="font-bold">
                                            <td colspan="4" class="text-right">Total Amount:</td>
                                            <td>&#8369;{{ number_format($billing['total'], 2) }}
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @else
                            <div class="py-8 text-center border-2 border-gray-300 border-dashed rounded-lg">
                                <x-heroicon-o-cube class="w-12 h-12 mx-auto text-gray-400" />
                                <p class="mt-2 text-gray-500">No items added yet</p>
                                <x-mary-button label="Add First Item" wire:click="addItem"
                                    class="mt-4 btn-primary" />
                            </div>
                        @endif
                    </div>
                </div>
            </x-mary-card>

            <div class="sticky bottom-0 z-10 p-4 border rounded-lg bg-base-100/95 backdrop-blur">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-end">
                    <x-mary-button label="Cancel" wire:click="closeForm" class="btn-ghost" />
                    <x-mary-button label="Save Draft" wire:click="save" class="btn-outline" />
                    <x-mary-button label="Save & Submit" wire:click="save(true)" class="btn-primary"
                        wire:confirm="Submit this purchase order now?" />
                </div>
            </div>
        </div>
    @else
    {{-- Filters --}}
    <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-5">
        <x-mary-select placeholder="All Suppliers" :options="$filterOptions['suppliers']" wire:model.live="supplierFilter"
            option-value="value" option-label="label" />
        <x-mary-select placeholder="All Warehouses" :options="$filterOptions['warehouses']" wire:model.live="warehouseFilter"
            option-value="value" option-label="label" />
        <x-mary-select placeholder="All Status" :options="$filterOptions['statuses']" wire:model.live="statusFilter" option-value="value"
            option-label="label" />
        <x-mary-select placeholder="All Dates" :options="$filterOptions['dates']" wire:model.live="dateFilter" option-value="value"
            option-label="label" />
        <x-mary-button icon="o-x-mark" wire:click="clearFilters" class="btn-ghost">
            Clear Filters
        </x-mary-button>
    </div>

    {{-- Purchase Orders Table --}}
    <x-mary-card>
        <div class="min-h-screen overflow-x-auto">
            <table class="table h-full table-zebra">
                <thead>
                    <tr>
                        <th>PO Number</th>
                        <th>Supplier</th>
                        <th>Warehouse</th>
                        <th>Order Date</th>
                        <th>Expected</th>
                        <th>Items</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($purchaseOrders as $po)
                        <tr>
                            <td>
                                <div>
                                    <div class="font-bold">{{ $po->po_number }}</div>
                                    <div class="text-sm text-gray-500">{{ $po->requestedBy->name }}</div>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <div class="font-medium">{{ $po->supplier->name }}</div>
                                    @if ($po->supplier->contact_person)
                                        <div class="text-sm text-gray-500">{{ $po->supplier->contact_person }}</div>
                                    @endif
                                </div>
                            </td>
                            <td>{{ $po->warehouse->name }}</td>
                            <td>{{ $po->order_date->format('M d, Y') }}</td>
                            <td>
                                @if ($po->expected_date)
                                    <div class="{{ $po->expected_date->isPast() ? 'text-red-600' : 'text-gray-700' }}">
                                        {{ $po->expected_date->format('M d, Y') }}
                                    </div>
                                    @if ($po->expected_date->isPast() && $po->status !== 'completed')
                                        <div class="text-xs text-red-500">Overdue</div>
                                    @endif
                                @else
                                    <span class="text-gray-400">Not set</span>
                                @endif
                            </td>
                            <td>
                                <div class="text-sm">
                                    {{ $po->items->count() }} item{{ $po->items->count() !== 1 ? 's' : '' }}
                                </div>
                            </td>
                            <td>
                                <div class="font-medium">&#8369;{{ number_format($po->total_amount, 2) }}</div>
                            </td>
                            <td>
                                <x-mary-badge value="{{ ucfirst($po->status) }}"
                                    class="badge badge-{{ $this->getStatusColor($po->status) }}" />
                            </td>
                            <td>
                                <div class="flex gap-1">
                                    @if ($po->status === 'draft')
                                        <x-mary-button icon="o-pencil" wire:click="editPO({{ $po->id }})"
                                            class="btn-ghost btn-sm" tooltip="Edit" />
                                        <x-mary-button icon="o-paper-airplane"
                                            wire:click="submitPO({{ $po->id }})"
                                            class="btn-ghost btn-sm text-success" tooltip="Submit"
                                            wire:confirm="Submit this purchase order?" />
                                    @endif

                                    @if (in_array($po->status, ['pending', 'partial']))
                                        <x-mary-button icon="o-cube"
                                            wire:click="openReceiveModal({{ $po->id }})"
                                            class="btn-ghost btn-sm text-info" tooltip="Receive Items" />
                                    @endif

                                    {{-- FIXED DROPDOWN WITH PROPER WIRE:CLICK --}}
                                    <div class="dropdown dropdown-end">
                                        <div tabindex="0" role="button" class="btn btn-ghost btn-xs">
                                            <x-heroicon-o-ellipsis-vertical class="w-4 h-4" />
                                        </div>
                                        <ul tabindex="0"
                                            class="dropdown-content menu bg-base-100 rounded-box z-[1] w-52 p-2 shadow">
                                            {{-- FIXED: Added proper wire:click for View Details --}}
                                            <li><a wire:click="viewPODetails({{ $po->id }})" class="text-info">
                                                    <x-heroicon-o-eye class="w-4 h-4" /> View Details</a></li>
                                            <li><a wire:click="printPO({{ $po->id }})" class="text-info">
                                                    <x-heroicon-o-printer class="w-4 h-4" /> Print PO</a></li>
                                            <li><a wire:click="deletePO({{ $po->id }})" class="text-error"
                                                    wire:confirm="Hard delete this purchase order? If received stock was already used, deletion will be blocked.">
                                                    <x-heroicon-o-trash class="w-4 h-4" /> Delete</a></li>
                                            @if (in_array($po->status, ['draft', 'pending']))
                                                <li><a wire:click="cancelPO({{ $po->id }})" class="text-warning"
                                                        wire:confirm="Are you sure you want to cancel this purchase order?">
                                                        <x-heroicon-o-x-mark class="w-4 h-4" /> Cancel</a></li>
                                            @endif
                                            <li><a wire:click="duplicatePO({{ $po->id }})"
                                                    class="text-secondary">
                                                    <x-heroicon-o-document-duplicate class="w-4 h-4" /> Duplicate</a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-8 text-center">
                                <div class="flex flex-col items-center">
                                    <x-heroicon-o-clipboard-document-list class="w-12 h-12 mb-2 text-gray-400" />
                                    <p class="text-gray-500">No purchase orders found</p>
                                    <x-mary-button label="Create First Purchase Order" wire:click="openModal"
                                        class="mt-4 btn-primary" />
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-mary-card>

    {{-- Pagination --}}
    <div class="mt-6">
        {{ $purchaseOrders->links() }}
    </div>

    @endif

    {{-- Receive Items Drawer --}}
    <x-mary-drawer
        wire:model="showReceiveModal"
        title="Receive Items - {{ $selectedPO?->po_number }}"
        subtitle="Process pending purchase order items"
        separator
        with-close-button
        close-on-escape
        class="w-full lg:w-11/12 xl:w-10/12 2xl:w-9/12"
        right>

        @if ($selectedPO)
            <div class="space-y-6">

                {{-- PO Summary --}}
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="p-4 rounded-lg bg-info/10">
                        <div class="text-sm text-gray-600">Supplier</div>
                        <div class="font-semibold">{{ $selectedPO->supplier->name }}</div>
                    </div>

                    <div class="p-4 rounded-lg bg-info/10">
                        <div class="text-sm text-gray-600">Warehouse</div>
                        <div class="font-semibold">{{ $selectedPO->warehouse->name }}</div>
                    </div>
                </div>

                {{-- Receiving Items --}}
                <div class="space-y-4">
                    @foreach ($receivingItems as $index => $item)
                        <div class="p-4 border rounded-xl bg-base-100">
                            <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">

                                {{-- Product --}}
                                <div class="xl:col-span-3">
                                    <div class="text-xs text-gray-500">Product</div>
                                    <div class="font-semibold">
                                        {{ $item['product_name'] }}
                                    </div>
                                </div>

                                {{-- Quantities --}}
                                <div class="grid grid-cols-3 gap-3 xl:col-span-3">
                                    <div>
                                        <div class="text-xs text-gray-500">Ordered</div>
                                        <div class="font-medium">{{ $item['quantity_ordered'] }}</div>
                                    </div>

                                    <div>
                                        <div class="text-xs text-gray-500">Received</div>
                                        <div class="font-medium">{{ $item['quantity_received'] }}</div>
                                    </div>

                                    <div>
                                        <div class="text-xs text-gray-500">Pending</div>
                                        <div class="font-medium text-warning">{{ $item['quantity_pending'] }}</div>
                                    </div>
                                </div>

                                {{-- Receiving Now --}}
                                <div class="xl:col-span-2">
                                    <x-mary-input
                                        label="Receiving Now"
                                        wire:model="receivingItems.{{ $index }}.receiving_quantity"
                                        type="number"
                                        min="0"
                                        max="{{ $item['quantity_pending'] }}" />
                                </div>

                                {{-- Batch / Lot --}}
                                <div class="xl:col-span-2">
                                    @if ($item['track_batch'] || $item['track_expiry'])
                                        <div class="space-y-2">
                                            <x-mary-input
                                                label="Batch No."
                                                wire:model="receivingItems.{{ $index }}.batch_number"
                                                placeholder="Batch number" />

                                            <x-mary-input
                                                label="Lot No."
                                                wire:model="receivingItems.{{ $index }}.lot_number"
                                                placeholder="Optional" />
                                        </div>
                                    @else
                                        <div class="text-sm text-gray-400">
                                            Not tracked
                                        </div>
                                    @endif
                                </div>

                                {{-- Dates --}}
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:col-span-2">
                                    @if ($item['track_batch'] || $item['track_expiry'])
                                        <x-mary-input
                                            label="Manufactured"
                                            wire:model="receivingItems.{{ $index }}.manufactured_date"
                                            type="date" />

                                        @if ($item['track_expiry'])
                                            <x-mary-input
                                                label="Expiry"
                                                wire:model="receivingItems.{{ $index }}.expiry_date"
                                                type="date" />
                                        @else
                                            <div class="text-sm text-gray-400">
                                                No expiry
                                            </div>
                                        @endif
                                    @else
                                        <div class="text-sm text-gray-400">
                                            -
                                        </div>
                                    @endif
                                </div>

                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <x-slot:actions>
                <x-mary-button
                    label="Cancel"
                    wire:click="$set('showReceiveModal', false)"
                    class="btn-ghost" />

                <x-mary-button
                    label="Process Receiving"
                    wire:click="processReceiving"
                    class="btn-primary" />
            </x-slot:actions>
        @endif

    </x-mary-drawer>

    {{-- View Purchase Order Details Modal --}}
    <x-mary-modal wire:model="showDetailsModal" title="Purchase Order Details"
        subtitle="{{ $viewingPO?->po_number }} - {{ $viewingPO?->supplier?->name }}" box-class="max-w-7xl">
        @if ($viewingPO)
            <div class="space-y-6">
                {{-- Header Information --}}
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    {{-- Basic Info --}}
                    <x-mary-card title="Basic Information">
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="font-medium">PO Number:</span>
                                <span>{{ $viewingPO->po_number }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">Status:</span>
                                <x-mary-badge value="{{ ucfirst($viewingPO->status) }}"
                                    class="badge badge-{{ $this->getStatusColor($viewingPO->status) }}" />
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">Order Date:</span>
                                <span>{{ $viewingPO->order_date->format('M d, Y') }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">Expected Date:</span>
                                <span>{{ $viewingPO->expected_date?->format('M d, Y') ?? 'Not set' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">Terms:</span>
                                <span>{{ $viewingPO->terms ?: 'Not set' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">Due Date:</span>
                                <span>{{ $viewingPO->due_date?->format('M d, Y') ?? 'Not set' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">Requested By:</span>
                                <span>{{ $viewingPO->requestedBy?->name }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">Total Amount:</span>
                                <span
                                    class="text-lg font-bold">&#8369;{{ number_format($viewingPO->total_amount, 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">Discount:</span>
                                <span>{{ ucfirst($viewingPO->discount_type ?? 'regular') }} - &#8369;{{ number_format($viewingPO->discount_amount ?? 0, 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">Tax:</span>
                                <span>{{ $this->taxLabel($viewingPO->tax_type ?? 'none') }} - &#8369;{{ number_format($viewingPO->tax_amount ?? 0, 2) }}</span>
                            </div>
                        </div>
                    </x-mary-card>

                    {{-- Supplier & Warehouse Info --}}
                    <x-mary-card title="Supplier & Warehouse">
                        <div class="space-y-4">
                            <div>
                                <h5 class="mb-2 font-medium">Supplier Information</h5>
                                <div class="space-y-1 text-sm">
                                    <div><strong>Name:</strong> {{ $viewingPO->supplier->name }}</div>
                                    @if ($viewingPO->supplier->contact_person)
                                        <div><strong>Contact:</strong> {{ $viewingPO->supplier->contact_person }}</div>
                                    @endif
                                    @if ($viewingPO->supplier->email)
                                        <div><strong>Email:</strong> {{ $viewingPO->supplier->email }}</div>
                                    @endif
                                    @if ($viewingPO->supplier->phone)
                                        <div><strong>Phone:</strong> {{ $viewingPO->supplier->phone }}</div>
                                    @endif
                                    <div><strong>TIN:</strong> {{ $viewingPO->tin ?: 'Not set' }}</div>
                                    <div><strong>Business Style:</strong> {{ $viewingPO->business_style ?: 'Not set' }}</div>
                                    <div><strong>PO Contact Person:</strong> {{ $viewingPO->contact_person ?: 'Not set' }}</div>
                                    <div><strong>PO Contact Number:</strong> {{ $viewingPO->contact_number ?: 'Not set' }}</div>
                                    <div><strong>PO Address:</strong> {{ $viewingPO->address ?: 'Not set' }}</div>
                                </div>
                            </div>

                            <div>
                                <h5 class="mb-2 font-medium">Warehouse</h5>
                                <div class="text-sm">
                                    <div><strong>Name:</strong> {{ $viewingPO->warehouse->name }}</div>
                                    @if ($viewingPO->warehouse->address)
                                        <div><strong>Address:</strong> {{ $viewingPO->warehouse->address }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </x-mary-card>
                </div>

                {{-- Items Details --}}
                <x-mary-card title="Items ({{ $viewingPO->items->count() }} items)">
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Qty Ordered</th>
                                    <th>Qty Received</th>
                                    <th>Pending</th>
                                    <th>VAT</th>
                                    <th>Unit Cost</th>
                                    <th>Total Cost</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($viewingPO->items as $item)
                                    <tr>
                                        <td>
                                            <div>
                                                <div class="font-medium">{{ $item->product->name }}</div>
                                                <div class="text-sm text-gray-500">{{ $item->product->sku }}</div>
                                            </div>
                                        </td>
                                        <td>{{ $item->quantity_ordered }}</td>
                                        <td>{{ $item->quantity_received }}</td>
                                        <td>{{ $item->quantity_pending }}</td>
                                        <td>{{ $this->taxLabel($item->tax_type ?? $viewingPO->tax_type ?? 'none') }}</td>
                                        <td>&#8369;{{ number_format($item->unit_cost, 2) }}</td>
                                        <td>&#8369;{{ number_format($item->total_cost, 2) }}</td>
                                        <td>
                                            @if ($item->quantity_received >= $item->quantity_ordered)
                                                <x-mary-badge value="Complete" class="badge-success" />
                                            @elseif ($item->quantity_received > 0)
                                                <x-mary-badge value="Partial" class="badge-warning" />
                                            @else
                                                <x-mary-badge value="Pending" class="badge-error" />
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="font-bold">
                                    <td colspan="6" class="text-right">Total:</td>
                                    <td>&#8369;{{ number_format($viewingPO->items->sum('total_cost'), 2) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </x-mary-card>

                {{-- Actions --}}
                <x-mary-card title="Actions">
                    <div class="flex flex-wrap gap-2">
                        @if ($viewingPO->status === 'draft')
                            <x-mary-button label="Edit PO" icon="o-pencil" wire:click="editPO({{ $viewingPO->id }})"
                                class="btn-outline" />
                            <x-mary-button label="Submit PO" icon="o-paper-airplane"
                                wire:click="submitPO({{ $viewingPO->id }})" class="btn-primary" />
                        @endif

                        @if (in_array($viewingPO->status, ['pending', 'partial']))
                            <x-mary-button label="Receive Items" icon="o-cube"
                                wire:click="openReceiveModal({{ $viewingPO->id }})" class="btn-success" />
                        @endif

                        <x-mary-button label="Print PO" icon="o-printer" wire:click="printPO({{ $viewingPO->id }})"
                            class="btn-outline" />

                        <x-mary-button label="Duplicate PO" icon="o-document-duplicate"
                            wire:click="duplicatePO({{ $viewingPO->id }})" class="btn-outline" />
                    </div>
                </x-mary-card>
            </div>
        @endif

        <x-slot:actions>
            <x-mary-button label="Close" wire:click="$set('showDetailsModal', false)" class="btn-primary" />
        </x-slot:actions>
    </x-mary-modal>
</div>
