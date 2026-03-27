<?php

use App\Modules\Crm\Http\Controllers\OpportunityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'workspace'])->group(function (): void {
    Route::get('/crm', [OpportunityController::class, 'index'])->middleware('permission:crm.view')->name('crm.index');
    Route::get('/crm/creer', [OpportunityController::class, 'create'])->middleware('permission:crm.manage')->name('crm.create');
    Route::post('/crm', [OpportunityController::class, 'store'])->middleware('permission:crm.manage')->name('crm.store');
    Route::get('/crm/{opportunity}', [OpportunityController::class, 'show'])->middleware('permission:crm.view')->name('crm.show');
    Route::post('/crm/{opportunity}/etape', [OpportunityController::class, 'updateStage'])->middleware('permission:crm.manage')->name('crm.update-stage');
    Route::post('/crm/{opportunity}/convertir-client', [OpportunityController::class, 'convertToCustomer'])->middleware('permission:crm.manage')->name('crm.convert-customer');
});
