<div class="space-y-6">
    <x-mary-header title="Payment Management" subtitle="Collect balances from unpaid and partial invoices" separator>
        <x-slot:actions>
            <x-mary-button label="Clear Filters" icon="o-x-mark" wire:click="clearFilters" />
        </x-slot:actions>
    </x-mary-header>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
        <x-mary-stat title="Open Invoices" value="{{ number_format($summary['count']) }}" icon="o-document-text"
            color="text-primary" />
        <x-mary-stat title="Outstanding Balance" value="₱{{ number_format($summary['total_balance'], 2) }}"
            icon="o-banknotes" color="text-error" />
        <x-mary-stat title="Overdue" value="{{ number_format($summary['overdue_count']) }}"
            icon="o-exclamation-triangle" color="text-warning" />
        <x-mary-stat title="Partial" value="{{ number_format($summary['partial_count']) }}"
            icon="o-receipt-percent" color="text-info" />
    </div>

    <x-mary-card>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <x-mary-input label="Search" wire:model.live.debounce.400ms="search"
                placeholder="Invoice or customer" icon="o-magnifying-glass" />
            <x-mary-select label="Payment Status" wire:model.live="statusFilter" :options="$statusOptions" />
            <x-mary-select label="Due Status" wire:model.live="overdueFilter" :options="$overdueOptions" />
            <div class="flex items-end">
                <x-mary-button label="Reset" icon="o-arrow-path" wire:click="clearFilters" class="w-full" />
            </div>
        </div>
    </x-mary-card>

    <x-mary-card title="Open Invoice Balances">
        <div class="overflow-x-auto">
            <table class="table table-zebra">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Customer</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th class="w-44 text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sales as $sale)
                        <tr>
                            <td>
                                <div class="font-medium">{{ $sale->invoice_number }}</div>
                                <div class="text-xs text-base-content/60">
                                    {{ $sale->warehouse->name }} &middot; {{ $sale->user->name }}
                                </div>
                            </td>
                            <td>
                                <div>{{ $sale->customer->name ?? 'Walk-in Customer' }}</div>
                                <div class="text-xs text-base-content/60">{{ ucfirst($sale->invoice_type ?? 'sales') }} invoice</div>
                            </td>
                            <td>
                                <div>{{ $sale->due_date?->format('M j, Y') ?? 'N/A' }}</div>
                                @if ($sale->days_delayed > 0)
                                    <div class="text-xs font-medium text-error">{{ $sale->days_delayed }} days delayed</div>
                                @else
                                    <div class="text-xs text-base-content/60">Not overdue</div>
                                @endif
                            </td>
                            <td>
                                <x-mary-badge value="{{ $sale->payment_status_label }}"
                                    class="badge-{{ $sale->days_delayed > 0 ? 'error' : 'warning' }} badge-sm" />
                            </td>
                            <td class="font-medium">₱{{ number_format($sale->total_amount, 2) }}</td>
                            <td class="text-success">₱{{ number_format($sale->paid_amount, 2) }}</td>
                            <td class="font-semibold text-error">₱{{ number_format($sale->outstanding_balance, 2) }}</td>
                            <td class="w-44 text-right">
                                <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                    <x-mary-button label="Payment" icon="o-plus" size="sm"
                                        wire:click="openPaymentModal({{ $sale->id }})"
                                        class="btn-primary h-10 min-h-10 w-28 justify-center" />
                                    @if ($sale->customer_id)
                                        <a href="{{ route('soa.preview', $sale->customer_id) }}" target="_blank"
                                            class="btn btn-outline btn-sm h-10 min-h-10 w-28 justify-center gap-2 px-3">
                                            <x-heroicon-o-printer class="w-4 h-4" />
                                            SOA
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center">
                                <div class="py-8">
                                    <x-heroicon-o-check-circle class="w-12 h-12 mx-auto text-success" />
                                    <p class="mt-2 text-base-content/60">No open invoice balances found</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $sales->links() }}
        </div>
    </x-mary-card>

    <x-mary-modal wire:model="showPaymentModal" title="Record Payment"
        subtitle="{{ $selectedSale?->invoice_number }}">
        @if ($selectedSale)
            <div class="space-y-5">
                <div class="grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
                    <div>
                        <div class="text-base-content/60">Customer</div>
                        <div class="font-medium">{{ $selectedSale->customer->name ?? 'Walk-in Customer' }}</div>
                    </div>
                    <div>
                        <div class="text-base-content/60">Due Date</div>
                        <div class="font-medium">{{ $selectedSale->due_date?->format('M j, Y') ?? 'N/A' }}</div>
                    </div>
                    <div>
                        <div class="text-base-content/60">Paid</div>
                        <div class="font-medium text-success">₱{{ number_format($selectedSale->paid_amount, 2) }}</div>
                    </div>
                    <div>
                        <div class="text-base-content/60">Balance</div>
                        <div class="font-semibold text-error">₱{{ number_format($selectedSale->outstanding_balance, 2) }}</div>
                    </div>
                </div>

                <x-mary-input label="Payment Amount" wire:model="paymentAmount" type="number" min="0.01"
                    max="{{ $selectedSale->outstanding_balance }}" step="0.01" prefix="₱" />
                <x-mary-select label="Received Via" wire:model="paymentReceivedVia" :options="$paymentMethodOptions" />
                <x-mary-textarea label="Note" wire:model="paymentNote" rows="3" />
            </div>
        @endif

        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="$set('showPaymentModal', false)" />
            <x-mary-button label="Record Payment" icon="o-check" wire:click="recordPayment" class="btn-primary"
                spinner="recordPayment" />
        </x-slot:actions>
    </x-mary-modal>
</div>
