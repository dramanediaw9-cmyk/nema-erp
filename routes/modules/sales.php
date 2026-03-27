<?php

use App\Modules\Sales\Http\Controllers\DeliveryNoteController;
use App\Modules\Sales\Http\Controllers\SalesCreditNoteController;
use App\Modules\Sales\Http\Controllers\SalesInvoiceController;
use App\Modules\Sales\Http\Controllers\SalesOrderController;
use App\Modules\Sales\Http\Controllers\SalesQuoteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'workspace'])->group(function (): void {
    Route::get('/devis', [SalesQuoteController::class, 'index'])->middleware('permission:quotes.view')->name('quotes.index');
    Route::get('/devis/export', [SalesQuoteController::class, 'export'])->middleware('permission:quotes.view')->name('quotes.export');
    Route::get('/devis/creer', [SalesQuoteController::class, 'create'])->middleware('permission:quotes.manage')->name('quotes.create');
    Route::post('/devis', [SalesQuoteController::class, 'store'])->middleware('permission:quotes.manage')->name('quotes.store');
    Route::post('/devis/{quote}/envoyer', [SalesQuoteController::class, 'send'])->middleware('permission:quotes.manage')->name('quotes.send');
    Route::post('/devis/{quote}/accepter', [SalesQuoteController::class, 'accept'])->middleware('permission:quotes.manage')->name('quotes.accept');
    Route::post('/devis/{quote}/annuler', [SalesQuoteController::class, 'cancel'])->middleware('permission:quotes.manage')->name('quotes.cancel');
    Route::post('/devis/{quote}/convertir-commande', [SalesQuoteController::class, 'convertToOrder'])->middleware('permission:quotes.manage')->name('quotes.convert-order');
    Route::post('/devis/{quote}/convertir', [SalesQuoteController::class, 'convert'])->middleware('permission:quotes.manage')->name('quotes.convert');
    Route::get('/devis/{quote}', [SalesQuoteController::class, 'show'])->middleware('permission:quotes.view')->name('quotes.show');
    Route::get('/devis/{quote}/imprimer', [SalesQuoteController::class, 'print'])->middleware('permission:quotes.view')->name('quotes.print');

    Route::get('/commandes-clients', [SalesOrderController::class, 'index'])->middleware('permission:orders.view')->name('orders.index');
    Route::get('/commandes-clients/export', [SalesOrderController::class, 'export'])->middleware('permission:orders.view')->name('orders.export');
    Route::get('/commandes-clients/creer', [SalesOrderController::class, 'create'])->middleware('permission:orders.manage')->name('orders.create');
    Route::post('/commandes-clients', [SalesOrderController::class, 'store'])->middleware('permission:orders.manage')->name('orders.store');
    Route::post('/commandes-clients/{order}/confirmer', [SalesOrderController::class, 'confirm'])->middleware('permission:orders.manage')->name('orders.confirm');
    Route::post('/commandes-clients/{order}/annuler', [SalesOrderController::class, 'cancel'])->middleware('permission:orders.manage')->name('orders.cancel');
    Route::post('/commandes-clients/{order}/convertir', [SalesOrderController::class, 'convert'])->middleware('permission:orders.manage')->name('orders.convert');
    Route::get('/commandes-clients/{order}', [SalesOrderController::class, 'show'])->middleware('permission:orders.view')->name('orders.show');
    Route::get('/commandes-clients/{order}/imprimer', [SalesOrderController::class, 'print'])->middleware('permission:orders.view')->name('orders.print');

    Route::get('/bons-livraison', [DeliveryNoteController::class, 'index'])->middleware('permission:delivery_notes.view')->name('delivery-notes.index');
    Route::get('/bons-livraison/export', [DeliveryNoteController::class, 'export'])->middleware('permission:delivery_notes.view')->name('delivery-notes.export');
    Route::get('/bons-livraison/creer', [DeliveryNoteController::class, 'create'])->middleware('permission:delivery_notes.manage')->name('delivery-notes.create');
    Route::post('/bons-livraison', [DeliveryNoteController::class, 'store'])->middleware('permission:delivery_notes.manage')->name('delivery-notes.store');
    Route::post('/bons-livraison/{deliveryNote}/convertir', [DeliveryNoteController::class, 'convert'])->middleware('permission:delivery_notes.manage')->name('delivery-notes.convert');
    Route::get('/bons-livraison/{deliveryNote}', [DeliveryNoteController::class, 'show'])->middleware('permission:delivery_notes.view')->name('delivery-notes.show');
    Route::get('/bons-livraison/{deliveryNote}/imprimer', [DeliveryNoteController::class, 'print'])->middleware('permission:delivery_notes.view')->name('delivery-notes.print');

    Route::get('/avoirs-clients', [SalesCreditNoteController::class, 'index'])->middleware('permission:credit_notes.view')->name('credit-notes.index');
    Route::get('/ventes/{sale}/avoirs/creer', [SalesCreditNoteController::class, 'create'])->middleware('permission:credit_notes.manage')->name('credit-notes.create');
    Route::post('/ventes/{sale}/avoirs', [SalesCreditNoteController::class, 'store'])->middleware('permission:credit_notes.manage')->name('credit-notes.store');
    Route::get('/avoirs-clients/{creditNote}', [SalesCreditNoteController::class, 'show'])->middleware('permission:credit_notes.view')->name('credit-notes.show');
    Route::get('/avoirs-clients/{creditNote}/imprimer', [SalesCreditNoteController::class, 'print'])->middleware('permission:credit_notes.view')->name('credit-notes.print');

    Route::get('/ventes', [SalesInvoiceController::class, 'index'])->middleware('permission:sales.view')->name('sales.index');
    Route::get('/ventes/export', [SalesInvoiceController::class, 'export'])->middleware('permission:sales.view')->name('sales.export');
    Route::get('/ventes/creer', [SalesInvoiceController::class, 'create'])->middleware('permission:sales.manage')->name('sales.create');
    Route::post('/ventes', [SalesInvoiceController::class, 'store'])->middleware('permission:sales.manage')->name('sales.store');
    Route::post('/ventes/{sale}/approuver', [SalesInvoiceController::class, 'approve'])->middleware('permission:sales.approve')->name('sales.approve');
    Route::get('/ventes/{sale}', [SalesInvoiceController::class, 'show'])->middleware('permission:sales.view')->name('sales.show');
    Route::get('/ventes/{sale}/imprimer', [SalesInvoiceController::class, 'print'])->middleware('permission:sales.view')->name('sales.print');
});


