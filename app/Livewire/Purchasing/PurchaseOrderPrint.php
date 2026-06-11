<?php

namespace App\Livewire\Purchasing;

use Livewire\Component;
use App\Models\PurchaseOrder;

class PurchaseOrderPrint extends Component
{
    public PurchaseOrder $purchaseOrder;

    public function mount(PurchaseOrder $purchaseOrder)
    {
        $this->purchaseOrder = $purchaseOrder->load([
            'supplier',
            'warehouse',
            'requestedBy',
            'items.product',
        ]);
    }

    public function print()
    {
        $this->dispatch('print-page');
    }

    public function render()
    {
        return view('livewire.purchasing.purchase-order-print')
            ->layout('layouts.app', [
                'title' => 'Print Purchase Order'
            ]);
    }
}