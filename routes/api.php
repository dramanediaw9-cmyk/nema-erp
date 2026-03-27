<?php

use App\Http\Controllers\Api\V1\IntegrationEventController;
use App\Http\Controllers\Api\V1\PartnerController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\SalesInvoiceController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('api.token')->group(function (): void {
    Route::get('/workspace', WorkspaceController::class);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/partners', [PartnerController::class, 'index']);
    Route::get('/sales-invoices', [SalesInvoiceController::class, 'index']);
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/integration-events', [IntegrationEventController::class, 'index']);
});
