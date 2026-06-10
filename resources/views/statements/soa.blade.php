<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>SOA - {{ $customer->name }}</title>
    <style>
        body {
            color: #222;
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 24px;
        }

        .actions {
            margin-bottom: 18px;
            text-align: right;
        }

        .button {
            background: #4338ca;
            border-radius: 4px;
            color: #fff;
            display: inline-block;
            font-weight: bold;
            margin-left: 8px;
            padding: 9px 14px;
            text-decoration: none;
        }

        .button.secondary {
            background: #111827;
        }

        .header {
            border-bottom: 2px solid #222;
            margin-bottom: 24px;
            padding-bottom: 16px;
            text-align: center;
        }

        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .document-title {
            font-size: 18px;
            font-weight: bold;
            margin-top: 14px;
            text-transform: uppercase;
        }

        .info-grid {
            display: table;
            margin-bottom: 22px;
            width: 100%;
        }

        .info-column {
            display: table-cell;
            vertical-align: top;
            width: 50%;
        }

        h3 {
            border-bottom: 1px solid #ddd;
            font-size: 14px;
            margin: 0 0 8px;
            padding-bottom: 5px;
        }

        p {
            margin: 4px 0;
        }

        .summary {
            display: table;
            margin-bottom: 20px;
            width: 100%;
        }

        .summary-box {
            border: 1px solid #ddd;
            display: table-cell;
            padding: 10px;
            text-align: center;
            width: 25%;
        }

        .summary-label {
            color: #666;
            font-size: 11px;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .summary-value {
            font-size: 16px;
            font-weight: bold;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background: #f3f4f6;
            font-weight: bold;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .overdue {
            color: #b91c1c;
            font-weight: bold;
        }

        .totals-row td {
            border-top: 2px solid #222;
            font-weight: bold;
        }

        .notice {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            margin-top: 20px;
            padding: 12px;
        }

        .footer {
            border-top: 1px solid #ddd;
            color: #666;
            font-size: 11px;
            margin-top: 32px;
            padding-top: 12px;
            text-align: center;
        }

        @media print {
            body {
                padding: 0;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="actions no-print">
        <a class="button secondary" href="{{ route('sales.payments') }}">Back to Payments</a>
        <a class="button" href="{{ route('soa.download', $customer) }}">Download PDF</a>
        <a class="button" href="#" onclick="window.print(); return false;">Print</a>
    </div>

    <div class="header">
        <div class="company-name">{{ config('app.name') }}</div>
        <div>Medical Equipment Inventory Management System</div>
        <div class="document-title">Statement of Account</div>
    </div>

    <div class="info-grid">
        <div class="info-column">
            <h3>Customer</h3>
            <p><strong>{{ $customer->name }}</strong></p>
            <p>Email: {{ $customer->email ?? 'N/A' }}</p>
            <p>Phone: {{ $customer->phone ?? 'N/A' }}</p>
            <p>Address: {{ $customer->address ?? 'N/A' }}</p>
        </div>
        <div class="info-column">
            <h3>Statement Details</h3>
            <p><strong>Statement Date:</strong> {{ $statementDate->format('M d, Y') }}</p>
            <p><strong>Open Invoices:</strong> {{ number_format($sales->count()) }}</p>
            <p><strong>Overdue Invoices:</strong> {{ number_format($overdueCount) }}</p>
            <p><strong>Total Balance:</strong> ₱{{ number_format($totalBalance, 2) }}</p>
        </div>
    </div>

    <div class="summary">
        <div class="summary-box">
            <div class="summary-label">Total Billed</div>
            <div class="summary-value">₱{{ number_format($totalAmount, 2) }}</div>
        </div>
        <div class="summary-box">
            <div class="summary-label">Total Paid</div>
            <div class="summary-value">₱{{ number_format($totalPaid, 2) }}</div>
        </div>
        <div class="summary-box">
            <div class="summary-label">Balance Due</div>
            <div class="summary-value">₱{{ number_format($totalBalance, 2) }}</div>
        </div>
        <div class="summary-box">
            <div class="summary-label">Overdue</div>
            <div class="summary-value">{{ number_format($overdueCount) }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Invoice #</th>
                <th>Invoice Date</th>
                <th>Due Date</th>
                <th>Status</th>
                <th class="center">Delay</th>
                <th class="right">Total</th>
                <th class="right">Paid</th>
                <th class="right">Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sales as $sale)
                <tr>
                    <td>{{ $sale->invoice_number }}</td>
                    <td>{{ $sale->completed_at?->format('M d, Y') ?? $sale->created_at->format('M d, Y') }}</td>
                    <td>{{ $sale->due_date?->format('M d, Y') ?? 'N/A' }}</td>
                    <td>{{ $sale->payment_status_label }}</td>
                    <td class="center {{ $sale->days_delayed > 0 ? 'overdue' : '' }}">
                        {{ $sale->days_delayed > 0 ? $sale->days_delayed . ' days' : '-' }}
                    </td>
                    <td class="right">₱{{ number_format($sale->total_amount, 2) }}</td>
                    <td class="right">₱{{ number_format($sale->paid_amount, 2) }}</td>
                    <td class="right">₱{{ number_format($sale->outstanding_balance, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="center">No open balances found for this customer.</td>
                </tr>
            @endforelse

            <tr class="totals-row">
                <td colspan="5" class="right">Totals</td>
                <td class="right">₱{{ number_format($totalAmount, 2) }}</td>
                <td class="right">₱{{ number_format($totalPaid, 2) }}</td>
                <td class="right">₱{{ number_format($totalBalance, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="notice">
        This statement summarizes outstanding account balances only. Please verify against official receipts and
        previously issued documents before collection.
    </div>

    <div class="footer">
        Generated on {{ now()->format('M d, Y H:i') }} by {{ config('app.name') }}.
    </div>
</body>

</html>
