<?php

namespace App\Services;

use App\Models\Sale;
use DOMDocument;

class EInvoiceService
{
    public function payload(Sale $sale): array
    {
        $sale->loadMissing(['customer', 'warehouse', 'user', 'items.product']);

        return [
            'document_type' => 'e_invoice',
            'schema_version' => '1.0',
            'invoice' => [
                'number' => $sale->invoice_number,
                'status' => $sale->status,
                'type' => $sale->invoice_type ?? 'sales',
                'issued_at' => optional($sale->invoice_date ?? $sale->completed_at ?? $sale->created_at)->toIso8601String(),
                'currency' => 'PHP',
                'payment_method' => $sale->payment_method,
                'payment_terms' => $sale->payment_terms,
                'due_date' => optional($sale->due_date)->toDateString(),
                'payment_status' => $sale->payment_status,
                'payment_status_label' => $sale->payment_status_label,
                'days_delayed' => $sale->days_delayed,
            ],
            'seller' => [
                'name' => config('app.name'),
                'branch' => $sale->warehouse?->name,
                'address' => $sale->warehouse?->address,
                'city' => $sale->warehouse?->city,
            ],
            'buyer' => [
                'name' => $sale->customer?->name ?? 'Walk-in Customer',
                'type' => $sale->customer?->type ?? 'individual',
                'tax_id' => $sale->customer?->tax_id,
                'email' => $sale->customer?->email,
                'phone' => $sale->customer?->phone,
                'address' => $sale->customer?->address,
                'city' => $sale->customer?->city,
            ],
            'lines' => $sale->items->values()->map(function ($item, int $index) {
                return [
                    'line_number' => $index + 1,
                    'item_type' => $item->item_type ?? 'product',
                    'name' => $item->item_name,
                    'sku' => $item->item_code,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => $this->money($item->unit_price),
                    'discount_amount' => $this->money($item->discount_amount),
                    'line_total' => $this->money($item->total_price),
                ];
            })->all(),
            'totals' => [
                'subtotal' => $this->money($sale->subtotal_amount),
                'gross_amount' => $this->money($sale->taxable_gross_amount),
                'discount_amount' => $this->money($sale->discount_amount),
                'tax_type' => $sale->tax_type ?? 'vat_12',
                'tax_rate' => $this->money($sale->tax_rate ?? 12),
                'tax_amount' => $this->money($sale->tax_amount),
                'total_amount' => $this->money($sale->total_amount),
                'paid_amount' => $this->money($sale->paid_amount),
                'change_amount' => $this->money($sale->change_amount),
                'balance_due' => $this->money($sale->outstanding_balance),
            ],
            'metadata' => [
                'generated_at' => now()->toIso8601String(),
                'generated_by' => $sale->user?->name,
                'source_system' => config('app.name') . ' Inventory Management System',
                'compliance_note' => 'Structured export only. Submit to BIR EIS or another required platform according to your registered taxpayer setup.',
            ],
        ];
    }

    public function xml(Sale $sale): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;

        $root = $document->createElement('EInvoice');
        $document->appendChild($root);

        $this->appendArray($document, $root, $this->payload($sale));

        return $document->saveXML();
    }

    private function appendArray(DOMDocument $document, \DOMElement $parent, array $data): void
    {
        foreach ($data as $key => $value) {
            $elementName = is_int($key) ? 'Item' : $this->xmlName($key);
            $element = $document->createElement($elementName);
            $parent->appendChild($element);

            if (is_array($value)) {
                $this->appendArray($document, $element, $value);
                continue;
            }

            $element->appendChild($document->createTextNode((string) ($value ?? '')));
        }
    }

    private function xmlName(string $name): string
    {
        return str($name)->studly()->toString();
    }

    private function money($amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
