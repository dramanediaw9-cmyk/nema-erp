<?php

use App\Modules\Commerce\Http\Controllers\CommerceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'workspace'])->group(function (): void {
    Route::get('/commerce-unifie', [CommerceController::class, 'index'])->middleware('permission:commerce.view')->name('commerce.index');
    Route::post('/commerce-unifie', [CommerceController::class, 'store'])->middleware('permission:commerce.manage')->name('commerce.store');
});
