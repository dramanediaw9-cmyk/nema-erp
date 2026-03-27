<?php

use App\Modules\Budgets\Http\Controllers\BudgetController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'workspace'])->group(function (): void {
    Route::get('/budgets', [BudgetController::class, 'index'])->middleware('permission:budgets.view')->name('budgets.index');
    Route::get('/budgets/creer', [BudgetController::class, 'create'])->middleware('permission:budgets.manage')->name('budgets.create');
    Route::post('/budgets', [BudgetController::class, 'store'])->middleware('permission:budgets.manage')->name('budgets.store');
    Route::get('/budgets/{budget}', [BudgetController::class, 'show'])->middleware('permission:budgets.view')->name('budgets.show');
});
