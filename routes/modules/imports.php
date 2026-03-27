<?php

use App\Modules\Core\Imports\Http\Controllers\ImportController;
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
});