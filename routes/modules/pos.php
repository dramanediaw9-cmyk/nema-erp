<?php

use App\Modules\Pos\Http\Controllers\PosController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'workspace'])->group(function (): void {
    Route::get('/point-de-vente', [PosController::class, 'index'])->middleware('permission:pos.view')->name('pos.index');
    Route::post('/point-de-vente/session', [PosController::class, 'open'])->middleware('permission:pos.manage')->name('pos.open');
    Route::get('/point-de-vente/sessions/{session}', [PosController::class, 'show'])->middleware('permission:pos.view')->name('pos.show');
    Route::get('/point-de-vente/sessions/{session}/comptage', [PosController::class, 'countSheet'])->middleware('permission:pos.view')->name('pos.count-sheet');
    Route::post('/point-de-vente/sessions/{session}/cloturer', [PosController::class, 'close'])->middleware('permission:pos.manage')->name('pos.close');
    Route::get('/point-de-vente/rapport-journalier', [PosController::class, 'report'])->middleware('permission:pos.view')->name('pos.report');
    Route::get('/point-de-vente/vente', [PosController::class, 'createSale'])->middleware('permission:pos.manage')->name('pos.sales.create');
    Route::post('/point-de-vente/vente', [PosController::class, 'storeSale'])->middleware('permission:pos.manage')->name('pos.sales.store');
    Route::post('/point-de-vente/brouillons', [PosController::class, 'storeDraft'])->middleware('permission:pos.manage')->name('pos.drafts.store');
    Route::delete('/point-de-vente/brouillons/{draft}', [PosController::class, 'destroyDraft'])->middleware('permission:pos.manage')->name('pos.drafts.destroy');
    Route::get('/point-de-vente/tickets/{sale}', [PosController::class, 'receipt'])->middleware('permission:pos.view')->name('pos.receipt');
    Route::get('/point-de-vente/tickets/{sale}/thermique', [PosController::class, 'thermalReceipt'])->middleware('permission:pos.view')->name('pos.receipt.thermal');
    Route::get('/point-de-vente/tickets/{sale}/retour', [PosController::class, 'returnForm'])->middleware('permission:pos.manage')->name('pos.returns.create');
    Route::post('/point-de-vente/tickets/{sale}/retour', [PosController::class, 'storeReturn'])->middleware('permission:pos.manage')->name('pos.returns.store');
});
