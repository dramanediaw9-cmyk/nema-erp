<?php

use App\Modules\Commerce\Http\Controllers\CommerceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'workspace'])->group(function (): void {
    Route::get('/commerce-unifie', [CommerceController::class, 'index'])->middleware('permission:commerce.view')->name('commerce.index');
    Route::post('/commerce-unifie', [CommerceController::class, 'store'])->middleware('permission:commerce.manage')->name('commerce.store');
    Route::post('/commerce-unifie/{commerceChannel}/snapshots', [CommerceController::class, 'storeSnapshot'])->middleware('permission:commerce.manage')->name('commerce.snapshots.store');
    Route::post('/commerce-unifie/{commerceChannel}/actions', [CommerceController::class, 'storeAction'])->middleware('permission:commerce.manage')->name('commerce.actions.store');
    Route::post('/commerce-unifie/actions/{commerceChannelAction}/status', [CommerceController::class, 'updateActionStatus'])->middleware('permission:commerce.manage')->name('commerce.actions.status');
});
