<?php

use App\Modules\Inventory\Http\Controllers\StockController;
use App\Modules\Inventory\Http\Controllers\StockCountController;
use App\Modules\Inventory\Http\Controllers\StockTransferController;
use App\Modules\Inventory\Http\Controllers\ProductLotController;
use App\Modules\Inventory\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'workspace'])->group(function (): void {
    Route::get('/stock', [StockController::class, 'index'])->middleware('permission:stock.view')->name('stock.index');
    Route::get('/stock/export', [StockController::class, 'export'])->middleware('permission:stock.view')->name('stock.export');
    Route::get('/stock/mouvements', [StockController::class, 'movements'])->middleware('permission:stock.view')->name('stock.movements');
    Route::get('/stock/mouvements/imprimer', [StockController::class, 'printMovements'])->middleware('permission:stock.view')->name('stock.movements.print');
    Route::get('/stock/lots', [ProductLotController::class, 'index'])->middleware('permission:stock.view')->name('stock.lots');
    Route::get('/stock/pertes', [StockController::class, 'createLoss'])->middleware('permission:stock.manage')->name('stock.losses.create');
    Route::get('/stock/produits/{product}', [StockController::class, 'show'])->middleware('permission:stock.view')->name('stock.show');
    Route::get('/stock/initial', [StockController::class, 'createOpening'])->middleware('permission:stock.manage')->name('stock.opening.create');
    Route::post('/stock/initial', [StockController::class, 'storeOpening'])->middleware('permission:stock.manage')->name('stock.opening.store');
    Route::get('/stock/ajustements', [StockController::class, 'createAdjustment'])->middleware('permission:stock.manage')->name('stock.adjustments.create');
    Route::post('/stock/ajustements', [StockController::class, 'storeAdjustment'])->middleware('permission:stock.manage')->name('stock.adjustments.store');
    Route::get('/stock/inventaires', [StockCountController::class, 'index'])->middleware('permission:stock_counts.view')->name('stock-counts.index');
    Route::get('/stock/inventaires/rapide', [StockCountController::class, 'quick'])->middleware('permission:stock_counts.manage')->name('stock-counts.quick');
    Route::get('/stock/inventaires/creer', [StockCountController::class, 'create'])->middleware('permission:stock_counts.manage')->name('stock-counts.create');
    Route::post('/stock/inventaires', [StockCountController::class, 'store'])->middleware('permission:stock_counts.manage')->name('stock-counts.store');
    Route::post('/stock/inventaires/rapide', [StockCountController::class, 'storeQuick'])->middleware('permission:stock_counts.manage')->name('stock-counts.quick.store');
    Route::post('/stock/inventaires/{stockCount}/valider', [StockCountController::class, 'post'])->middleware('permission:stock_counts.manage')->name('stock-counts.post');
    Route::get('/stock/inventaires/{stockCount}/imprimer', [StockCountController::class, 'print'])->middleware('permission:stock_counts.view')->name('stock-counts.print');
    Route::get('/stock/inventaires/{stockCount}', [StockCountController::class, 'show'])->middleware('permission:stock_counts.view')->name('stock-counts.show');

    Route::get('/stock/entrepots', [WarehouseController::class, 'index'])->middleware('permission:warehouses.view')->name('warehouses.index');
    Route::post('/stock/entrepots', [WarehouseController::class, 'store'])->middleware('permission:warehouses.manage')->name('warehouses.store');

    Route::get('/stock/transferts', [StockTransferController::class, 'index'])->middleware('permission:transfers.view')->name('transfers.index');
    Route::get('/stock/transferts/creer', [StockTransferController::class, 'create'])->middleware('permission:transfers.manage')->name('transfers.create');
    Route::post('/stock/transferts', [StockTransferController::class, 'store'])->middleware('permission:transfers.manage')->name('transfers.store');
    Route::get('/stock/transferts/{transfer}/imprimer', [StockTransferController::class, 'print'])->middleware('permission:transfers.view')->name('transfers.print');
    Route::get('/stock/transferts/{transfer}', [StockTransferController::class, 'show'])->middleware('permission:transfers.view')->name('transfers.show');
});
