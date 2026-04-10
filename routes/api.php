<?php

use App\Http\Controllers\Api\V1\IntegrationEventController;
use App\Http\Controllers\Api\V1\PartnerController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PlatformCapabilityController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\SalesInvoiceController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('api.token')->group(function (): void {
    Route::get('/workspace', WorkspaceController::class);
    Route::get('/platform/capabilities', PlatformCapabilityController::class);

    Route::get('/products', [ProductController::class, 'index']);

    Route::get('/partners', [PartnerController::class, 'index']);
    Route::get('/partners/{partner}', [PartnerController::class, 'show']);
    Route::post('/partners', [PartnerController::class, 'store']);
    Route::match(['put', 'patch'], '/partners/{partner}', [PartnerController::class, 'update']);

    Route::get('/sales-invoices', [SalesInvoiceController::class, 'index']);
    Route::get('/sales-invoices/{salesInvoice}', [SalesInvoiceController::class, 'show']);
    Route::post('/sales-invoices', [SalesInvoiceController::class, 'store']);

    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/payments/{payment}', [PaymentController::class, 'show']);
    Route::post('/payments', [PaymentController::class, 'store']);

    Route::get('/integration-events', [IntegrationEventController::class, 'index']);
    Route::get('/integration-events/{integrationEvent}', [IntegrationEventController::class, 'show']);
});
