<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\AccountingReportService;
use App\Support\CurrentWorkspace;
use App\Support\Exports\CsvExportService;
use App\Support\Pdf\PdfDocumentService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReportController extends Controller
{
    public function __construct(
        private readonly AccountingReportService $accountingReportService,
        private readonly CsvExportService $csvExportService,
        private readonly PdfDocumentService $pdfDocumentService,
    ) {
    }

    public function generalLedger(Request $request, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $dateFrom = $request->string('date_from')->value() ?: null;
        $dateTo = $request->string('date_to')->value() ?: null;
        $accountId = $request->integer('account_id') ?: null;

        $ledger = $this->accountingReportService->generalLedger($companyId, $dateFrom, $dateTo, $accountId);

        return view('accounting.general-ledger.index', [
            'lines' => $ledger['lines'],
            'summary' => $ledger['summary'],
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'account_id' => $accountId,
            ],
            'accounts' => Account::query()->where('company_id', $companyId)->orderBy('code')->get(),
        ]);
    }

    public function exportGeneralLedger(Request $request, CurrentWorkspace $workspace): StreamedResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $dateFrom = $request->string('date_from')->value() ?: null;
        $dateTo = $request->string('date_to')->value() ?: null;
        $accountId = $request->integer('account_id') ?: null;
        $ledger = $this->accountingReportService->generalLedgerCollection($companyId, $dateFrom, $dateTo, $accountId);

        $rows = $ledger['lines']->map(fn ($line) => [
            $line->journalEntry?->entry_date?->format('d/m/Y'),
            $line->journalEntry?->journal_code,
            $line->journalEntry?->journal_number,
            $line->account?->code,
            $line->account?->name,
            $line->label ?: $line->journalEntry?->description,
            $line->partner?->name,
            number_format((float) $line->debit, 2, '.', ''),
            number_format((float) $line->credit, 2, '.', ''),
        ]);

        return $this->csvExportService->download('grand-livre.csv', [
            'Date', 'Journal', 'Numero', 'Compte', 'Libelle compte', 'Libelle ligne', 'Tiers', 'Debit', 'Credit',
        ], $rows);
    }

    public function printGeneralLedger(Request $request, CurrentWorkspace $workspace): \Symfony\Component\HttpFoundation\Response
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $dateFrom = $request->string('date_from')->value() ?: null;
        $dateTo = $request->string('date_to')->value() ?: null;
        $accountId = $request->integer('account_id') ?: null;
        $ledger = $this->accountingReportService->generalLedgerCollection($companyId, $dateFrom, $dateTo, $accountId);

        return $this->pdfDocumentService->inline('accounting.general-ledger.print', [
            'lines' => $ledger['lines'],
            'summary' => $ledger['summary'],
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'account_id' => $accountId,
            ],
            'company' => $workspace->company(),
        ], 'grand-livre.pdf', 'a4', 'landscape');
    }

    public function profitLoss(Request $request, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $dateFrom = $request->string('date_from')->value() ?: now()->startOfMonth()->format('Y-m-d');
        $dateTo = $request->string('date_to')->value() ?: now()->format('Y-m-d');

        return view('accounting.profit-loss.index', [
            'report' => $this->accountingReportService->incomeStatement($companyId, $dateFrom, $dateTo),
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function exportProfitLoss(Request $request, CurrentWorkspace $workspace): StreamedResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $dateFrom = $request->string('date_from')->value() ?: now()->startOfMonth()->format('Y-m-d');
        $dateTo = $request->string('date_to')->value() ?: now()->format('Y-m-d');
        $report = $this->accountingReportService->incomeStatement($companyId, $dateFrom, $dateTo);

        $rows = collect($report['income'])->map(fn (array $line) => ['Produit', $line['code'], $line['name'], number_format((float) $line['balance'], 2, '.', '')])
            ->concat(collect($report['expenses'])->map(fn (array $line) => ['Charge', $line['code'], $line['name'], number_format((float) $line['balance'], 2, '.', '')]))
            ->push(['Resultat', '', 'Resultat net', number_format((float) $report['net_result'], 2, '.', '')]);

        return $this->csvExportService->download('compte-resultat.csv', [
            'Section', 'Code', 'Libelle', 'Montant',
        ], $rows);
    }

    public function printProfitLoss(Request $request, CurrentWorkspace $workspace): \Symfony\Component\HttpFoundation\Response
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $dateFrom = $request->string('date_from')->value() ?: now()->startOfMonth()->format('Y-m-d');
        $dateTo = $request->string('date_to')->value() ?: now()->format('Y-m-d');

        return $this->pdfDocumentService->inline('accounting.profit-loss.print', [
            'report' => $this->accountingReportService->incomeStatement($companyId, $dateFrom, $dateTo),
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'company' => $workspace->company(),
        ], 'compte-resultat.pdf');
    }

    public function balanceSheet(Request $request, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $dateTo = $request->string('date_to')->value() ?: now()->format('Y-m-d');

        return view('accounting.balance-sheet.index', [
            'report' => $this->accountingReportService->balanceSheet($companyId, $dateTo),
            'filters' => [
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function exportBalanceSheet(Request $request, CurrentWorkspace $workspace): StreamedResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $dateTo = $request->string('date_to')->value() ?: now()->format('Y-m-d');
        $report = $this->accountingReportService->balanceSheet($companyId, $dateTo);

        $rows = collect($report['assets'])->map(fn (array $line) => ['Actif', $line['code'], $line['name'], number_format((float) $line['balance'], 2, '.', '')])
            ->concat(collect($report['liabilities'])->map(fn (array $line) => ['Passif', $line['code'], $line['name'], number_format((float) $line['balance'], 2, '.', '')]))
            ->concat(collect($report['equity'])->map(fn (array $line) => ['Capitaux propres', $line['code'], $line['name'], number_format((float) $line['balance'], 2, '.', '')]))
            ->push(['Capitaux propres', $report['current_result']['code'], $report['current_result']['name'], number_format((float) $report['current_result']['balance'], 2, '.', '')]);

        return $this->csvExportService->download('bilan.csv', [
            'Section', 'Code', 'Libelle', 'Montant',
        ], $rows);
    }

    public function printBalanceSheet(Request $request, CurrentWorkspace $workspace): \Symfony\Component\HttpFoundation\Response
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $dateTo = $request->string('date_to')->value() ?: now()->format('Y-m-d');

        return $this->pdfDocumentService->inline('accounting.balance-sheet.print', [
            'report' => $this->accountingReportService->balanceSheet($companyId, $dateTo),
            'filters' => [
                'date_to' => $dateTo,
            ],
            'company' => $workspace->company(),
        ], 'bilan.pdf');
    }
}
