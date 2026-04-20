<?php

use App\Http\Controllers\Api\InboundIntegrationWebhookController;
use App\Modules\Core\Access\Http\Controllers\RoleController;
use App\Modules\Core\Access\Http\Controllers\UserController;
use App\Modules\Core\Automation\Http\Controllers\AutomationController;
use App\Modules\Core\Approvals\Http\Controllers\ApprovalPortalController;
use App\Modules\Core\Approvals\Http\Controllers\ApprovalStepController;
use App\Modules\Core\Audit\Http\Controllers\ActivityLogController;
use App\Modules\Core\Auth\Http\Controllers\AuthenticatedSessionController;
use App\Modules\Core\Branch\Http\Controllers\BranchController;
use App\Modules\Core\Collaboration\Http\Controllers\DocumentCollaborationController;
use App\Modules\Core\Company\Http\Controllers\CompanyController;
use App\Modules\Core\Company\Http\Controllers\SettingsController;
use App\Modules\Core\Dashboard\Http\Controllers\DashboardController;
use App\Modules\Core\Dashboard\Http\Controllers\GlobalSearchController;
use App\Modules\Core\Dashboard\Http\Controllers\MerchantRoutineController;
use App\Modules\Core\Dashboard\Http\Controllers\UiModeController;
use App\Modules\Core\Notifications\Http\Controllers\NotificationController;
use App\Modules\Core\Notifications\Http\Controllers\OutboundNotificationController;
use App\Modules\Core\Onboarding\Http\Controllers\OnboardingController;
use App\Modules\Core\Ops\Http\Controllers\OperationsController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::post('/integrations/webhooks/inbound/{company}', [InboundIntegrationWebhookController::class, 'store'])
    ->name('integrations.webhooks.inbound.receive');

Route::middleware(['auth', 'active', 'workspace'])->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    Route::get('/routine-commerce', MerchantRoutineController::class)
        ->middleware('permission:dashboard.view')
        ->name('merchant.routine');

    Route::get('/recherche', GlobalSearchController::class)
        ->middleware('permission:dashboard.view')
        ->name('search.index');
    Route::post('/interface/mode', [UiModeController::class, 'update'])
        ->middleware('permission:dashboard.view')
        ->name('ui-mode.update');

    Route::get('/demarrage', [OnboardingController::class, 'index'])
        ->middleware('permission:dashboard.view')
        ->name('onboarding.index');
    Route::post('/demarrage/masquer', [OnboardingController::class, 'dismiss'])
        ->middleware('permission:dashboard.view')
        ->name('onboarding.dismiss');
    Route::post('/demarrage/reactiver', [OnboardingController::class, 'reopen'])
        ->middleware('permission:dashboard.view')
        ->name('onboarding.reopen');
    Route::post('/demarrage/starter-secteur', [OnboardingController::class, 'applySectorStarter'])
        ->middleware('permission:settings.manage')
        ->name('onboarding.sector-starter.apply');
    Route::post('/demarrage/demo-secteur', [OnboardingController::class, 'applySectorDemoData'])
        ->middleware('permission:settings.manage')
        ->name('onboarding.sector-demo.apply');

    Route::get('/approbations', [ApprovalPortalController::class, 'index'])
        ->middleware('permission:approvals.view')
        ->name('approvals.index');
    Route::post('/approbations/etapes/{approvalStep}/deleguer', [ApprovalStepController::class, 'delegate'])
        ->middleware('permission:approvals.view')
        ->name('approvals.steps.delegate');

    Route::get('/alertes', [NotificationController::class, 'index'])
        ->middleware('permission:notifications.view')
        ->name('notifications.index');
    Route::get('/automatisations', [AutomationController::class, 'index'])
        ->middleware('permission:automation.view')
        ->name('automation.index');
    Route::post('/automatisations', [AutomationController::class, 'store'])
        ->middleware('permission:automation.manage')
        ->name('automation.store');
    Route::put('/automatisations/{automationRule}', [AutomationController::class, 'update'])
        ->middleware('permission:automation.manage')
        ->name('automation.update');
    Route::post('/automatisations/executer', [AutomationController::class, 'runAll'])
        ->middleware('permission:automation.manage')
        ->name('automation.run-all');
    Route::post('/automatisations/{automationRule}/executer', [AutomationController::class, 'run'])
        ->middleware('permission:automation.manage')
        ->name('automation.run');
    Route::get('/notifications-sortantes', [OutboundNotificationController::class, 'index'])
        ->middleware('permission:notifications.outbound.view')
        ->name('notifications.outbound.index');
    Route::post('/notifications-sortantes/traiter', [OutboundNotificationController::class, 'process'])
        ->middleware('permission:settings.manage')
        ->name('notifications.outbound.process');
    Route::post('/notifications-sortantes/relancer-echecs', [OutboundNotificationController::class, 'retryFailed'])
        ->middleware('permission:settings.manage')
        ->name('notifications.outbound.retry-failed');
    Route::get('/operations/sante', [OperationsController::class, 'index'])
        ->middleware('permission:ops.view')
        ->name('ops.index');
    Route::post('/operations/outbox/traiter', [OperationsController::class, 'processOutbox'])
        ->middleware('permission:ops.view')
        ->name('ops.outbox.process');
    Route::post('/operations/outbox/{integrationEvent}/relancer', [OperationsController::class, 'retryOutboxEvent'])
        ->middleware('permission:ops.view')
        ->name('ops.outbox.retry');
    Route::post('/operations/outbox/relancer-echecs', [OperationsController::class, 'retryFailedOutbox'])
        ->middleware('permission:ops.view')
        ->name('ops.outbox.retry-failed');
    Route::post('/operations/email/test', [OperationsController::class, 'sendTestEmail'])
        ->middleware('permission:ops.view')
        ->name('ops.mail-test');
    Route::post('/alertes/lire-tout', [NotificationController::class, 'readAll'])
        ->middleware('permission:notifications.view')
        ->name('notifications.read-all');
    Route::post('/alertes/{notification}/lire', [NotificationController::class, 'read'])
        ->middleware('permission:notifications.view')
        ->name('notifications.read');

    Route::post('/documents/commentaires', [DocumentCollaborationController::class, 'storeComment'])->name('documents.comments.store');
    Route::post('/documents/pieces-jointes', [DocumentCollaborationController::class, 'storeAttachment'])->name('documents.attachments.store');
    Route::get('/documents/pieces-jointes/{attachment}', [DocumentCollaborationController::class, 'showAttachment'])->name('documents.attachments.show');

    Route::get('/entreprises', [CompanyController::class, 'index'])->middleware('permission:companies.view')->name('companies.index');
    Route::get('/entreprises/creer', [CompanyController::class, 'create'])->middleware('permission:companies.manage')->name('companies.create');
    Route::post('/entreprises', [CompanyController::class, 'store'])->middleware('permission:companies.manage')->name('companies.store');
    Route::get('/entreprises/{company}/modifier', [CompanyController::class, 'edit'])->middleware('permission:companies.manage')->name('companies.edit');
    Route::put('/entreprises/{company}', [CompanyController::class, 'update'])->middleware('permission:companies.manage')->name('companies.update');

    Route::get('/agences', [BranchController::class, 'index'])->middleware('permission:branches.view')->name('branches.index');
    Route::get('/agences/creer', [BranchController::class, 'create'])->middleware('permission:branches.manage')->name('branches.create');
    Route::post('/agences', [BranchController::class, 'store'])->middleware('permission:branches.manage')->name('branches.store');
    Route::get('/agences/{branch}/modifier', [BranchController::class, 'edit'])->middleware('permission:branches.manage')->name('branches.edit');
    Route::put('/agences/{branch}', [BranchController::class, 'update'])->middleware('permission:branches.manage')->name('branches.update');
    Route::post('/agences/{branch}/activer', [BranchController::class, 'switch'])->middleware('permission:branches.view')->name('branches.switch');

    Route::get('/utilisateurs', [UserController::class, 'index'])->middleware('permission:users.view')->name('users.index');
    Route::get('/utilisateurs/creer', [UserController::class, 'create'])->middleware('permission:users.manage')->name('users.create');
    Route::post('/utilisateurs', [UserController::class, 'store'])->middleware('permission:users.manage')->name('users.store');
    Route::get('/utilisateurs/{user}/modifier', [UserController::class, 'edit'])->middleware('permission:users.manage')->name('users.edit');
    Route::put('/utilisateurs/{user}', [UserController::class, 'update'])->middleware('permission:users.manage')->name('users.update');

    Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('roles.index');
    Route::get('/roles/creer', [RoleController::class, 'create'])->middleware('permission:roles.manage')->name('roles.create');
    Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:roles.manage')->name('roles.store');
    Route::get('/roles/{role}/modifier', [RoleController::class, 'edit'])->middleware('permission:roles.manage')->name('roles.edit');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.manage')->name('roles.update');

    Route::get('/parametres', [SettingsController::class, 'index'])->middleware('permission:settings.view')->name('settings.index');
    Route::put('/parametres/societe', [SettingsController::class, 'updateCompany'])->middleware('permission:settings.manage')->name('settings.company.update');
    Route::put('/parametres/profil-secteur', [SettingsController::class, 'updateSectorProfile'])->middleware('permission:settings.manage')->name('settings.sector-profile.update');
    Route::put('/parametres/sequences', [SettingsController::class, 'updateSequences'])->middleware('permission:settings.manage')->name('settings.sequences.update');
    Route::put('/parametres/approbations', [SettingsController::class, 'updateApprovalWorkflows'])->middleware('permission:settings.manage')->name('settings.approvals.update');
    Route::put('/parametres/notifications-approbations', [SettingsController::class, 'updateApprovalNotifications'])->middleware('permission:settings.manage')->name('settings.approval-notifications.update');
    Route::put('/parametres/integrations/webhook', [SettingsController::class, 'updateIntegrationWebhook'])->middleware('permission:settings.integrations.manage')->name('settings.integrations.webhook.update');
    Route::put('/parametres/passerelles-paiement', [SettingsController::class, 'updatePaymentGateways'])->middleware('permission:settings.integrations.manage')->name('settings.payment-gateways.update');
    Route::post('/parametres/conditions-paiement', [SettingsController::class, 'storePaymentTerm'])->middleware('permission:settings.manage')->name('settings.payment-terms.store');
    Route::post('/parametres/listes-prix', [SettingsController::class, 'storePriceList'])->middleware('permission:settings.manage')->name('settings.price-lists.store');
    Route::post('/parametres/listes-prix/lignes', [SettingsController::class, 'storePriceListItem'])->middleware('permission:settings.manage')->name('settings.price-list-items.store');
    Route::post('/parametres/regles-fiscales', [SettingsController::class, 'storeTaxRule'])->middleware('permission:settings.manage')->name('settings.tax-rules.store');
    Route::post('/parametres/api/tokens', [SettingsController::class, 'createApiToken'])->middleware('permission:settings.integrations.manage')->name('settings.api-tokens.store');
    Route::delete('/parametres/api/tokens/{apiToken}', [SettingsController::class, 'revokeApiToken'])->middleware('permission:settings.integrations.manage')->name('settings.api-tokens.destroy');

    Route::get('/journaux-activite', [ActivityLogController::class, 'index'])
        ->middleware('permission:activity_logs.view')
        ->name('activity-logs.index');
});


