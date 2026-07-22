<?php

use App\Modules\Purchases\Http\Controllers\GoodsReceiptController;
use App\Modules\Purchases\Http\Controllers\PurchaseBillController;
use App\Modules\Purchases\Http\Controllers\PurchaseCreditNoteController;
use App\Modules\Purchases\Http\Controllers\PurchaseOrderController;
use App\Modules\Purchases\Http\Controllers\PurchaseRequestController;
use App\Modules\Purchases\Http\Controllers\ReplenishmentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'workspace'])->group(function (): void {
    Route::get('/reapprovisionnements', [ReplenishmentController::class, 'index'])->middleware('permission:purchase_requests.view')->name('replenishments.index');
    Route::get('/reapprovisionnements/export', [ReplenishmentController::class, 'export'])->middleware('permission:purchase_requests.view')->name('replenishments.export');
    Route::get('/reapprovisionnements/imprimer', [ReplenishmentController::class, 'print'])->middleware('permission:purchase_requests.view')->name('replenishments.print');
    Route::post('/reapprovisionnements/activer-produits', [ReplenishmentController::class, 'activateProducts'])->middleware('permission:purchase_requests.manage')->name('replenishments.activate-products');
    Route::post('/reapprovisionnements/generer', [ReplenishmentController::class, 'generate'])->middleware('permission:purchase_requests.manage')->name('replenishments.generate');
    Route::get('/demandes-achats', [PurchaseRequestController::class, 'index'])->middleware('permission:purchase_requests.view')->name('purchase-requests.index');
    Route::get('/demandes-achats/creer', [PurchaseRequestController::class, 'create'])->middleware('permission:purchase_requests.manage')->name('purchase-requests.create');
    Route::post('/demandes-achats', [PurchaseRequestController::class, 'store'])->middleware('permission:purchase_requests.manage')->name('purchase-requests.store');
    Route::post('/demandes-achats/{purchaseRequest}/approuver', [PurchaseRequestController::class, 'approve'])->middleware('permission:purchase_requests.approve')->name('purchase-requests.approve');
    Route::post('/demandes-achats/{purchaseRequest}/rejeter', [PurchaseRequestController::class, 'reject'])->middleware('permission:purchase_requests.approve')->name('purchase-requests.reject');
    Route::post('/demandes-achats/{purchaseRequest}/convertir', [PurchaseRequestController::class, 'convert'])->middleware('permission:purchase_requests.manage')->name('purchase-requests.convert');
    Route::post('/demandes-achats/{purchaseRequest}/convertir-recommande', [PurchaseRequestController::class, 'autoConvert'])->middleware('permission:purchase_requests.manage')->name('purchase-requests.auto-convert');
    Route::get('/demandes-achats/{purchaseRequest}', [PurchaseRequestController::class, 'show'])->middleware('permission:purchase_requests.view')->name('purchase-requests.show');

    Route::get('/achats', [PurchaseBillController::class, 'index'])->middleware('permission:purchases.view')->name('purchases.index');
    Route::get('/achats/export', [PurchaseBillController::class, 'export'])->middleware('permission:purchases.view')->name('purchases.export');
    Route::get('/achats/creer', [PurchaseBillController::class, 'create'])->middleware('permission:purchases.manage')->name('purchases.create');
    Route::post('/achats', [PurchaseBillController::class, 'store'])->middleware('permission:purchases.manage')->name('purchases.store');
    Route::post('/achats/{purchase}/approuver', [PurchaseBillController::class, 'approve'])->middleware('permission:purchases.approve')->name('purchases.approve');
    Route::post('/achats/{purchase}/rejeter', [PurchaseBillController::class, 'reject'])->middleware('permission:purchases.approve')->name('purchases.reject');
    Route::get('/avoirs-fournisseurs', [PurchaseCreditNoteController::class, 'index'])->middleware('permission:supplier_credit_notes.view')->name('purchase-credit-notes.index');
    Route::get('/achats/{purchase}/avoirs/creer', [PurchaseCreditNoteController::class, 'create'])->middleware('permission:supplier_credit_notes.issue')->name('purchase-credit-notes.create');
    Route::post('/achats/{purchase}/avoirs', [PurchaseCreditNoteController::class, 'store'])->middleware('permission:supplier_credit_notes.issue')->name('purchase-credit-notes.store');
    Route::get('/avoirs-fournisseurs/{creditNote}', [PurchaseCreditNoteController::class, 'show'])->middleware('permission:supplier_credit_notes.view')->name('purchase-credit-notes.show');
    Route::get('/avoirs-fournisseurs/{creditNote}/imprimer', [PurchaseCreditNoteController::class, 'print'])->middleware('permission:supplier_credit_notes.view')->name('purchase-credit-notes.print');
    Route::get('/achats/{purchase}', [PurchaseBillController::class, 'show'])->middleware('permission:purchases.view')->name('purchases.show');
    Route::get('/achats/{purchase}/imprimer', [PurchaseBillController::class, 'print'])->middleware('permission:purchases.view')->name('purchases.print');

    Route::get('/commandes-fournisseurs', [PurchaseOrderController::class, 'index'])->middleware('permission:purchase_orders.view')->name('purchase-orders.index');
    Route::get('/commandes-fournisseurs/creer', [PurchaseOrderController::class, 'create'])->middleware('permission:purchase_orders.manage')->name('purchase-orders.create');
    Route::post('/commandes-fournisseurs', [PurchaseOrderController::class, 'store'])->middleware('permission:purchase_orders.manage')->name('purchase-orders.store');
    Route::post('/commandes-fournisseurs/{purchaseOrder}/confirmer', [PurchaseOrderController::class, 'confirm'])->middleware('permission:purchase_orders.manage')->name('purchase-orders.confirm');
    Route::post('/commandes-fournisseurs/{purchaseOrder}/annuler', [PurchaseOrderController::class, 'cancel'])->middleware('permission:purchase_orders.manage')->name('purchase-orders.cancel');
    Route::get('/commandes-fournisseurs/{purchaseOrder}/imprimer', [PurchaseOrderController::class, 'print'])->middleware('permission:purchase_orders.view')->name('purchase-orders.print');
    Route::get('/commandes-fournisseurs/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->middleware('permission:purchase_orders.view')->name('purchase-orders.show');

    Route::get('/receptions-fournisseurs', [GoodsReceiptController::class, 'index'])->middleware('permission:goods_receipts.view')->name('goods-receipts.index');
    Route::get('/receptions-fournisseurs/creer', [GoodsReceiptController::class, 'create'])->middleware('permission:goods_receipts.manage')->name('goods-receipts.create');
    Route::post('/receptions-fournisseurs', [GoodsReceiptController::class, 'store'])->middleware('permission:goods_receipts.manage')->name('goods-receipts.store');
    Route::get('/receptions-fournisseurs/{goodsReceipt}/imprimer', [GoodsReceiptController::class, 'print'])->middleware('permission:goods_receipts.view')->name('goods-receipts.print');
    Route::get('/receptions-fournisseurs/{goodsReceipt}', [GoodsReceiptController::class, 'show'])->middleware('permission:goods_receipts.view')->name('goods-receipts.show');
});
