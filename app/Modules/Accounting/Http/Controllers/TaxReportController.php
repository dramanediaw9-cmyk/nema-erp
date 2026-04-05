<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\SalesInvoice;
use App\Support\CurrentWorkspace;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaxReportController extends Controller
{
    public function index(Request $request, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $from = $request->date('from', now()->startOfMonth())?->toDateString() ?? now()->startOfMonth()->toDateString();
        $to = $request->date('to', now()->endOfMonth())?->toDateString() ?? now()->endOfMonth()->toDateString();

        $sales = SalesInvoice::query()
            ->with('customer')
            ->where('company_id', $companyId)
            ->where('status', 'validated')
            ->whereBetween('invoice_date', [$from, $to])
            ->where('tax_total', '>', 0)
            ->latest('invoice_date')
            ->get();

        $purchases = PurchaseBill::query()
            ->with('supplier')
            ->where('company_id', $companyId)
            ->where('status', 'validated')
            ->whereBetween('bill_date', [$from, $to])
            ->where('tax_total', '>', 0)
            ->latest('bill_date')
            ->get();

        $summary = [
            'collected_vat' => (float) $sales->sum('tax_total'),
            'deductible_vat' => (float) $purchases->sum('tax_total'),
        ];
        $summary['net_vat'] = round($summary['collected_vat'] - $summary['deductible_vat'], 2);

        return view('accounting.tax-report.index', [
            'from' => $from,
            'to' => $to,
            'summary' => $summary,
            'sales' => $sales,
            'purchases' => $purchases,
        ]);
    }
}
