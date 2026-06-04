<?php

use App\Modules\Pos\Http\Controllers\PosBackofficeController;
use App\Modules\Pos\Http\Controllers\PosController;
use App\Modules\Pos\Http\Controllers\PosPreparationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'workspace'])->group(function (): void {
    Route::get('/point-de-vente', [PosController::class, 'index'])->middleware('permission:pos.view')->name('pos.index');
    Route::get('/point-de-vente/commandes', [PosBackofficeController::class, 'orders'])->middleware('permission:pos.view')->name('pos.orders.index');
    Route::get('/point-de-vente/sessions', [PosBackofficeController::class, 'sessions'])->middleware('permission:pos.view')->name('pos.sessions.index');
    Route::get('/point-de-vente/paiements', [PosBackofficeController::class, 'payments'])->middleware('permission:pos.view')->name('pos.payments.index');
    Route::get('/point-de-vente/clients', [PosBackofficeController::class, 'customers'])->middleware('permission:pos.view')->name('pos.customers.index');
    Route::get('/point-de-vente/produits', [PosBackofficeController::class, 'products'])->middleware('permission:pos.view')->name('pos.products.index');
    Route::get('/point-de-vente/tarification', [PosBackofficeController::class, 'pricing'])->middleware('permission:pos.view')->name('pos.pricing.index');
    Route::get('/point-de-vente/analyse', [PosBackofficeController::class, 'analytics'])->middleware('permission:pos.view')->name('pos.analytics.index');
    Route::get('/point-de-vente/preparation', [PosPreparationController::class, 'index'])->middleware('permission:pos.view')->name('pos.preparation.index');
    Route::get('/point-de-vente/preparation/displays/{display}', [PosPreparationController::class, 'display'])->middleware('permission:pos.view')->name('pos.preparation.display');
    Route::get('/point-de-vente/preparation/displays/{display}/snapshot', [PosPreparationController::class, 'displaySnapshot'])->middleware('permission:pos.view')->name('pos.preparation.display.snapshot');
    Route::post('/point-de-vente/preparation/{ticket}/statut', [PosPreparationController::class, 'updateStatus'])->middleware('permission:pos.manage')->name('pos.preparation.update');
    Route::get('/point-de-vente/preparation/{ticket}/imprimer', [PosPreparationController::class, 'printTicket'])->middleware('permission:pos.view')->name('pos.preparation.print');
    Route::get('/point-de-vente/configuration', [PosBackofficeController::class, 'settings'])->middleware('permission:pos.view')->name('pos.settings.index');
    Route::post('/point-de-vente/configuration/modes-paiement', [PosBackofficeController::class, 'storePaymentMethod'])->middleware('permission:pos.manage')->name('pos.payment-methods.store');
    Route::post('/point-de-vente/tarification/fidelite', [PosBackofficeController::class, 'storeLoyaltyProgram'])->middleware('permission:pos.manage')->name('pos.loyalty-programs.store');
    Route::post('/point-de-vente/tarification/cartes-valeur', [PosBackofficeController::class, 'storeStoredValueCard'])->middleware('permission:pos.manage')->name('pos.stored-value-cards.store');
    Route::post('/point-de-vente/configuration/imprimantes-preparation', [PosBackofficeController::class, 'storePreparationPrinter'])->middleware('permission:pos.manage')->name('pos.preparation-printers.store');
    Route::post('/point-de-vente/configuration/preparation-display', [PosBackofficeController::class, 'storePreparationDisplay'])->middleware('permission:pos.manage')->name('pos.preparation-displays.store');
    Route::post('/point-de-vente/configuration/modeles-notes', [PosBackofficeController::class, 'storeNoteTemplate'])->middleware('permission:pos.manage')->name('pos.note-templates.store');
    Route::post('/point-de-vente/produits/combos', [PosBackofficeController::class, 'storeComboChoice'])->middleware('permission:pos.manage')->name('pos.combo-choices.store');
    Route::post('/point-de-vente/produits/categories-menu', [PosBackofficeController::class, 'storeMenuCategory'])->middleware('permission:pos.manage')->name('pos.menu-categories.store');
    Route::post('/point-de-vente/produits/etiquettes', [PosBackofficeController::class, 'storeProductTag'])->middleware('permission:pos.manage')->name('pos.product-tags.store');
    Route::post('/point-de-vente/configuration/profils', [PosBackofficeController::class, 'storeProfile'])->middleware('permission:pos.manage')->name('pos.profiles.store');
    Route::post('/point-de-vente/session', [PosController::class, 'open'])->middleware('permission:pos.manage')->name('pos.open');
    Route::get('/point-de-vente/sessions/{session}', [PosController::class, 'show'])->middleware('permission:pos.view')->name('pos.show');
    Route::get('/point-de-vente/sessions/{session}/comptage', [PosController::class, 'countSheet'])->middleware('permission:pos.view')->name('pos.count-sheet');
    Route::post('/point-de-vente/sessions/{session}/cloturer', [PosController::class, 'close'])->middleware('permission:pos.manage')->name('pos.close');
    Route::post('/point-de-vente/sessions/{session}/deverrouiller', [PosController::class, 'unlock'])->middleware('permission:pos.sessions.unlock')->name('pos.unlock');
    Route::get('/point-de-vente/rapport-journalier', [PosController::class, 'report'])->middleware('permission:pos.view')->name('pos.report');
    Route::get('/point-de-vente/stock-disponible', [PosController::class, 'stockAvailability'])->middleware('permission:pos.manage')->name('pos.stock-availability');
    Route::get('/point-de-vente/vente', [PosController::class, 'createSale'])->middleware('permission:pos.manage')->name('pos.sales.create');
    Route::post('/point-de-vente/vente', [PosController::class, 'storeSale'])->middleware('permission:pos.manage')->name('pos.sales.store');
    Route::post('/point-de-vente/brouillons', [PosController::class, 'storeDraft'])->middleware('permission:pos.manage')->name('pos.drafts.store');
    Route::delete('/point-de-vente/brouillons/{draft}', [PosController::class, 'destroyDraft'])->middleware('permission:pos.manage')->name('pos.drafts.destroy');
    Route::get('/point-de-vente/tickets/{sale}', [PosController::class, 'receipt'])->middleware('permission:pos.view')->name('pos.receipt');
    Route::get('/point-de-vente/tickets/{sale}/thermique', [PosController::class, 'thermalReceipt'])->middleware('permission:pos.view')->name('pos.receipt.thermal');
    Route::get('/point-de-vente/tickets/{sale}/retour', [PosController::class, 'returnForm'])->middleware('permission:pos.manage')->name('pos.returns.create');
    Route::post('/point-de-vente/tickets/{sale}/retour', [PosController::class, 'storeReturn'])->middleware('permission:pos.manage')->name('pos.returns.store');
});
