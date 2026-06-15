@php
    $printSettings = \App\Support\PrintDocumentSettings::for('purchase_order');
@endphp

<div class="max-w-6xl p-6 mx-auto">

    <style>
        @media print {
            @page {
                size: A4 portrait;
                margin: 12mm;
            }

            html,
            body {
                width: 210mm;
                min-height: 297mm;
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
            }

            body * {
                visibility: hidden !important;
            }

            body {
                transform: scale(.95);
                transform-origin: top left;
            }

            #po-print-area,
            #po-print-area * {
                visibility: visible !important;
            }

            #po-print-area {
                position: fixed !important;
                inset: 0 auto auto 0 !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .po-header {
                margin-bottom: 10px !important;
                padding-bottom: 10px !important;
            }

            .po-header h1 {
                font-size: 18pt !important;
                margin-bottom: 2px !important;
            }

            .print-container {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                max-width: none !important;
                border: none !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }

            .print-body {
                padding: 0 !important;
            }

            .no-print {
                display: none !important;
            }

            table {
                page-break-inside: auto;
            }

            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            thead {
                display: table-header-group;
            }

            tfoot {
                display: table-footer-group;
            }

            .supplier-table td {
                padding: 1px 4px !important;
            }

            .po-items-table {
                font-size: 9pt;
                width: 100%;
                border-collapse: collapse;
            }

            .po-items-table th,
            .po-items-table td {
                padding: 3px 6px !important;
                line-height: 1.15 !important;
                vertical-align: top;
            }

            .po-items-table th {
                font-weight: bold;
            }

            .hide-print {
                display: none !important;
            }
        }
    </style>

    <div class="flex justify-between mb-4 no-print">
        <x-mary-button
            icon="o-arrow-left"
            label="Back"
            onclick="history.back()"
            class="btn-ghost" />

        <x-mary-button
            icon="o-printer"
            label="Print PO"
            onclick="window.print()"
            class="btn-primary" />
    </div>

    <div id="po-print-area">
        <div class="bg-white border rounded-lg shadow print-container">

            <div class="p-8 print-body">

                @include('print.partials.document-header', [
                    'printSettings' => $printSettings,
                    'documentTitle' => 'PURCHASE ORDER',
                    'documentNumber' => $purchaseOrder->po_number,
                ])

                {{-- DETAILS --}}
                <div class="grid grid-cols-2 gap-8 mb-8">

                    <div>
                        <h3 class="mb-3 font-bold">
                            Supplier Information
                        </h3>

                        <table class="w-full text-sm print:text-[9pt]">
                            <tr>
                                <td class="w-40 font-semibold">Supplier</td>
                                <td>{{ $purchaseOrder->supplier?->name }}</td>
                            </tr>

                            <tr>
                                <td class="font-semibold">TIN</td>
                                <td>{{ $purchaseOrder->tin }}</td>
                            </tr>

                            <tr>
                                <td class="font-semibold">Business Style</td>
                                <td>{{ $purchaseOrder->business_style }}</td>
                            </tr>

                            <tr>
                                <td class="font-semibold">Address</td>
                                <td>{{ $purchaseOrder->address }}</td>
                            </tr>

                            <tr>
                                <td class="font-semibold">Contact Person</td>
                                <td>{{ $purchaseOrder->contact_person }}</td>
                            </tr>

                            <tr>
                                <td class="font-semibold">Contact Number</td>
                                <td>{{ $purchaseOrder->contact_number }}</td>
                            </tr>
                        </table>
                    </div>

                    <div>
                        <h3 class="mb-3 font-bold">
                            Order Information
                        </h3>

                        <table class="w-full text-sm">

                            <tr>
                                <td class="w-40 font-semibold">PO Number</td>
                                <td>{{ $purchaseOrder->po_number }}</td>
                            </tr>

                            <tr>
                                <td class="font-semibold">Order Date</td>
                                <td>{{ $purchaseOrder->order_date?->format('M d, Y') }}</td>
                            </tr>

                            <tr>
                                <td class="font-semibold">Expected Date</td>
                                <td>{{ $purchaseOrder->expected_date?->format('M d, Y') }}</td>
                            </tr>

                            <tr>
                                <td class="font-semibold">Terms</td>
                                <td>{{ $purchaseOrder->terms }}</td>
                            </tr>

                            <tr>
                                <td class="font-semibold">Due Date</td>
                                <td>{{ $purchaseOrder->due_date?->format('M d, Y') }}</td>
                            </tr>

                            <tr>
                                <td class="font-semibold">Warehouse</td>
                                <td>{{ $purchaseOrder->warehouse?->name }}</td>
                            </tr>

                            <tr>
                                <td class="font-semibold">Requested By</td>
                                <td>{{ $purchaseOrder->requestedBy?->name }}</td>
                            </tr>
                        </table>
                    </div>

                </div>

                {{-- ITEMS --}}
                <div class="mb-8">

                    <h3 class="mb-3 font-bold">
                        Ordered Items
                    </h3>

                    <table class="table w-full border po-items-table">

                        <thead>
                            <tr>
                                <th width="4%">#</th>
                                <th width="45%">Product</th>
                                <th width="10%">Qty</th>
                                <th width="15%" class="text-right">Unit Cost</th>
                                <th width="10%">Tax</th>
                                <th width="16%" class="text-right">Amount</th>
                            </tr>
                        </thead>

                        <tbody>

                            @foreach($purchaseOrder->items as $index => $item)

                                <tr>
                                    <td>{{ $index + 1 }}</td>

                                    <td>
                                        {{ $item->product?->name }}
                                    </td>

                                    <td>
                                        {{ number_format($item->quantity_ordered) }}
                                    </td>

                                    <td class="text-right">
                                        ₱{{ number_format($item->unit_cost,2) }}
                                    </td>

                                    <td>
                                        {{ strtoupper($item->tax_type ?? '-') }}
                                    </td>

                                    <td class="text-right">
                                        ₱{{ number_format($item->total_cost,2) }}
                                    </td>
                                </tr>

                            @endforeach

                        </tbody>

                    </table>

                </div>

                {{-- BILLING --}}
                <div class="flex justify-end mb-10">

                    <div class="w-96">

                        <table class="table table-sm">

                            <tr>
                                <td>Discount Type</td>
                                <td class="text-right">
                                    {{ strtoupper($purchaseOrder->discount_type ?? 'REGULAR') }}
                                </td>
                            </tr>

                            <tr>
                                <td>Discount Amount</td>
                                <td class="text-right">
                                    ₱{{ number_format($purchaseOrder->discount_amount,2) }}
                                </td>
                            </tr>

                            <tr>
                                <td>Tax</td>
                                <td class="text-right">
                                    ₱{{ number_format($purchaseOrder->tax_amount,2) }}
                                </td>
                            </tr>

                            <tr class="text-lg font-bold border-t">
                                <td>Total Amount</td>
                                <td class="text-right">
                                    ₱{{ number_format($purchaseOrder->total_amount,2) }}
                                </td>
                            </tr>

                        </table>

                    </div>

                </div>

                {{-- NOTES --}}
                @if($purchaseOrder->notes)
                    <div class="mb-10">
                        <h3 class="mb-2 font-bold">Notes</h3>

                        <div class="p-3 border rounded min-h-24">
                            {{ $purchaseOrder->notes }}
                        </div>
                    </div>
                @endif

                @include('print.partials.document-footer', ['printSettings' => $printSettings])

            </div>

        </div>
    </div>

</div>
