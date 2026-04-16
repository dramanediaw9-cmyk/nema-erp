<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Services\AccountingReportService;
use App\Support\CurrentWorkspace;
use App\Support\Exports\CsvExportService;
use App\Support\Pdf\PdfDocumentService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BalanceController extends Controller
{
    public function __construct(
        private readonly AccountingReportService $accountingReportService,
        private readonly CsvExportService $csvExportService,
        private readonly PdfDocumentService $pdfDocumentService,
    ) {
    }

    public function index(Request $request, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $dateFrom = $request->string('date_from')->value() ?: null;
        $dateTo = $request->string('date_to')->value() ?: null;

        [$balances, $summary] = $this->accountingReportService->trialBalance($companyId, $dateFrom, $dateTo);

        return view('accounting.balance.index', [
            'balances' => $balances,
            'summary' => $summary,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function export(Request $request, CurrentWorkspace $workspace): StreamedResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $dateFrom = $request->string('date_from')->value() ?: null;
        $dateTo = $request->string('date_to')->value() ?: null;
        [$balances] = $this->accountingReportService->trialBalance($companyId, $dateFrom, $dateTo);

        $rows = $balances->map(fn (array $balance) => [
            $balance['code'],
            $balance['name'],
            $balance['type'],
            number_format((float) $balance['total_debit'], 2, '.', ''),
            number_format((float) $balance['total_credit'], 2, '.', ''),
            number_format((float) $balance['balance'], 2, '.', ''),
        ]);

        return $this->csvExportService->download('balance-comptable.csv', [
            'Code', 'Libelle', 'Type', 'Debit', 'Credit', 'Solde',
        ], $rows);
    }

    public function print(Request $request, CurrentWorkspace $workspace): \Symfony\Component\HttpFoundation\Response
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $dateFrom = $request->string('date_from')->value() ?: null;
        $dateTo = $request->string('date_to')->value() ?: null;
        [$balances, $summary] = $this->accountingReportService->trialBalance($companyId, $dateFrom, $dateTo);

        return $this->pdfDocumentService->inline('accounting.balance.print', [
            'balances' => $balances,
            'summary' => $summary,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'company' => $workspace->company(),
        ], 'balance-comptable.pdf', 'a4', 'landscape');
    }
}
