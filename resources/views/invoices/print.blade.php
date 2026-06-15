<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Invoice {{ $sale->invoice_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }

        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .company-details {
            font-size: 12px;
            color: #666;
        }

        .invoice-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .invoice-details,
        .customer-details {
            width: 48%;
        }

        .invoice-details h3,
        .customer-details h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .items-table th,
        .items-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .items-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }

        .items-table .qty,
        .items-table .price,
        .items-table .total {
            text-align: right;
        }

        .totals {
            width: 300px;
            margin-left: auto;
            margin-bottom: 30px;
        }

        .totals table {
            width: 100%;
            border-collapse: collapse;
        }

        .totals td {
            padding: 5px;
            border-bottom: 1px solid #ddd;
        }

        .totals .total-row {
            font-weight: bold;
            font-size: 16px;
            border-top: 2px solid #333;
        }

        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            text-align: center;
            color: #666;
        }

        .notice {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            margin-top: 20px;
            border-radius: 4px;
        }

        .notice strong {
            color: #856404;
        }

        @media print {
            body {
                margin: 0;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>
    @php
        $printSettings = \App\Support\PrintDocumentSettings::for('invoice');
    @endphp

    @include('print.partials.document-header', [
        'printSettings' => $printSettings,
        'documentTitle' => 'SALES INVOICE',
        'documentNumber' => $sale->invoice_number,
    ])

    <div class="invoice-info">
        <div class="invoice-details">
            <h3>Invoice Details</h3>
            <p><strong>Invoice Number:</strong> {{ $sale->invoice_number }}</p>
            <p><strong>Date:</strong> {{ $sale->created_at->format('M d, Y H:i') }}</p>
            <p><strong>Warehouse:</strong> {{ $sale->warehouse->name }}</p>
            <p><strong>Cashier:</strong> {{ $sale->user->name }}</p>
            <p><strong>Payment Method:</strong> {{ $sale->payment_method_label }}</p>
            <p><strong>Payment Status:</strong> {{ $sale->payment_status_label }}</p>
            @if ($sale->payment_method === 'terms')
                <p><strong>Payment Terms:</strong> {{ $sale->payment_terms ?? 'N/A' }}</p>
                <p><strong>Due Date:</strong> {{ $sale->due_date?->format('M d, Y') ?? 'N/A' }}</p>
                @if (!$sale->is_paid && $sale->days_delayed > 0)
                    <p><strong>Delay:</strong> {{ $sale->days_delayed }} days</p>
                @endif
            @endif
        </div>

        <div class="customer-details">
            <h3>Customer Details</h3>
            <p><strong>Name:</strong> {{ $sale->customer->name ?? 'No customer' }}</p>
            @if ($sale->customer)
                <p><strong>Email:</strong> {{ $sale->customer->email ?? 'N/A' }}</p>
                <p><strong>Phone:</strong> {{ $sale->customer->phone ?? 'N/A' }}</p>
                @if ($sale->customer->address)
                    <p><strong>Address:</strong> {{ $sale->customer->address }}</p>
                @endif
            @endif
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th>Item</th>
                <th>SKU</th>
                <th class="qty">Qty</th>
                <th class="price">Unit Price</th>
                <th class="total">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->items as $item)
                <tr>
                    <td>
                        <strong>{{ $item->product_name }}</strong>
                    </td>
                    <td>{{ $item->product_sku }}</td>
                    <td class="qty">{{ $item->quantity }}</td>
                    <td class="price">&#8369;{{ number_format($item->unit_price, 2) }}</td>
                    <td class="total">&#8369;{{ number_format($item->total_price, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr>
                <td>{{ in_array($sale->tax_type, ['vat_12', 'ewt_sales_1', 'ewt_service_2'], true) ? 'Subtotal (Net of VAT):' : 'Subtotal:' }}</td>
                <td style="text-align: right;">&#8369;{{ number_format($sale->subtotal_amount, 2) }}</td>
            </tr>
            @if ($sale->tax_amount > 0)
                <tr>
                    <td>{{ $sale->tax_label }}:</td>
                    <td style="text-align: right;">&#8369;{{ number_format($sale->tax_amount, 2) }}</td>
                </tr>
            @endif
            <tr class="total-row">
                <td>Total:</td>
                <td style="text-align: right;">&#8369;{{ number_format($sale->total_amount, 2) }}</td>
            </tr>
            <tr>
                <td>Amount Paid:</td>
                <td style="text-align: right;">&#8369;{{ number_format($sale->paid_amount, 2) }}</td>
            </tr>
            @if ($sale->change_amount > 0)
                <tr>
                    <td>Change:</td>
                    <td style="text-align: right;">&#8369;{{ number_format($sale->change_amount, 2) }}</td>
                </tr>
            @endif
            @if ($sale->outstanding_balance > 0)
                <tr>
                    <td>Balance Due:</td>
                    <td style="text-align: right;">&#8369;{{ number_format($sale->outstanding_balance, 2) }}</td>
                </tr>
            @endif
        </table>
    </div>

    <div class="notice">
        <strong>Important Notice:</strong><br>
        This is a SALES INVOICE DRAFT - Not valid as official receipt.<br>
        Please issue BIR-printed receipt manually for legal compliance.
    </div>

    @include('print.partials.document-footer', ['printSettings' => $printSettings])
</body>

</html>
