<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;

class StatementOfAccountController extends Controller
{
    public function preview(Customer $customer)
    {
        $data = $this->statementData($customer);

        return view('statements.soa', $data);
    }

    public function download(Customer $customer)
    {
        $data = $this->statementData($customer);

        $pdf = Pdf::loadView('statements.soa', $data);
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isPhpEnabled' => true,
            'defaultFont' => 'Arial',
        ]);

        $filename = 'soa-' . str($customer->name)->slug() . '-' . now()->format('Ymd') . '.pdf';

        return $pdf->download($filename);
    }

    private function statementData(Customer $customer): array
    {
        if (! auth()->user()?->canProcessSales()) {
            abort(403, 'Unauthorized to print statements of account.');
        }

        $sales = Sale::with(['customer', 'warehouse', 'user'])
            ->where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->whereRaw('total_amount > paid_amount')
            ->where(function ($query) {
                $query->whereNull('payment_status')
                    ->orWhere('payment_status', '!=', 'paid');
            })
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->orderBy('completed_at')
            ->get();

        return [
            'customer' => $customer,
            'sales' => $sales,
            'statementDate' => now(),
            'totalAmount' => $sales->sum('total_amount'),
            'totalPaid' => $sales->sum('paid_amount'),
            'totalBalance' => $sales->sum(fn (Sale $sale) => $sale->outstanding_balance),
            'overdueCount' => $sales->filter(fn (Sale $sale) => $sale->days_delayed > 0)->count(),
        ];
    }
}
