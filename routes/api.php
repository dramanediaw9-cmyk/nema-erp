<?php

use App\Http\Controllers\Api\V1\AccountingLocalizationController;
use App\Http\Controllers\Api\V1\HrLeaveRequestController;
use App\Http\Controllers\Api\V1\IntegrationEventController;
use App\Http\Controllers\Api\V1\HrDepartmentController;
use App\Http\Controllers\Api\V1\HrEmployeeController;
use App\Http\Controllers\Api\V1\PartnerController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PlatformCapabilityController;
use App\Http\Controllers\Api\V1\PlatformConnectionController;
use App\Http\Controllers\Api\V1\PlatformDeploymentProfileController;
use App\Http\Controllers\Api\V1\PlatformOpenApiController;
use App\Http\Controllers\Api\V1\PayrollRunController;
use App\Http\Controllers\Api\V1\PayrollSlipController;
use App\Http\Controllers\Api\V1\ManufacturingBomController;
use App\Http\Controllers\Api\V1\ProductionOrderController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\SalesInvoiceController;
use App\Http\Controllers\Api\V1\CommerceChannelController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('api.token')->group(function (): void {
    Route::get('/workspace', WorkspaceController::class);
    Route::get('/platform/capabilities', PlatformCapabilityController::class);
    Route::get('/platform/deployment-profile', [PlatformDeploymentProfileController::class, 'show']);
    Route::match(['put', 'patch'], '/platform/deployment-profile', [PlatformDeploymentProfileController::class, 'update']);
    Route::get('/platform/openapi', PlatformOpenApiController::class);
    Route::get('/platform/connections', [PlatformConnectionController::class, 'index']);
    Route::get('/platform/connections/{integrationConnection}', [PlatformConnectionController::class, 'show']);
    Route::post('/platform/connections', [PlatformConnectionController::class, 'store']);
    Route::match(['put', 'patch'], '/platform/connections/{integrationConnection}/status', [PlatformConnectionController::class, 'updateStatus']);
    Route::get('/accounting/localization', AccountingLocalizationController::class);

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

    Route::get('/hr/departments', [HrDepartmentController::class, 'index']);
    Route::get('/hr/departments/{hrDepartment}', [HrDepartmentController::class, 'show']);
    Route::post('/hr/departments', [HrDepartmentController::class, 'store']);

    Route::get('/hr/employees', [HrEmployeeController::class, 'index']);
    Route::get('/hr/employees/{hrEmployee}', [HrEmployeeController::class, 'show']);
    Route::post('/hr/employees', [HrEmployeeController::class, 'store']);

    Route::get('/hr/leave-requests', [HrLeaveRequestController::class, 'index']);
    Route::get('/hr/leave-requests/{leaveRequest}', [HrLeaveRequestController::class, 'show']);
    Route::post('/hr/leave-requests', [HrLeaveRequestController::class, 'store']);

    Route::get('/payroll/runs', [PayrollRunController::class, 'index']);
    Route::get('/payroll/runs/{payrollRun}', [PayrollRunController::class, 'show']);
    Route::post('/payroll/runs', [PayrollRunController::class, 'store']);

    Route::get('/payroll/slips', [PayrollSlipController::class, 'index']);
    Route::get('/payroll/slips/{payrollSlip}', [PayrollSlipController::class, 'show']);
    Route::post('/payroll/slips', [PayrollSlipController::class, 'store']);

    Route::get('/projects', [ProjectController::class, 'index']);
    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::post('/projects/{project}/tasks', [ProjectController::class, 'storeTask']);
    Route::match(['put', 'patch'], '/projects/{project}/tasks/{projectTask}', [ProjectController::class, 'updateTask']);

    Route::get('/manufacturing/boms', [ManufacturingBomController::class, 'index']);
    Route::get('/manufacturing/boms/{manufacturingBom}', [ManufacturingBomController::class, 'show']);
    Route::post('/manufacturing/boms', [ManufacturingBomController::class, 'store']);

    Route::get('/production-orders', [ProductionOrderController::class, 'index']);
    Route::get('/production-orders/{productionOrder}', [ProductionOrderController::class, 'show']);
    Route::post('/production-orders', [ProductionOrderController::class, 'store']);

    Route::get('/commerce/channels', [CommerceChannelController::class, 'index']);
    Route::get('/commerce/channels/{commerceChannel}', [CommerceChannelController::class, 'show']);
    Route::post('/commerce/channels', [CommerceChannelController::class, 'store']);
    Route::post('/commerce/channels/{commerceChannel}/snapshots', [CommerceChannelController::class, 'storeSnapshot']);
    Route::post('/commerce/channels/{commerceChannel}/actions', [CommerceChannelController::class, 'storeAction']);
    Route::match(['put', 'patch'], '/commerce/channels/{commerceChannel}/actions/{commerceChannelAction}', [CommerceChannelController::class, 'updateAction']);

    Route::get('/integration-events', [IntegrationEventController::class, 'index']);
    Route::get('/integration-events/{integrationEvent}', [IntegrationEventController::class, 'show']);
});
