<?php

use App\Modules\Core\Imports\Http\Controllers\ImportController;
use App\Modules\Core\Imports\Odoo\Http\Controllers\OdooProductImportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'workspace'])->group(function (): void {
    Route::get('/imports', [ImportController::class, 'index'])->middleware('permission:imports.manage')->name('imports.index');
    Route::get('/imports/modeles/{type}', [ImportController::class, 'downloadTemplate'])->middleware('permission:imports.manage')->name('imports.templates.download');
    Route::post('/imports/clients', [ImportController::class, 'importCustomers'])->middleware('permission:imports.manage')->name('imports.customers.store');
    Route::post('/imports/fournisseurs', [ImportController::class, 'importSuppliers'])->middleware('permission:imports.manage')->name('imports.suppliers.store');
    Route::post('/imports/produits', [ImportController::class, 'importProducts'])->middleware('permission:imports.manage')->name('imports.products.store');
    Route::post('/imports/stock-initial', [ImportController::class, 'importOpeningStock'])->middleware('permission:imports.manage')->name('imports.opening-stock.store');
    Route::post('/imports/ventes-historiques', [ImportController::class, 'importHistoricalSales'])->middleware('permission:imports.manage')->name('imports.historical-sales.store');
    Route::post('/imports/achats-historiques', [ImportController::class, 'importHistoricalPurchases'])->middleware('permission:imports.manage')->name('imports.historical-purchases.store');
    Route::prefix('/imports/odoo')->middleware('permission:imports.manage')->name('imports.odoo.')->group(function (): void {
        Route::get('/', [OdooProductImportController::class, 'index'])->name('index');
        Route::post('/connexions', [OdooProductImportController::class, 'saveConnection'])->name('connections.save');
        Route::post('/connexions/{connection}/tester', [OdooProductImportController::class, 'testConnection'])->name('connections.test');
        Route::post('/connexions/{connection}/synchroniser', [OdooProductImportController::class, 'start'])->name('connections.start');
        Route::get('/executions/{run:uuid}', [OdooProductImportController::class, 'status'])->name('runs.status');
        Route::post('/executions/{run:uuid}/annuler', [OdooProductImportController::class, 'cancel'])->name('runs.cancel');
        Route::post('/executions/{run:uuid}/reprendre', [OdooProductImportController::class, 'resume'])->name('runs.resume');
    });
});
