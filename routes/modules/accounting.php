<?php

use App\Modules\Accounting\Http\Controllers\AccountController;
use App\Modules\Accounting\Http\Controllers\AccountingPeriodController;
use App\Modules\Accounting\Http\Controllers\BalanceController;
use App\Modules\Accounting\Http\Controllers\FinancialReportController;
use App\Modules\Accounting\Http\Controllers\JournalEntryController;
use App\Modules\Accounting\Http\Controllers\TaxReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'workspace'])->group(function (): void {
    Route::get('/comptabilite/plan-comptable', [AccountController::class, 'index'])
        ->middleware('permission:accounting.view')
        ->name('accounting.accounts.index');

    Route::get('/comptabilite/periodes', [AccountingPeriodController::class, 'index'])
        ->middleware('permission:accounting.manage_periods')
        ->name('accounting.periods.index');
    Route::post('/comptabilite/periodes', [AccountingPeriodController::class, 'store'])
        ->middleware('permission:accounting.manage_periods')
        ->name('accounting.periods.store');
    Route::post('/comptabilite/periodes/{period}/cloturer', [AccountingPeriodController::class, 'close'])
        ->middleware('permission:accounting.manage_periods')
        ->name('accounting.periods.close');
    Route::post('/comptabilite/periodes/{period}/reouvrir', [AccountingPeriodController::class, 'reopen'])
        ->middleware('permission:accounting.manage_periods')
        ->name('accounting.periods.reopen');

    Route::get('/comptabilite/journaux', [JournalEntryController::class, 'index'])
        ->middleware('permission:accounting.view')
        ->name('accounting.journal-entries.index');

    Route::get('/comptabilite/journaux/{journalEntry}', [JournalEntryController::class, 'show'])
        ->middleware('permission:accounting.view')
        ->name('accounting.journal-entries.show');

    Route::get('/comptabilite/balance', [BalanceController::class, 'index'])
        ->middleware('permission:accounting.view')
        ->name('accounting.balance.index');
    Route::get('/comptabilite/balance/export', [BalanceController::class, 'export'])
        ->middleware('permission:accounting.view')
        ->name('accounting.balance.export');
    Route::get('/comptabilite/balance/imprimer', [BalanceController::class, 'print'])
        ->middleware('permission:accounting.view')
        ->name('accounting.balance.print');

    Route::get('/comptabilite/grand-livre', [FinancialReportController::class, 'generalLedger'])
        ->middleware('permission:accounting.view')
        ->name('accounting.general-ledger.index');
    Route::get('/comptabilite/grand-livre/export', [FinancialReportController::class, 'exportGeneralLedger'])
        ->middleware('permission:accounting.view')
        ->name('accounting.general-ledger.export');
    Route::get('/comptabilite/grand-livre/imprimer', [FinancialReportController::class, 'printGeneralLedger'])
        ->middleware('permission:accounting.view')
        ->name('accounting.general-ledger.print');

    Route::get('/comptabilite/compte-resultat', [FinancialReportController::class, 'profitLoss'])
        ->middleware('permission:accounting.view')
        ->name('accounting.profit-loss.index');
    Route::get('/comptabilite/compte-resultat/export', [FinancialReportController::class, 'exportProfitLoss'])
        ->middleware('permission:accounting.view')
        ->name('accounting.profit-loss.export');
    Route::get('/comptabilite/compte-resultat/imprimer', [FinancialReportController::class, 'printProfitLoss'])
        ->middleware('permission:accounting.view')
        ->name('accounting.profit-loss.print');

    Route::get('/comptabilite/bilan', [FinancialReportController::class, 'balanceSheet'])
        ->middleware('permission:accounting.view')
        ->name('accounting.balance-sheet.index');
    Route::get('/comptabilite/bilan/export', [FinancialReportController::class, 'exportBalanceSheet'])
        ->middleware('permission:accounting.view')
        ->name('accounting.balance-sheet.export');
    Route::get('/comptabilite/bilan/imprimer', [FinancialReportController::class, 'printBalanceSheet'])
        ->middleware('permission:accounting.view')
        ->name('accounting.balance-sheet.print');

    Route::get('/comptabilite/fiscalite', [TaxReportController::class, 'index'])
        ->middleware('permission:accounting.view')
        ->name('accounting.tax-report.index');
});
