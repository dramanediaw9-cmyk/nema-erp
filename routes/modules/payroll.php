<?php

use App\Modules\Payroll\Http\Controllers\PayrollController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'workspace'])->group(function (): void {
    Route::get('/paie', [PayrollController::class, 'index'])->middleware('permission:payroll.view')->name('payroll.index');
    Route::post('/paie', [PayrollController::class, 'store'])->middleware('permission:payroll.manage')->name('payroll.store');
    Route::post('/paie/bulletins', [PayrollController::class, 'storeSlip'])->middleware('permission:payroll.manage')->name('payroll.slips.store');
});
