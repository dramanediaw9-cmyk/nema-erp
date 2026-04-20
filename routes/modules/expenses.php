<?php

use App\Modules\Expenses\Http\Controllers\ExpenseCategoryController;
use App\Modules\Expenses\Http\Controllers\ExpenseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'workspace'])->group(function (): void {
    Route::get('/categories-depenses', [ExpenseCategoryController::class, 'index'])->middleware('permission:expense_categories.view')->name('expense-categories.index');
    Route::get('/categories-depenses/creer', [ExpenseCategoryController::class, 'create'])->middleware('permission:expense_categories.manage')->name('expense-categories.create');
    Route::post('/categories-depenses', [ExpenseCategoryController::class, 'store'])->middleware('permission:expense_categories.manage')->name('expense-categories.store');
    Route::get('/categories-depenses/{expenseCategory}/modifier', [ExpenseCategoryController::class, 'edit'])->middleware('permission:expense_categories.manage')->name('expense-categories.edit');
    Route::put('/categories-depenses/{expenseCategory}', [ExpenseCategoryController::class, 'update'])->middleware('permission:expense_categories.manage')->name('expense-categories.update');

    Route::get('/depenses', [ExpenseController::class, 'index'])->middleware('permission:expenses.view')->name('expenses.index');
    Route::get('/depenses/export', [ExpenseController::class, 'export'])->middleware('permission:expenses.view')->name('expenses.export');
    Route::get('/depenses/creer', [ExpenseController::class, 'create'])->middleware('permission:expenses.manage')->name('expenses.create');
    Route::post('/depenses', [ExpenseController::class, 'store'])->middleware('permission:expenses.manage')->name('expenses.store');
    Route::post('/depenses/{expense}/approuver', [ExpenseController::class, 'approve'])->middleware('permission:expenses.approve')->name('expenses.approve');
    Route::post('/depenses/{expense}/rejeter', [ExpenseController::class, 'reject'])->middleware('permission:expenses.approve')->name('expenses.reject');
    Route::get('/depenses/{expense}', [ExpenseController::class, 'show'])->middleware('permission:expenses.view')->name('expenses.show');
    Route::get('/depenses/{expense}/imprimer', [ExpenseController::class, 'print'])->middleware('permission:expenses.view')->name('expenses.print');
});
