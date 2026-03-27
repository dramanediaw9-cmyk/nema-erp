<?php

use App\Modules\Treasury\Http\Controllers\CashAccountController;
use App\Modules\Treasury\Http\Controllers\PaymentController;
use App\Modules\Treasury\Http\Controllers\TreasuryReconciliationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'workspace'])->group(function (): void {
    Route::get('/comptes-tresorerie', [CashAccountController::class, 'index'])->middleware('permission:cash_accounts.view')->name('cash-accounts.index');
    Route::get('/comptes-tresorerie/creer', [CashAccountController::class, 'create'])->middleware('permission:cash_accounts.manage')->name('cash-accounts.create');
    Route::post('/comptes-tresorerie', [CashAccountController::class, 'store'])->middleware('permission:cash_accounts.manage')->name('cash-accounts.store');
    Route::get('/comptes-tresorerie/{cashAccount}/modifier', [CashAccountController::class, 'edit'])->middleware('permission:cash_accounts.manage')->name('cash-accounts.edit');
    Route::put('/comptes-tresorerie/{cashAccount}', [CashAccountController::class, 'update'])->middleware('permission:cash_accounts.manage')->name('cash-accounts.update');

    Route::get('/paiements', [PaymentController::class, 'index'])->middleware('permission:payments.view')->name('payments.index');
    Route::get('/paiements/export', [PaymentController::class, 'export'])->middleware('permission:payments.view')->name('payments.export');
    Route::get('/paiements/creer', [PaymentController::class, 'create'])->middleware('permission:payments.manage')->name('payments.create');
    Route::get('/paiements/{payment}', [PaymentController::class, 'show'])->middleware('permission:payments.view')->name('payments.show');
    Route::post('/paiements', [PaymentController::class, 'store'])->middleware('permission:payments.manage')->name('payments.store');
    Route::get('/rapprochements-tresorerie', [TreasuryReconciliationController::class, 'index'])->middleware('permission:reconciliations.view')->name('treasury-reconciliations.index');
    Route::get('/rapprochements-tresorerie/creer', [TreasuryReconciliationController::class, 'create'])->middleware('permission:reconciliations.manage')->name('treasury-reconciliations.create');
    Route::post('/rapprochements-tresorerie', [TreasuryReconciliationController::class, 'store'])->middleware('permission:reconciliations.manage')->name('treasury-reconciliations.store');
    Route::get('/rapprochements-tresorerie/{treasuryReconciliation}', [TreasuryReconciliationController::class, 'show'])->middleware('permission:reconciliations.view')->name('treasury-reconciliations.show');
});

