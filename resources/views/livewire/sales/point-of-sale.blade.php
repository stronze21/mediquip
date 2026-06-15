<div class="w-full max-w-none">
    <style>
        main:has([data-invoice-page]) > .max-w-7xl,
        main:has([data-invoice-page]) > .container {
            max-width: none;
        }
    </style>

    <div data-invoice-page></div>

    <x-mary-header title="Sales Invoices" subtitle="Create invoice drafts and process customer payments" separator>
        @unless ($showInvoiceForm)
            <x-slot:middle class="!justify-end">
                <x-mary-input placeholder="Search invoices..." wire:model.live.debounce="invoiceSearch" clearable
                    icon="o-magnifying-glass" />
            </x-slot:middle>
        @endunless
        <x-slot:actions>
            @if ($showInvoiceForm)
                <x-mary-button icon="o-arrow-left" wire:click="closeInvoiceForm" class="btn-ghost">
                    Back to Invoices
                </x-mary-button>
            @else
                <x-mary-button icon="o-plus" wire:click="openInvoiceForm" class="btn-primary">
                    Create Invoice
                </x-mary-button>
            @endif
        </x-slot:actions>
    </x-mary-header>

    @if ($showInvoiceForm)
    <x-mary-card class="mb-4">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-2xl font-bold">
                    {{ $editingInvoiceId ? 'Edit Invoice Draft' : 'Create Invoice Draft' }}
                </h2>
                <p class="text-sm text-gray-500">
                    Save an invoice as a draft while preparing it, or process it when payment details are ready.
                </p>
            </div>
            <x-mary-badge value="Invoice Workflow" class="badge-warning" />
        </div>
    </x-mary-card>

    <div class="space-y-6">
        <x-mary-card>
            <div class="space-y-6">
                {{-- Customer & Invoice Information --}}
                <div>
                    <h3 class="mb-3 text-lg font-semibold">Customer & Invoice Information</h3>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <x-mary-select label="Invoice Type" :options="[
                            ['value' => 'sales', 'label' => 'Sales Invoice'],
                            ['value' => 'service', 'label' => 'Service Invoice'],
                        ]" wire:model.live="invoiceType" option-label="label" option-value="value" />

                        <x-mary-select label="Warehouse *" :options="$warehouses" wire:model="selectedWarehouse"
                            option-value="id" option-label="name" placeholder="Select warehouse" />

                        <x-mary-select label="Customer" :options="$customers" wire:model.live="selectedCustomer"
                            option-value="id" option-label="name" placeholder="Select customer" />

                        <div class="flex items-end gap-2">
                            <x-mary-button label="New Customer" icon="o-user-plus" wire:click="openCustomerModal"
                                class="btn-primary" />
                        </div>
                    </div>
                </div>

                {{-- Customer Details --}}
                <div>
                    <h3 class="mb-3 text-lg font-semibold">Customer Details</h3>
                    @if ($selectedCustomer)
                        @php $customer = \App\Models\Customer::find($selectedCustomer) @endphp
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="p-4 rounded-lg bg-base-200">
                                <div class="text-xs text-gray-500">Customer</div>
                                <div class="font-semibold">{{ $customer?->name }}</div>
                            </div>
                            <div class="p-4 rounded-lg bg-base-200">
                                <div class="text-xs text-gray-500">Email</div>
                                <div class="font-semibold">{{ $customer?->email ?: 'Not set' }}</div>
                            </div>
                            <div class="p-4 rounded-lg bg-base-200">
                                <div class="text-xs text-gray-500">Phone</div>
                                <div class="font-semibold">{{ $customer?->phone ?: 'Not set' }}</div>
                            </div>
                            <div class="p-4 rounded-lg bg-base-200">
                                <div class="text-xs text-gray-500">Tax ID</div>
                                <div class="font-semibold">{{ $customer?->tax_id ?: 'Not set' }}</div>
                            </div>
                            <div class="p-4 rounded-lg md:col-span-2 bg-base-200">
                                <div class="text-xs text-gray-500">Address</div>
                                <div class="font-semibold">{{ $customer?->address ?: 'Not set' }}</div>
                            </div>
                        </div>
                    @else
                        <div class="p-4 rounded-lg bg-base-200">
                            <div class="text-sm text-gray-500">
                                No customer selected. Select an existing customer or create a new customer before saving or processing this invoice.
                            </div>
                        </div>
                    @endif
                </div>

                <x-mary-textarea label="Invoice Notes" wire:model="saleNotes"
                    placeholder="Additional notes for this invoice" />

            </div>
        </x-mary-card>

        {{-- Left Panel - Invoice Line Entry --}}
        <div class="space-y-6">

            {{-- Line Entry --}}
            <x-mary-card title="Add Invoice Lines" class="{{ $invoiceType === 'sales' ? 'hidden' : '' }}">
                @if ($invoiceType === 'sales')
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <div class="font-medium">Product line pricing</div>
                            <p class="text-sm text-base-content/60">
                                Add an item row, choose the product, then enter the agreed unit price for this client.
                            </p>
                        </div>
                        <x-mary-button label="Add Item" icon="o-plus" wire:click="addInvoiceItem"
                            class="btn-primary" />
                    </div>
                @endif

                @if ($invoiceType === 'service')
                    <div class="flex gap-2">
                        <div class="flex-1">
                            <x-mary-input wire:model.live.debounce.300ms="searchService"
                                placeholder="Search services (labor, diagnostics, etc.)" icon="o-wrench-screwdriver"
                                class="w-full" />
                        </div>
                        <x-mary-button wire:click="openServiceModal" icon="o-plus" class="btn-primary"
                            tooltip="Browse Services" />
                    </div>

                    {{-- Service Search Results --}}
                    @if (!empty($serviceResults))
                        <div class="mt-2 border rounded-lg shadow-sm bg-base-100 border-base-300">
                            @foreach ($serviceResults as $service)
                                <div class="flex items-center justify-between p-3 border-b last:border-b-0 border-base-200 hover:bg-base-50"
                                    wire:click="addServiceToCart({{ $service->id }})" role="button">
                                    <div class="flex-1">
                                        <div class="font-medium">{{ $service->name }}</div>
                                        <div class="text-sm text-base-content/60">
                                            {{ $service->code }} ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢ {{ $service->service_type }} ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢
                                            {{ $service->formatted_duration }}
                                        </div>
                                        @if ($service->description)
                                            <div class="text-xs text-base-content/50">
                                                {{ Str::limit($service->description, 50) }}</div>
                                        @endif
                                    </div>
                                    <div class="text-right">
                                        <div class="font-bold text-primary">{{ $service->formatted_price }}</div>
                                        @if ($service->requires_parts)
                                            <div class="text-xs badge badge-warning">Requires Parts</div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif
            </x-mary-card>
            {{-- Invoice Line Items --}}
            <x-mary-card>
                {{-- Invoice Items Header with Bulk Actions --}}
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold">Invoice Items ({{ $this->invoiceLineCount() }} items)</h3>
                    <div class="flex gap-2">
                        @if ($invoiceType === 'sales')
                            <x-mary-button icon="o-plus" wire:click="addInvoiceItem" class="btn-sm btn-primary"
                                label="Add Item" />
                        @endif
                        @if ($this->hasSelectedInvoiceProductLines())
                            <x-mary-button icon="o-currency-dollar" wire:click="openBulkPriceSelection"
                                class="btn-sm btn-outline" label="Bulk Price" />
                        @endif
                    </div>
                </div>{{-- Add this before the cart items section --}}
                @if ($this->hasSerialTrackingItems() && !$selectedCustomer)
                    <div class="p-3 mb-4 border rounded-lg bg-warning/10 border-warning/20">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-warning" />
                            <div class="text-sm">
                                <p class="font-medium text-warning">Customer Required</p>
                                <p class="text-warning/80">This cart contains items that require serial number tracking.
                                    Please select a customer to continue.</p>
                            </div>
                        </div>
                    </div>
                @endif
                @if (count($cartItems) > 0)
                    <div class="space-y-3">
                        <div class="overflow-x-auto">
                            <table class="table table-sm min-w-[1120px]">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Quantity</th>
                                        <th>VAT</th>
                                        <th>Unit Price</th>
                                        <th>Total</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($cartItems as $cartKey => $item)
                                        <tr>
                                            <td class="min-w-96">
                                                @if ($item['item_type'] === 'service')
                                                    <div class="flex items-start gap-2">
                                                        <x-mary-icon name="o-wrench-screwdriver" class="w-4 h-4 mt-1 text-primary" />
                                                        <div>
                                                            <div class="font-medium">{{ $item['name'] }}</div>
                                                            <div class="text-sm text-base-500">{{ $item['code'] }}</div>
                                                        </div>
                                                    </div>
                                                @else
                                                    <x-mary-choices-offline :options="$products"
                                                        wire:model.live="cartItems.{{ $cartKey }}.product_id"
                                                        wire:change="selectInvoiceProduct('{{ $cartKey }}', $event.target.value)"
                                                        option-value="id" option-label="name"
                                                        placeholder="Select product" single clearable searchable />

                                                    @if (!empty($item['product_id']))
                                                        <div class="mt-1 text-xs text-base-400">
                                                            SKU: {{ $item['code'] ?: '-' }}
                                                            @if (isset($item['available_stock']))
                                                                <span class="ml-2">Stock: {{ $item['available_stock'] }}</span>
                                                            @endif
                                                        </div>
                                                    @endif

                                                    @if (isset($item['track_serial']) && $item['track_serial'])
                                                        @php
                                                            $serialCount = count($item['serial_numbers'] ?? []);
                                                            $required = $item['quantity'];
                                                        @endphp
                                                        <div class="mt-1 text-xs">
                                                            @if ($serialCount === $required)
                                                                <span class="text-success">{{ $serialCount }}/{{ $required }} serials</span>
                                                            @elseif(!$selectedCustomer)
                                                                <span class="text-error">Customer required</span>
                                                            @else
                                                                <span class="text-warning">{{ $serialCount }}/{{ $required }} serials</span>
                                                            @endif
                                                        </div>
                                                    @endif
                                                @endif
                                            </td>
                                            <td class="min-w-36">
                                                <div class="flex items-center gap-1">
                                                    <x-mary-button icon="o-minus"
                                                        wire:click="decreaseQuantity('{{ $cartKey }}')" class="btn-xs btn-ghost" />
                                                    <x-mary-input wire:model.blur="cartItems.{{ $cartKey }}.quantity"
                                                        wire:change="updateCartItemQuantity('{{ $cartKey }}', $event.target.value)"
                                                        class="w-16 text-center input-xs" />
                                                    <x-mary-button icon="o-plus"
                                                        wire:click="increaseQuantity('{{ $cartKey }}')" class="btn-xs btn-ghost"
                                                        :disabled="$item['item_type'] === 'product' && isset($item['available_stock']) && $item['available_stock'] !== null && $item['quantity'] >= $item['available_stock']" />
                                                </div>
                                            </td>
                                            <td class="min-w-72">
                                                <x-mary-select :options="$this->taxOptions()"
                                                    wire:model.live="cartItems.{{ $cartKey }}.tax_type"
                                                    option-value="value" option-label="label"
                                                    class="w-72 min-w-72" />
                                            </td>
                                            <td class="min-w-40">
                                                <div class="flex items-center gap-1">
                                                    <x-mary-input wire:model.blur="cartItems.{{ $cartKey }}.price"
                                                        wire:change="updatePrice('{{ $cartKey }}', $event.target.value)"
                                                        type="number" step="0.01" min="0" class="text-right input-xs" />
                                                    @if ($item['item_type'] === 'product' && !empty($item['product_id']))
                                                        <x-mary-button icon="o-ellipsis-vertical"
                                                            wire:click="openPriceSelection('{{ $cartKey }}')"
                                                            class="btn-xs btn-ghost" title="Select preset price" />
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="font-bold text-right min-w-28">
                                                &#8369;{{ number_format($item['subtotal'], 2) }}
                                            </td>
                                            <td>
                                                <div class="flex items-center gap-1">
                                                    @if ($item['item_type'] === 'product' && isset($item['track_serial']) && $item['track_serial'])
                                                        @if ($selectedCustomer)
                                                            <x-mary-button icon="o-qr-code"
                                                                wire:click="openSerialModal('{{ $cartKey }}')"
                                                                class="btn-xs {{ count($item['serial_numbers'] ?? []) === $item['quantity'] ? 'btn-success' : 'btn-warning' }}"
                                                                title="Enter Serial Numbers" />
                                                        @else
                                                            <x-mary-button icon="o-qr-code" class="btn-xs btn-error btn-disabled" disabled
                                                                title="Select customer first to enter serial numbers" />
                                                        @endif
                                                    @endif
                                                    <x-mary-button icon="o-trash" wire:click="removeFromCart('{{ $cartKey }}')"
                                                        class="btn-xs btn-ghost text-error" />
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    @php $billing = $this->calculateBilling(); @endphp
                                    <tr>
                                        <td colspan="4" class="text-right">{{ $this->subtotalLabel() }}</td>
                                        <td class="font-medium text-right">&#8369;{{ number_format($this->displaySubtotalAmount(), 2) }}</td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="text-right">{{ $this->taxLabel($billing['tax_type']) }}:</td>
                                        <td class="font-medium text-right">&#8369;{{ number_format($taxAmount, 2) }}</td>
                                        <td></td>
                                    </tr>
                                    <tr class="font-bold">
                                        <td colspan="4" class="text-right">Total Amount:</td>
                                        <td class="text-right">&#8369;{{ number_format($totalAmount, 2) }}</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        @if (false)
                        @foreach ($cartItems as $cartKey => $item)
                            <div class="flex items-center gap-4 p-3 border rounded-lg bg-base-50">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        {{-- Item Type Icon --}}
                                        @if ($item['item_type'] === 'service')
                                            <x-mary-icon name="o-wrench-screwdriver" class="w-4 h-4 text-primary" />
                                        @else
                                            <x-mary-icon name="o-cube" class="w-4 h-4 text-secondary" />
                                        @endif
                                        <div class="font-medium">{{ $item['name'] }}</div>
                                    </div>
                                    <div class="text-sm text-base-500">{{ $item['code'] }}</div>

                                    {{-- Stock Info for Products Only --}}
                                    @if ($item['item_type'] === 'product' && isset($item['available_stock']))
                                        <div class="text-xs text-base-400">Stock: {{ $item['available_stock'] }}</div>
                                    @endif
                                </div>

                                {{-- Serial Number Status (Products Only) --}}
                                @if ($item['item_type'] === 'product' && isset($item['track_serial']) && $item['track_serial'])
                                    <div class="mt-1 text-xs">
                                        @php
                                            $serialCount = count($item['serial_numbers'] ?? []);
                                            $required = $item['quantity'];
                                        @endphp
                                        @if ($serialCount === $required)
                                            <span class="text-success">ÃƒÂ¢Ã…â€œÃ¢â‚¬Å“ {{ $serialCount }}/{{ $required }}
                                                serials</span>
                                        @elseif(!$selectedCustomer)
                                            <Customer required</span>
                                        @else
                                            <span class="text-warning">ÃƒÂ¢Ã…Â¡Ã‚Â  {{ $serialCount }}/{{ $required }}
                                                serials</span>
                                        @endif
                                    </div>
                                @endif

                                {{-- Quantity Controls --}}
                                <div class="flex items-center gap-2">
                                    <x-mary-button icon="o-minus"
                                        wire:click="decreaseQuantity('{{ $cartKey }}')" class="btn-xs btn-ghost"
                                        />
                                    <x-mary-input wire:model.blur="cartItems.{{ $cartKey }}.quantity"
                                        wire:change="updateCartItemQuantity('{{ $cartKey }}', $event.target.value)"
                                        class="w-16 text-center input-xs" />
                                    <x-mary-button icon="o-plus"
                                        wire:click="increaseQuantity('{{ $cartKey }}')" class="btn-xs btn-ghost"
                                        :disabled="$item['item_type'] === 'product' && $item['quantity'] >= ($item['available_stock'] ?? 0)" />
                                </div>

                                {{-- Serial Button (Products Only) --}}
                                @if ($item['item_type'] === 'product' && isset($item['track_serial']) && $item['track_serial'])
                                    @if ($selectedCustomer)
                                        <x-mary-button icon="o-qr-code"
                                            wire:click="openSerialModal('{{ $cartKey }}')"
                                            class="btn-xs {{ count($item['serial_numbers'] ?? []) === $item['quantity'] ? 'btn-success' : 'btn-warning' }}"
                                            title="Enter Serial Numbers" />
                                    @else
                                        <x-mary-button icon="o-qr-code" class="btn-xs btn-error btn-disabled" disabled
                                            title="Select customer first to enter serial numbers" />
                                    @endif
                                @endif

                                {{-- Price with selection button (Products Only) --}}
                                @if ($item['item_type'] === 'product')
                                    <div class="w-32">
                                        <div class="flex items-center gap-1">
                                            <x-mary-input wire:model.blur="cartItems.{{ $cartKey }}.price"
                                                wire:change="updatePrice('{{ $cartKey }}', $event.target.value)"
                                                class="text-right input-xs" />
                                            <x-mary-button icon="o-ellipsis-vertical"
                                                wire:click="openPriceSelection('{{ $cartKey }}')"
                                                class="btn-xs btn-ghost" title="Select price" />
                                        </div>
                                    </div>
                                @else
                                    {{-- Services have fixed price --}}
                                    <div class="w-32">
                                        <div class="text-sm text-right text-base-500">
                                            &#8369;{{ number_format($item['price'], 2) }}
                                        </div>
                                    </div>
                                @endif

                                {{-- Subtotal --}}
                                <div class="w-20 font-bold text-right">
                                    &#8369;{{ number_format($item['subtotal'], 2) }}
                                </div>

                                {{-- Remove Button --}}
                                <x-mary-button icon="o-trash" wire:click="removeFromCart('{{ $cartKey }}')"
                                    class="btn-xs btn-ghost text-error" />
                            </div>
                        @endforeach
                        @endif

                        {{-- Invoice Actions --}}
                        <div class="flex gap-2 pt-4 border-t">
                            <x-mary-button label="Clear Items" wire:click="clearCart" class="btn-ghost btn-sm" />
                            <x-mary-button label="Save Draft" wire:click="saveInvoiceDraft"
                                class="btn-outline btn-sm" :disabled="!$selectedCustomer || $this->hasIncompleteInvoiceProductLines()" />
                        </div>
                    </div>
                @else
                    <div class="py-8 text-center">
                        <x-heroicon-o-shopping-cart class="w-12 h-12 mx-auto text-base-400" />
                        <p class="mt-2 text-base-500">No invoice items yet</p>
                        <p class="text-sm text-base-400">
                            Add a line, select the product, then enter the client-specific price.
                        </p>
                        @if ($invoiceType === 'sales')
                            <x-mary-button label="Add First Item" icon="o-plus" wire:click="addInvoiceItem"
                                class="mt-4 btn-primary" />
                        @endif
                    </div>
                @endif
            </x-mary-card>
        </div>

    </div>

    @php
        $canCheckout = $this->invoiceLineCount() > 0 && !$this->hasIncompleteInvoiceProductLines() && $this->validateCustomerForSerials();
    @endphp

    <div class="sticky bottom-0 z-10 p-4 mt-6 border rounded-lg bg-base-100/95 backdrop-blur">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-end">
            @if (!$selectedCustomer)
                <p class="text-xs text-error md:mr-auto">
                    Customer is required before saving or processing this invoice.
                </p>
            @endif
            <x-mary-button label="Cancel" wire:click="closeInvoiceForm" class="btn-ghost" />
            <x-mary-button label="Save Draft" wire:click="saveInvoiceDraft" class="btn-outline"
                :disabled="!$selectedCustomer || $this->hasIncompleteInvoiceProductLines()" />
            <x-mary-button label="Process Invoice" wire:click="openPaymentModal" class="btn-primary"
                :disabled="!$canCheckout" />
        </div>
    </div>

    @else
        <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-5">
            <x-mary-select placeholder="All Customers" :options="$filterOptions['customers']"
                wire:model.live="customerFilter" option-value="value" option-label="label" />
            <x-mary-select placeholder="All Status" :options="$filterOptions['statuses']"
                wire:model.live="invoiceStatusFilter" option-value="value" option-label="label" />
            <x-mary-select placeholder="All Types" :options="$filterOptions['types']"
                wire:model.live="invoiceTypeFilter" option-value="value" option-label="label" />
            <x-mary-select placeholder="All Dates" :options="$filterOptions['dates']"
                wire:model.live="invoiceDateFilter" option-value="value" option-label="label" />
            <x-mary-button label="Clear Filters" wire:click="clearInvoiceFilters" class="btn-outline" />
        </div>

        <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-4">
            <x-mary-stat title="Total Invoices" value="{{ \App\Models\Sale::count() }}" icon="o-document-text"
                color="text-primary" />
            <x-mary-stat title="Drafts" value="{{ \App\Models\Sale::where('status', 'draft')->count() }}"
                icon="o-pencil-square" color="text-warning" />
            <x-mary-stat title="Completed" value="{{ \App\Models\Sale::where('status', 'completed')->count() }}"
                icon="o-check-circle" color="text-success" />
            <x-mary-stat title="Outstanding"
                value="₱{{ number_format(\App\Models\Sale::whereIn('payment_status', ['unpaid', 'partial'])->sum(\DB::raw('total_amount - paid_amount')), 2) }}"
                icon="o-banknotes" color="text-info" />
        </div>

        <x-mary-card>
            <div class="overflow-x-auto">
                <table class="table table-zebra">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Customer</th>
                            <th>Type</th>
                            <th>Warehouse</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($invoices as $invoice)
                            <tr>
                                <td class="font-semibold">{{ $invoice->invoice_number }}</td>
                                <td>{{ $this->customerDisplayName($invoice->customer) }}</td>
                                <td>{{ $invoice->invoice_type === 'service' ? 'Service' : 'Sales' }}</td>
                                <td>{{ $invoice->warehouse?->name ?? '-' }}</td>
                                <td>{{ $invoice->items->count() }}</td>
                                <td class="font-bold">&#8369;{{ number_format($invoice->total_amount, 2) }}</td>
                                <td>
                                    <x-mary-badge value="{{ $invoice->payment_status_label }}"
                                        class="badge-{{ $invoice->is_paid ? 'success' : ($invoice->payment_status === 'partial' ? 'warning' : 'error') }}" />
                                </td>
                                <td>
                                    <x-mary-badge value="{{ ucfirst($invoice->status) }}"
                                        class="badge-{{ $invoice->status === 'completed' ? 'success' : ($invoice->status === 'draft' ? 'warning' : 'neutral') }}" />
                                </td>
                                <td>{{ $invoice->created_at->format('M d, Y') }}</td>
                                <td>
                                    <div class="flex gap-1">
                                        @if ($invoice->status === 'draft')
                                            <x-mary-button icon="o-pencil" wire:click="editInvoice({{ $invoice->id }})"
                                                class="btn-xs btn-ghost" tooltip="Edit draft" />
                                            <x-mary-button icon="o-credit-card"
                                                wire:click="processInvoice({{ $invoice->id }})"
                                                class="btn-xs btn-primary" tooltip="Process invoice" />
                                        @else
                                            <a href="{{ route('invoice.preview', $invoice) }}" target="_blank"
                                                class="btn btn-xs btn-ghost" title="Preview invoice">
                                                <x-mary-icon name="o-eye" class="w-4 h-4" />
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="py-8 text-center">
                                    <div class="flex flex-col items-center">
                                        <x-heroicon-o-document-text class="w-12 h-12 mb-2 text-gray-400" />
                                        <p class="text-gray-500">No invoices found</p>
                                        <x-mary-button label="Create First Invoice" wire:click="openInvoiceForm"
                                            class="mt-4 btn-primary" />
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-mary-card>

        <div class="mt-6">
            {{ $invoices->links() }}
        </div>
    @endif

    {{-- Individual Price Selection Modal --}}
    <x-mary-modal wire:model="showPriceModal" title="Select Price" class="backdrop-blur">
        <div class="space-y-3">
            @if ($availablePrices)
                @foreach ($availablePrices as $priceType => $priceData)
                    <div class="flex items-center justify-between p-3 border rounded-lg cursor-pointer hover:bg-base-50"
                        wire:click="selectPrice('{{ $priceType }}')">
                        <span class="font-medium">{{ $priceData['label'] }}</span>
                        <span
                            class="text-lg font-bold text-primary">&#8369;{{ number_format($priceData['value'], 2) }}</span>
                    </div>
                @endforeach
            @endif
        </div>

        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="$set('showPriceModal', false)" />
        </x-slot:actions>
    </x-mary-modal>

    {{-- Add to Cart Price Selection Modal --}}
    <x-mary-modal wire:model="showAddPriceModal" title="Select Price for Adding to Cart" class="backdrop-blur">
        <div class="space-y-3">
            @if ($availablePrices)
                @foreach ($availablePrices as $priceType => $priceData)
                    <div class="flex items-center justify-between p-3 border rounded-lg cursor-pointer hover:bg-base-50"
                        wire:click="addToCartWithPrice('{{ $priceType }}')">
                        <span class="font-medium">{{ $priceData['label'] }}</span>
                        <span
                            class="text-lg font-bold text-primary">&#8369;{{ number_format($priceData['value'], 2) }}</span>
                    </div>
                @endforeach
            @endif
        </div>

        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="$set('showAddPriceModal', false)" />
        </x-slot:actions>
    </x-mary-modal>

    {{-- Bulk Price Selection Modal --}}
    <x-mary-modal wire:model="showBulkPriceModal" title="Apply Bulk Price Change" class="backdrop-blur">
        <div class="space-y-4">
            <p class="text-base-600">Select a price type to apply to all compatible items in the cart:</p>

            <x-mary-radio wire:model="bulkPriceType" :options="[
                ['id' => 'selling_price', 'name' => 'Selling Price (Default)'],
                ['id' => 'wholesale_price', 'name' => 'Wholesale Price'],
                ['id' => 'alt_price1', 'name' => 'Alternative Price 1'],
                ['id' => 'alt_price2', 'name' => 'Alternative Price 2'],
                ['id' => 'alt_price3', 'name' => 'Alternative Price 3'],
            ]" option-value="id" option-label="name" />

            <div class="p-3 border border-yellow-200 rounded-lg bg-yellow-50">
                <p class="text-sm text-yellow-800">
                    <strong>Note:</strong> Only products that have the selected price type available will be updated.
                </p>
            </div>
        </div>

        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="$set('showBulkPriceModal', false)" />
            <x-mary-button label="Apply to All" wire:click="applyBulkPrice" class="btn-primary" />
        </x-slot:actions>
    </x-mary-modal>
    {{-- Payment Modal --}}
    {{-- Enhanced Payment Modal with Additional Change Features --}}
    <x-mary-modal wire:model="showPaymentModal" title="Process Invoice Payment" subtitle="Complete the invoice transaction">
        <div class="space-y-4">
            {{-- Invoice Summary --}}
            <div class="p-4 rounded-lg bg-base-200">
                <h4 class="mb-3 font-semibold">Invoice Summary</h4>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span>Invoice Type:</span>
                        <span>{{ $invoiceType === 'service' ? 'Service Invoice' : 'Sales Invoice' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>{{ $this->subtotalLabel() }}</span>
                        <span>&#8369;{{ number_format($this->displaySubtotalAmount(), 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>{{ $this->taxLabel() }}:</span>
                        <span>&#8369;{{ number_format($taxAmount, 2) }}</span>
                    </div>
                    <div class="flex justify-between pt-2 text-lg font-bold border-t">
                        <span>Total:</span>
                        <span>&#8369;{{ number_format($totalAmount, 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- Payment Method --}}
            <x-mary-select label="Payment Method" :options="[
                ['value' => 'cash', 'label' => 'Cash'],
                ['value' => 'card', 'label' => 'Credit/Debit Card'],
                ['value' => 'gcash', 'label' => 'GCash'],
                ['value' => 'bank_transfer', 'label' => 'Bank Transfer'],
                ['value' => 'terms', 'label' => 'Payment Terms'],
            ]" wire:model.live="paymentMethod"
                option-label="label" option-value="value" />

            @if ($paymentMethod === 'terms')
                <div class="p-3 border rounded-lg bg-warning/10 border-warning/20">
                    <div class="text-sm text-warning">
                        Payment terms require a selected customer. The invoice will be completed with an outstanding
                        balance until fully paid.
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <x-mary-select label="Terms" :options="[
                        ['value' => 'Due on receipt', 'label' => 'Due on receipt'],
                        ['value' => 'Net 7', 'label' => 'Net 7'],
                        ['value' => 'Net 15', 'label' => 'Net 15'],
                        ['value' => 'Net 30', 'label' => 'Net 30'],
                        ['value' => 'Net 60', 'label' => 'Net 60'],
                    ]" wire:model.live="paymentTerms" option-label="label" option-value="value" />

                    <x-mary-input label="Due Date" wire:model="paymentDueDate" type="date" />
                </div>
            @endif

            {{-- Payment Amount --}}
            <div class="space-y-2">
                <x-mary-input label="{{ $paymentMethod === 'terms' ? 'Initial Payment (Optional)' : 'Amount Received' }}"
                    wire:model.live="paidAmount" type="number" step="0.01"
                    class="{{ $paymentMethod !== 'terms' && $paidAmount < $totalAmount ? 'input-error' : ($changeAmount > 0 ? 'input-success' : '') }}" />

                @if ($paymentMethod === 'cash')
                    <div class="flex flex-wrap gap-2">
                        <x-mary-button label="Exact Amount" wire:click="setExactCash" class="btn-outline btn-sm" />
                        {{-- Quick Cash Buttons --}}
                        <x-mary-button &#8369;{{ number_format(ceil($totalAmount / 100) * 100, 0) }}"
                            wire:click="setQuickCash({{ ceil($totalAmount / 100) * 100 }})"
                            class="btn-outline btn-sm" />
                        <x-mary-button &#8369;{{ number_format(ceil($totalAmount / 500) * 500, 0) }}"
                            wire:click="setQuickCash({{ ceil($totalAmount / 500) * 500 }})"
                            class="btn-outline btn-sm" />
                        <x-mary-button &#8369;{{ number_format(ceil($totalAmount / 1000) * 1000, 0) }}"
                            wire:click="setQuickCash({{ ceil($totalAmount / 1000) * 1000 }})"
                            class="btn-outline btn-sm" />
                    </div>
                @endif

                {{-- Payment Status Indicator --}}
                @if ($paymentMethod === 'terms')
                    <div class="text-sm text-warning">
                        Outstanding balance: &#8369;{{ number_format(max(0, $totalAmount - (float) ($paidAmount ?: 0)), 2) }}
                    </div>
                @elseif ($paidAmount > 0)
                    <div class="text-sm">
                        @if ($paidAmount < $totalAmount)
                            <div class="text-error">
                                ÃƒÂ¢Ã…Â¡Ã‚Â ÃƒÂ¯Ã‚Â¸Ã‚Â Insufficient payment: &#8369;{{ number_format($totalAmount - $paidAmount, 2) }} remaining
                            </div>
                        @elseif ($paidAmount == $totalAmount)
                            <div class="text-success">
                                ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ Exact payment received
                            </div>
                        @else
                            <div class="text-info">
                                ÃƒÂ°Ã…Â¸Ã¢â‚¬â„¢Ã‚Â° Overpayment: &#8369;{{ number_format($changeAmount, 2) }} change due
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Enhanced Change Calculation --}}
            @if ($changeAmount > 0)
                <div class="p-4 border rounded-lg bg-success/10 border-success/20">
                    <div class="text-center">
                        <div class="mb-2 text-2xl font-bold text-success">
                            Change: &#8369;{{ number_format($changeAmount, 2) }}
                        </div>
                    </div>
                </div>
            @endif

            {{-- Invoice Notes --}}
            <x-mary-textarea label="Invoice Notes (Optional)" wire:model="saleNotes"
                placeholder="Any additional notes..." rows="2" />
        </div>

        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="$set('showPaymentModal', false)" />
            <x-mary-button label="Complete Invoice" wire:click="completeSale" class="btn-success"
                :disabled="$paymentMethod !== 'terms' && $paidAmount < $totalAmount" />
        </x-slot:actions>
    </x-mary-modal>

    {{-- Barcode Scanner Modal --}}
    <x-mary-modal wire:model="showBarcodeModal" title="Barcode Scanner"
        subtitle="Scan multiple items then add to the invoice" box-class="w-11/12 max-w-4xl">
        <div class="space-y-4">
            {{-- Barcode Input --}}
            <div class="p-4 rounded-lg bg-primary/10">
                <x-mary-input label="Barcode Scanner" wire:model.live="barcodeInput"
                    placeholder="Scan barcode here..." hint="Products will be added automatically as you scan" />
                <div class="flex gap-2 mt-2">
                    <x-mary-button label="Clear" wire:click="clearBarcodeInput" class="btn-ghost btn-sm" />
                    <x-mary-button label="Process" wire:click="processBarcodeInput" class="btn-primary btn-sm" />
                </div>
            </div>

            {{-- Scanned Items --}}
            @if (count($scannedItems) > 0)
                <div>
                    <h4 class="mb-3 font-semibold">Scanned Items ({{ count($scannedItems) }})</h4>
                    <div class="space-y-2 overflow-y-auto max-h-64">
                        @foreach ($scannedItems as $index => $item)
                            <div class="flex items-center gap-3 p-3 border rounded-lg bg-base-200">
                                <div class="flex-1">
                                    <div class="font-medium">{{ $item['name'] }}</div>
                                    <div class="text-sm text-base-500">{{ $item['sku'] }} ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢ Stock:
                                        {{ $item['available_stock'] }}</div>
                                </div>
                                <div class="flex items-center gap-2">
                                    {{-- Quantity Controls --}}
                                    <div class="flex items-center gap-1">
                                        <x-mary-button icon="o-minus"
                                            wire:click="updateScannedItemQuantity({{ $index }}, {{ $item['quantity'] - 1 }})"
                                            class="btn-ghost btn-xs" />
                                        <span
                                            class="font-semibold min-w-[2rem] text-center text-sm">{{ $item['quantity'] }}</span>
                                        <x-mary-button icon="o-plus"
                                            wire:click="updateScannedItemQuantity({{ $index }}, {{ $item['quantity'] + 1 }})"
                                            class="btn-ghost btn-xs" />
                                    </div>

                                    {{-- Price --}}
                                    <div class="text-right min-w-[4rem]">
                                        <div class="text-sm font-semibold">&#8369;{{ number_format($item['subtotal'], 2) }}
                                        </div>
                                    </div>

                                    {{-- Remove Button --}}
                                    <x-mary-button icon="o-x-mark"
                                        wire:click="removeScannedItem({{ $index }})"
                                        class="btn-ghost btn-xs text-error" />
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Batch Total --}}
                    <div class="pt-2 mt-3 border-t border-base-300">
                        <div class="flex justify-between text-sm font-semibold">
                            <span>Batch Total:</span>
                            <span>&#8369;{{ number_format(collect($scannedItems)->sum('subtotal'), 2) }}</span>
                        </div>
                    </div>
                </div>
            @else
                <div class="py-8 text-center">
                    <x-heroicon-o-qr-code class="w-12 h-12 mx-auto text-base-400" />
                    <p class="mt-2 text-base-500">No items scanned yet</p>
                    <p class="text-sm text-base-400">Scan barcodes to add products</p>
                </div>
            @endif

            {{-- Current Invoice Summary --}}
            <div class="p-3 border rounded-lg bg-primary/5 border-primary/20">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-primary-700">Current Invoice</span>
                    <span class="text-xs text-primary-600">{{ count($cartItems) }} items</span>
                </div>
                @if (count($cartItems) > 0)
                    <div class="space-y-1 overflow-y-auto max-h-20">
                        @foreach (array_slice($cartItems, -3, 3, true) as $key => $item)
                            <div class="flex justify-between text-xs text-primary-700">
                                <span class="truncate">{{ $item['name'] }}</span>
                                <span>{{ $item['quantity'] }}x &#8369;{{ number_format($item['price'], 2) }}</span>
                            </div>
                        @endforeach
                        @if (count($cartItems) > 3)
                            <div class="text-xs text-center text-primary-600">... and {{ count($cartItems) - 3 }} more
                                items</div>
                        @endif
                    </div>
                    <div class="pt-2 mt-2 border-t border-primary/20">
                        <div class="flex justify-between text-sm font-semibold text-primary-800">
                            <span>Invoice Total:</span>
                            <span>&#8369;{{ number_format($totalAmount, 2) }}</span>
                        </div>
                    </div>
                @else
                    <div class="py-2 text-xs text-center text-primary-600">Invoice has no items yet</div>
                @endif
            </div>
        </div>

        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="$set('showBarcodeModal', false)" class="btn-ghost" />
            <x-mary-button label="Clear All" wire:click="clearScannedItems" class="btn-outline" />
            <x-mary-button label="Add to Invoice ({{ count($scannedItems) }})" wire:click="addScannedItemsToCart"
                class="btn-primary" :disabled="count($scannedItems) === 0" />
        </x-slot:actions>
    </x-mary-modal>

    {{-- Customer Search Modal --}}
    <x-mary-modal wire:model="showSearchCustomerModal" title="Search Customer" subtitle="Find existing customer">
        <div class="space-y-4">
            <x-mary-input label="Search" wire:model.live.debounce="customerSearch"
                placeholder="Search by name, email, or phone..." icon="o-magnifying-glass" />

            @if (count($customerSearchResults) > 0)
                <div class="space-y-2 overflow-y-auto max-h-64">
                    @foreach ($customerSearchResults as $customer)
                        <div class="p-3 transition-colors border rounded-lg cursor-pointer hover:bg-primary/10 hover:border-primary"
                            wire:click="selectSearchedCustomer({{ $customer['id'] }})">
                            <div>
                                <div class="font-medium">{{ $customer['name'] }}</div>
                                <div class="text-sm text-base-500">
                                    {{ $customer['email'] ?? 'No email' }} ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢ {{ $customer['phone'] ?? 'No phone' }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @elseif (strlen($customerSearch) >= 2)
                <div class="py-8 text-center">
                    <x-heroicon-o-user-minus class="w-12 h-12 mx-auto text-base-400" />
                    <p class="mt-2 text-base-500">No customers found</p>
                </div>
            @endif
        </div>

        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="$set('showSearchCustomerModal', false)" />
            <x-mary-button label="Create New Customer" wire:click="openCustomerModal" class="btn-primary" />
        </x-slot:actions>
    </x-mary-modal>

    {{-- New Customer Modal --}}
    <x-mary-modal wire:model="showCustomerModal" title="Create New Customer" subtitle="Add customer information">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <x-mary-input label="Full Name" wire:model="customerName" placeholder="Customer name"
                class="md:col-span-2" />
            <x-mary-input label="Email" wire:model="customerEmail" placeholder="customer@example.com" />
            <x-mary-input label="Phone" wire:model="customerPhone" placeholder="Phone number" />
            <x-mary-textarea label="Address" wire:model="customerAddress" placeholder="Customer address"
                rows="2" class="md:col-span-2" />
        </div>

        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="$set('showCustomerModal', false)" />
            <x-mary-button label="Create Customer" wire:click="createCustomer" class="btn-primary" />
        </x-slot:actions>
    </x-mary-modal>
    {{-- Hold Sale Modal --}}
    <x-mary-modal wire:model="showHoldSaleModal" title="Hold Sale" subtitle="Save current sale for later">
        <div class="space-y-4">
            <x-mary-input label="Reference Name" wire:model="holdReference" placeholder="Hold reference" />
            <x-mary-textarea label="Notes" wire:model="holdNotes"
                placeholder="Optional notes about this held sale..." rows="3" />

            <div class="p-4 rounded-lg bg-warning/10">
                <div class="flex items-start space-x-2">
                    <x-heroicon-o-information-circle class="w-5 h-5 mt-0.5 text-warning" />
                    <div class="text-sm">
                        <p class="font-medium text-warning">Hold Sale Information:</p>
                        <ul class="mt-1 space-y-1 text-base-700">
                            <li>ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢ Current cart will be saved and cleared</li>
                            <li>ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢ You can retrieve this sale later from "Held Sales"</li>
                            <li>ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢ Customer information will be preserved</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="$set('showHoldSaleModal', false)" />
            <x-mary-button label="Hold Sale" wire:click="holdSale" class="btn-warning" />
        </x-slot:actions>
    </x-mary-modal>

    {{-- Held Sales Modal --}}
    <x-mary-modal wire:model="showHeldSalesModal" title="Held Sales" subtitle="Retrieve previously held sales"
        box-class="w-11/12 max-w-4xl">
        @if (count($heldSales) > 0)
            <div class="overflow-x-auto">
                <table class="table table-zebra">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Date/Time</th>
                            <th>Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($heldSales as $heldSale)
                            <tr>
                                <td class="font-medium">{{ $heldSale['invoice_number'] }}</td>
                                <td>{{ $heldSale['customer_name'] }}</td>
                                <td>{{ $heldSale['items_count'] }} items</td>
                                <td class="font-bold">&#8369;{{ number_format($heldSale['total_amount'], 2) }}</td>
                                <td class="text-sm">{{ $heldSale['created_at'] }}</td>
                                <td class="text-sm">
                                    @if ($heldSale['notes'])
                                        {{ str_replace('HELD SALE: ', '', $heldSale['notes']) }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    <div class="flex gap-1">
                                        <x-mary-button label="Retrieve"
                                            wire:click="retrieveHeldSale({{ $heldSale['id'] }})"
                                            class="btn-primary btn-xs" />
                                        <x-mary-button icon="o-trash"
                                            wire:click="deleteHeldSale({{ $heldSale['id'] }})"
                                            wire:confirm="Delete this held sale?"
                                            class="btn-ghost btn-xs text-error" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="py-8 text-center">
                <x-heroicon-o-document-text class="w-12 h-12 mx-auto text-base-400" />
                <p class="mt-2 text-base-500">No held sales found</p>
                <p class="text-sm text-base-400">Hold a sale to see it here</p>
            </div>
        @endif

        <x-slot:actions>
            <x-mary-button label="Close" wire:click="$set('showHeldSalesModal', false)" />
        </x-slot:actions>
    </x-mary-modal>

    {{-- Serial Number Entry Modal --}}
    <x-mary-modal wire:model="showSerialModal" title="Enter Serial Numbers" persistent box-class="w-11/12 max-w-2xl">
        <div class="space-y-4">
            <div class="text-sm text-gray-600">
                Enter {{ $requiredSerials }} serial number(s) for this product.
                Current: {{ count($enteredSerials) }}/{{ $requiredSerials }}
            </div>

            {{-- Serial input --}}
            <div class="flex gap-2">
                <x-mary-input wire:model="serialInput" placeholder="Scan or enter serial number"
                    wire:keydown.enter="addSerialNumber" class="flex-1"
                    hint="Enter any serial number - will be created if doesn't exist" />
                <x-mary-button label="Add" wire:click="addSerialNumber" class="btn-primary" />
            </div>

            {{-- Bulk entry section --}}
            <div class="pt-4 border-t">
                <h4 class="mb-2 text-sm font-medium">Bulk Entry (Optional)</h4>
                <x-mary-textarea wire:model="bulkSerialInput"
                    placeholder="Enter multiple serial numbers, one per line&#10;SN001&#10;SN002&#10;SN003"
                    rows="3" class="text-sm" />
                <div class="flex gap-2 mt-2">
                    <x-mary-button label="Add All" wire:click="addBulkSerials" class="btn-sm btn-outline" />
                    <span class="self-center text-xs text-gray-500">
                        Remaining: {{ $requiredSerials - count($enteredSerials) }}
                    </span>
                </div>
            </div>

            {{-- Quick actions --}}
            <div class="flex gap-2">
                @if (count($enteredSerials) < $requiredSerials)
                    <x-mary-button label="Auto Generate" wire:click="generateSerialNumbers"
                        class="btn-sm btn-outline" />
                @endif
                @if (count($enteredSerials) > 0)
                    <x-mary-button label="Clear All" wire:click="$set('enteredSerials', [])"
                        class="btn-sm btn-ghost" />
                @endif
            </div>

            {{-- Entered serials list --}}
            @if (count($enteredSerials) > 0)
                <div class="space-y-2">
                    <h4 class="font-medium">Entered Serial Numbers:</h4>
                    <div class="p-2 space-y-1 overflow-y-auto border rounded max-h-40">
                        @foreach ($enteredSerials as $index => $serial)
                            <div class="flex items-center justify-between p-2 rounded bg-gray-50">
                                <span class="font-mono text-sm">{{ $serial }}</span>
                                <x-mary-button wire:click="removeSerialNumber({{ $index }})"
                                    class="text-red-500 btn-xs btn-ghost" icon="o-trash" />
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Progress indicator --}}
            <div class="w-full h-2 bg-gray-200 rounded-full">
                <div class="h-2 transition-all duration-300 rounded-full bg-primary"
                    style="width: {{ count($enteredSerials) > 0 ? (count($enteredSerials) / $requiredSerials) * 100 : 0 }}%">
                </div>
            </div>
        </div>

        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="$set('showSerialModal', false)" />
            @if (count($enteredSerials) === $requiredSerials)
                <x-mary-button label="Save Serials" wire:click="saveSerialNumbers" class="btn-primary" />
            @else
                <x-mary-button label="Save Serials" class="btn-primary btn-disabled" disabled />
            @endif
        </x-slot:actions>
    </x-mary-modal>

    {{-- Service Modal --}}
    <x-mary-modal wire:model="showServiceModal" title="Browse Services" box-class="w-11/12 max-w-4xl">
        <div class="space-y-4">
            <x-mary-input wire:model.live.debounce.300ms="searchService" placeholder="Search services..."
                icon="o-magnifying-glass" />

            @if (!empty($serviceResults))
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    @foreach ($serviceResults as $service)
                        <div class="p-4 border rounded-lg border-base-300 hover:border-primary hover:bg-base-50">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h4 class="font-semibold">{{ $service->name }}</h4>
                                    <p class="text-sm text-base-content/60">{{ $service->code }}</p>
                                    @if ($service->description)
                                        <p class="mt-1 text-sm text-base-content/70">{{ $service->description }}</p>
                                    @endif
                                    <div class="flex gap-2 mt-2">
                                        <span
                                            class="badge badge-sm badge-outline">{{ $service->service_type }}</span>
                                        <span
                                            class="badge badge-sm badge-outline">{{ $service->formatted_duration }}</span>
                                        @if ($service->requires_parts)
                                            <span class="badge badge-sm badge-warning">Requires Parts</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-lg font-bold text-primary">{{ $service->formatted_price }}</div>
                                    <x-mary-button wire:click="addServiceToCart({{ $service->id }})"
                                        class="mt-2 btn-sm btn-primary">
                                        Add to Cart
                                    </x-mary-button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="py-8 text-center text-base-content/60">
                    @if (strlen($searchService) >= 2)
                        No services found matching "{{ $searchService }}"
                    @else
                        Enter at least 2 characters to search services
                    @endif
                </div>
            @endif
        </div>

        <x-slot:actions>
            <x-mary-button wire:click="closeServiceModal">Close</x-mary-button>
        </x-slot:actions>
    </x-mary-modal>



    {{-- Barcode Input Focus Script --}}
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('barcode-modal-opened', () => {
                setTimeout(() => {
                    const barcodeInput = document.querySelector(
                        'input[wire\\:model\\.live="barcodeInput"]');
                    if (barcodeInput) {
                        barcodeInput.focus();
                    }
                }, 100);
            });

            // Auto-focus barcode input when modal opens
            Livewire.on('focus-barcode-input', () => {
                setTimeout(() => {
                    const input = document.getElementById('barcode-input') ||
                        document.querySelector('input[wire\\:model\\.live="barcodeInput"]');
                    if (input) {
                        input.focus();
                        input.select();
                    }
                }, 100);
            });

            // Handle keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // F1 - Open barcode scanner
                if (e.key === 'F1') {
                    e.preventDefault();
                    Livewire.dispatch('open-barcode-modal');
                }

                // F2 - Open payment modal (if cart has items)
                if (e.key === 'F2') {
                    e.preventDefault();
                    Livewire.dispatch('open-payment-modal');
                }

                // F3 - Clear cart
                if (e.key === 'F3') {
                    e.preventDefault();
                    if (confirm('Clear all items from cart?')) {
                        Livewire.dispatch('clear-cart');
                    }
                }

                // F4 - Hold sale
                if (e.key === 'F4') {
                    e.preventDefault();
                    Livewire.dispatch('open-hold-sale-modal');
                }

                // F5 - View held sales
                if (e.key === 'F5') {
                    e.preventDefault();
                    Livewire.dispatch('open-held-sales-modal');
                }

                // Escape - Close any open modal
                if (e.key === 'Escape') {
                    Livewire.dispatch('close-all-modals');
                }
            });

            // Auto-submit barcode when Enter is pressed
            document.addEventListener('keypress', function(e) {
                if (e.target && e.target.getAttribute('wire:model.live') === 'barcodeInput') {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        Livewire.dispatch('process-barcode');
                    }
                }
            });
        });

        // Print function for receipts
        function printReceipt() {
            window.print();
        }

        // Notification sound for successful operations
        function playSuccessSound() {
            // Create a simple beep sound
            const audioContext = new(window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);

            oscillator.frequency.value = 800;
            oscillator.type = 'sine';

            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1);

            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.1);
        }

        // Listen for Livewire events to play sounds
        document.addEventListener('livewire:init', () => {
            Livewire.on('sale-completed', () => {
                playSuccessSound();
            });

            Livewire.on('item-added-to-cart', () => {
                // Shorter beep for item additions
                const audioContext = new(window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();

                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);

                oscillator.frequency.value = 600;
                oscillator.type = 'sine';

                gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.05);

                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + 0.05);
            });
        });
    </script>

    {{-- Print Styles for Receipt --}}
    <style>
        @media print {
            body * {
                visibility: hidden;
            }

            .receipt-content,
            .receipt-content * {
                visibility: visible;
            }

            .receipt-content {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }

            /* Hide all buttons and UI elements when printing */
            .btn,
            button,
            .modal,
            .navbar {
                display: none !important;
            }
        }

        /* Receipt styling */
        .receipt-content {
            font-family: 'Courier New', monospace;
            max-width: 300px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            background: white;
        }

        .receipt-header {
            text-align: center;
            border-bottom: 1px dashed #333;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }

        .receipt-items {
            border-bottom: 1px dashed #333;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }

        .receipt-total {
            text-align: right;
            font-weight: bold;
        }

        .receipt-footer {
            text-align: center;
            margin-top: 10px;
            font-size: 12px;
        }
    </style>
</div>
