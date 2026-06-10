<?php

namespace App\Livewire\Sales;

use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class PaymentManagement extends Component
{
    use Toast;
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $overdueFilter = '';

    public bool $showPaymentModal = false;
    public ?Sale $selectedSale = null;
    public string $paymentAmount = '';
    public string $paymentReceivedVia = 'cash';
    public string $paymentNote = '';

    protected array $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'overdueFilter' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingOverdueFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $baseQuery = $this->baseQuery();
        $sales = (clone $baseQuery)
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->orderBy('completed_at', 'desc')
            ->paginate(15);

        $summarySales = (clone $baseQuery)->get();

        return view('livewire.sales.payment-management', [
            'sales' => $sales,
            'summary' => [
                'count' => $summarySales->count(),
                'overdue_count' => $summarySales->filter(fn (Sale $sale) => $sale->days_delayed > 0)->count(),
                'total_balance' => $summarySales->sum(fn (Sale $sale) => $sale->outstanding_balance),
                'partial_count' => $summarySales->where('payment_status', 'partial')->count(),
            ],
            'statusOptions' => [
                ['id' => '', 'name' => 'All Open'],
                ['id' => 'unpaid', 'name' => 'Unpaid'],
                ['id' => 'partial', 'name' => 'Partial'],
            ],
            'overdueOptions' => [
                ['id' => '', 'name' => 'All Due Dates'],
                ['id' => 'overdue', 'name' => 'Overdue Only'],
                ['id' => 'not_overdue', 'name' => 'Not Overdue'],
            ],
            'paymentMethodOptions' => [
                ['id' => 'cash', 'name' => 'Cash'],
                ['id' => 'card', 'name' => 'Credit/Debit Card'],
                ['id' => 'gcash', 'name' => 'GCash'],
                ['id' => 'paymaya', 'name' => 'PayMaya'],
                ['id' => 'bank_transfer', 'name' => 'Bank Transfer'],
            ],
        ])->layout('layouts.app', ['title' => 'Payment Management']);
    }

    public function openPaymentModal(int $saleId): void
    {
        $sale = Sale::with(['customer', 'warehouse', 'user'])
            ->whereKey($saleId)
            ->where('status', 'completed')
            ->firstOrFail();

        if ($sale->is_paid) {
            $this->warning('This invoice is already paid.');
            return;
        }

        $this->selectedSale = $sale;
        $this->paymentAmount = number_format($sale->outstanding_balance, 2, '.', '');
        $this->paymentReceivedVia = 'cash';
        $this->paymentNote = '';
        $this->showPaymentModal = true;
    }

    public function recordPayment(): void
    {
        if (! $this->selectedSale) {
            $this->error('Select an invoice before recording a payment.');
            return;
        }

        $outstandingBalance = Sale::findOrFail($this->selectedSale->id)->outstanding_balance;

        $this->validate([
            'paymentAmount' => ['required', 'numeric', 'min:0.01', 'max:' . $outstandingBalance],
            'paymentReceivedVia' => ['required', 'in:cash,card,gcash,paymaya,bank_transfer'],
            'paymentNote' => ['nullable', 'string', 'max:500'],
        ]);

        $recorded = DB::transaction(function () {
            $sale = Sale::lockForUpdate()->findOrFail($this->selectedSale->id);
            $paymentAmount = round((float) $this->paymentAmount, 2);

            if ($sale->is_paid || $paymentAmount > $sale->outstanding_balance) {
                return false;
            }

            $newPaidAmount = min((float) $sale->total_amount, (float) $sale->paid_amount + $paymentAmount);
            $newBalance = max(0, (float) $sale->total_amount - $newPaidAmount);

            $sale->update([
                'paid_amount' => $newPaidAmount,
                'payment_status' => $newBalance <= 0.009 ? 'paid' : 'partial',
                'change_amount' => 0,
                'notes' => $this->paymentNotes($sale, $paymentAmount),
            ]);

            return true;
        });

        if (! $recorded) {
            $this->warning('This invoice balance changed. Refresh and try again.');
            return;
        }

        $this->showPaymentModal = false;
        $this->selectedSale = null;
        $this->paymentAmount = '';
        $this->paymentNote = '';
        $this->success('Payment recorded successfully.');
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'overdueFilter']);
        $this->resetPage();
    }

    private function baseQuery()
    {
        return Sale::query()
            ->with(['customer', 'warehouse', 'user'])
            ->where('status', 'completed')
            ->where(function ($query) {
                $query->whereNull('payment_status')
                    ->orWhere('payment_status', '!=', 'paid');
            })
            ->whereRaw('total_amount > paid_amount')
            ->when($this->search, function ($query) {
                $query->where(function ($query) {
                    $query->where('invoice_number', 'like', '%' . $this->search . '%')
                        ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', '%' . $this->search . '%'));
                });
            })
            ->when($this->statusFilter, fn ($query) => $query->where('payment_status', $this->statusFilter))
            ->when($this->overdueFilter === 'overdue', fn ($query) => $query->whereDate('due_date', '<', now()->toDateString()))
            ->when($this->overdueFilter === 'not_overdue', function ($query) {
                $query->where(function ($query) {
                    $query->whereNull('due_date')
                        ->orWhereDate('due_date', '>=', now()->toDateString());
                });
            });
    }

    private function paymentNotes(Sale $sale, float $paymentAmount): string
    {
        $method = ucfirst(str_replace('_', ' ', $this->paymentReceivedVia));
        $actor = auth()->user()?->name ?? 'System';
        $note = trim($this->paymentNote);
        $entry = now()->format('Y-m-d H:i') . " payment: PHP " . number_format($paymentAmount, 2) . " via {$method} by {$actor}.";

        if ($note !== '') {
            $entry .= " Note: {$note}";
        }

        return trim(($sale->notes ? $sale->notes . PHP_EOL : '') . $entry);
    }
}
