<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Services\EInvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceController extends Controller
{
    public function __construct(private readonly EInvoiceService $eInvoiceService)
    {
    }

    public function download(Sale $sale)
    {
        try {
            // Check if user has permission to view this sale
            if (!auth()->user()->canProcessSales()) {
                abort(403, 'Unauthorized to download invoices.');
            }

            // Load the sale with all related data
            $sale = $sale->load(['customer', 'warehouse', 'user', 'items.product']);

            // Check if sale exists and is completed
            if (!$sale || $sale->status !== 'completed') {
                abort(404, 'Sale not found or not completed.');
            }

            // Generate PDF
            $pdf = Pdf::loadView('invoices.print', compact('sale'));

            // Set paper size and orientation
            $pdf->setPaper('A4', 'portrait');

            // Set additional options
            $pdf->setOptions([
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
                'defaultFont' => 'Arial'
            ]);

            // Generate filename
            $filename = 'invoice-' . $sale->invoice_number . '.pdf';

            // Return PDF download
            return $pdf->download($filename);
        } catch (\Exception $e) {
            // Log the error
            \Log::error('Invoice download error: ' . $e->getMessage(), [
                'sale_id' => $sale->id ?? 'unknown',
                'user_id' => auth()->id(),
                'error' => $e->getTraceAsString()
            ]);

            // Return error response
            return response()->json([
                'error' => 'Failed to generate invoice: ' . $e->getMessage()
            ], 500);
        }
    }

    public function downloadEInvoiceJson(Sale $sale)
    {
        $this->authorizeCompletedSaleExport($sale);

        $payload = $this->eInvoiceService->payload($sale);
        $filename = 'e-invoice-' . $sale->invoice_number . '.json';

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function downloadEInvoiceXml(Sale $sale)
    {
        $this->authorizeCompletedSaleExport($sale);

        $filename = 'e-invoice-' . $sale->invoice_number . '.xml';

        return response()->streamDownload(function () use ($sale) {
            echo $this->eInvoiceService->xml($sale);
        }, $filename, [
            'Content-Type' => 'application/xml',
        ]);
    }

    public function preview(Sale $sale)
    {
        try {

            // Load the sale with all related data
            $sale = $sale->load(['customer', 'warehouse', 'user', 'items.product']);

            // Return HTML view for preview
            return view('invoices.print', compact('sale'));
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to preview invoice: ' . $e->getMessage()
            ], 500);
        }
    }

    private function authorizeCompletedSaleExport(Sale $sale): void
    {
        if (!auth()->user()?->canProcessSales()) {
            abort(403, 'Unauthorized to export invoices.');
        }

        if ($sale->status !== 'completed') {
            abort(404, 'Sale not found or not completed.');
        }
    }
}
