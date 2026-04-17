<?php

namespace App\Modules\Core\Platform\Services;

use App\Modules\Commerce\Models\CommerceChannel;
use App\Modules\Core\Automation\Models\AutomationExecution;
use App\Modules\Core\Automation\Models\AutomationRule;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Integrations\Models\IntegrationConnection;
use App\Modules\Core\Integrations\Models\ApiToken;
use App\Modules\Core\Integrations\Models\IntegrationEvent;
use App\Modules\Core\Integrations\Models\IntegrationInboundWebhook;
use App\Modules\Core\Integrations\Services\IntegrationSecretGovernanceService;
use App\Modules\Core\Platform\Models\DeploymentProfile;
use App\Modules\Hr\Models\HrDepartment;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrLeaveRequest;
use App\Modules\Manufacturing\Models\ManufacturingBom;
use App\Modules\Manufacturing\Models\ProductionOrder;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\PayrollSlip;
use App\Modules\Projects\Models\Project;

class PlatformCatalogService
{
    public function __construct(
        private readonly DeploymentProfileService $deploymentProfileService,
        private readonly DeploymentReadinessService $deploymentReadinessService,
        private readonly TenantReadinessService $tenantReadinessService,
        private readonly IntegrationSecretGovernanceService $integrationSecretGovernanceService,
    ) {
    }

    public function forCompany(?int $companyId): array
    {
        $company = $companyId ? Company::query()->with('tenant')->find($companyId) : null;
        $apiTokens = $companyId ? ApiToken::query()->with('creator')->where('company_id', $companyId)->orderByDesc('id')->limit(6)->get() : collect();
        $outboxEvents = $companyId ? IntegrationEvent::query()->with('latestDelivery')->where('company_id', $companyId)->latest('id')->limit(6)->get() : collect();
        $inboundWebhooks = $companyId ? IntegrationInboundWebhook::query()->where('company_id', $companyId)->latest('id')->limit(6)->get() : collect();
        $connections = $companyId ? IntegrationConnection::query()->with(['branch', 'owner', 'secretOwner'])->where('company_id', $companyId)->orderBy('partner_name')->orderBy('name')->get() : collect();
        $deploymentProfile = $company ? $this->deploymentProfileService->profileForCompany($company)->loadMissing('owner') : null;
        $deploymentLabels = $this->deploymentProfileService->labels();
        $readiness = ($company && $deploymentProfile) ? $this->deploymentReadinessService->summary($company, $deploymentProfile) : null;
        $tenantReadiness = $company ? $this->tenantReadinessService->summary($company) : null;
        $secretGovernance = $this->integrationSecretGovernanceService->summary($connections);

        return [
            'product' => [
                'name' => config('app.name', 'Nema ERP'),
                'edition' => 'Growth Foundation',
                'commercial_offer' => $deploymentProfile ? $this->deploymentProfileService->label('commercial_offer', $deploymentProfile->commercial_offer) : null,
                'currency' => 'XOF',
                'timezone' => config('app.timezone'),
            ],
            'packaging' => [
                'summary' => 'Socle de deploiement local, supervision, sauvegarde et controles qualite pour industrialiser le produit.',
                'start_command' => 'powershell -ExecutionPolicy Bypass -File .\\scripts\\start-nema-erp.ps1',
                'stop_command' => 'powershell -ExecutionPolicy Bypass -File .\\scripts\\stop-nema-erp.ps1',
                'quality_gates' => [
                    'php artisan test',
                    'npm run e2e:smoke',
                    'php artisan nema:ops:monitor-app',
                    'php artisan nema:core:pulse',
                ],
                'runbooks' => [
                    ['label' => 'README', 'path' => 'README.md', 'purpose' => 'Lancement, verification rapide et comptes de demonstration.'],
                    ['label' => 'Deploiement local', 'path' => 'docs/DEPLOIEMENT-LOCAL.md', 'purpose' => 'Checklist poste, scripts et prerequisites.'],
                    ['label' => 'Publication cloud', 'path' => 'docs/PUBLICATION-LARAVEL-CLOUD.md', 'purpose' => 'Passage vers une exploitation cloud plus stable.'],
                    ['label' => 'Sauvegarde et restauration', 'path' => 'docs/SAUVEGARDE-RESTAURATION.md', 'purpose' => 'RPO/RTO, restoration guidee et verification.'],
                    ['label' => 'Plateforme & ecosysteme', 'path' => 'docs/PLATEFORME-ECOSYSTEME.md', 'purpose' => 'Vision packaging, API, partenaires et modules d expansion.'],
                ],
                'deployment_profile' => $deploymentProfile ? [
                    'owner_id' => $deploymentProfile->owner_id,
                    'owner_name' => $deploymentProfile->owner?->name,
                    'commercial_offer' => $deploymentProfile->commercial_offer,
                    'commercial_offer_label' => $deploymentLabels['commercial_offer'][$deploymentProfile->commercial_offer] ?? $deploymentProfile->commercial_offer,
                    'deployment_mode' => $deploymentProfile->deployment_mode,
                    'deployment_mode_label' => $deploymentLabels['deployment_mode'][$deploymentProfile->deployment_mode] ?? $deploymentProfile->deployment_mode,
                    'lifecycle_stage' => $deploymentProfile->lifecycle_stage,
                    'lifecycle_stage_label' => $deploymentLabels['lifecycle_stage'][$deploymentProfile->lifecycle_stage] ?? $deploymentProfile->lifecycle_stage,
                    'hosting_target' => $deploymentProfile->hosting_target,
                    'hosting_target_label' => $deploymentLabels['hosting_target'][$deploymentProfile->hosting_target] ?? $deploymentProfile->hosting_target,
                    'support_tier' => $deploymentProfile->support_tier,
                    'support_tier_label' => $deploymentLabels['support_tier'][$deploymentProfile->support_tier] ?? $deploymentProfile->support_tier,
                    'monitoring_level' => $deploymentProfile->monitoring_level,
                    'monitoring_level_label' => $deploymentLabels['monitoring_level'][$deploymentProfile->monitoring_level] ?? $deploymentProfile->monitoring_level,
                    'backup_strategy' => $deploymentProfile->backup_strategy,
                    'backup_strategy_label' => $deploymentLabels['backup_strategy'][$deploymentProfile->backup_strategy] ?? $deploymentProfile->backup_strategy,
                    'update_channel' => $deploymentProfile->update_channel,
                    'update_channel_label' => $deploymentLabels['update_channel'][$deploymentProfile->update_channel] ?? $deploymentProfile->update_channel,
                    'target_users' => $deploymentProfile->target_users,
                    'target_branches' => $deploymentProfile->target_branches,
                    'go_live_target_at' => $deploymentProfile->go_live_target_at,
                    'last_release_at' => $deploymentProfile->last_release_at,
                    'last_backup_verified_at' => $deploymentProfile->last_backup_verified_at,
                    'last_restore_drill_at' => $deploymentProfile->last_restore_drill_at,
                    'notes' => $deploymentProfile->notes,
                ] : null,
                'readiness' => $readiness,
                'tenant_landscape' => $tenantReadiness ? [
                    'tenant_name' => $tenantReadiness['tenant_name'],
                    'active_companies' => $tenantReadiness['active_companies'],
                    'active_users' => $tenantReadiness['active_users'],
                    'active_branches' => Branch::query()->where('company_id', $company->id)->where('is_active', true)->count(),
                    'tenant_active_branches' => $tenantReadiness['active_branches'],
                    'portfolio_status' => $tenantReadiness['portfolio_status'],
                    'portfolio_status_label' => $tenantReadiness['portfolio_status_label'],
                    'average_score' => $tenantReadiness['average_score'],
                ] : null,
                'tenant_readiness' => $tenantReadiness,
            ],
            'ecosystem' => [
                'api' => [
                    'base_path' => '/api/v1',
                    'authentication' => ['Bearer token', 'X-Api-Key'],
                    'documentation' => [
                        'web_openapi_path' => '/plateforme/openapi.json',
                        'api_openapi_path' => '/api/v1/platform/openapi',
                        'curl_examples' => [
                            'curl -H "Authorization: Bearer {token}" '.rtrim((string) url('/api/v1/platform/capabilities'), '/'),
                            'curl -H "X-Api-Key: {token}" '.rtrim((string) url('/api/v1/platform/connections?status=active'), '/'),
                            'curl -H "Authorization: Bearer {token}" '.rtrim((string) url('/api/v1/platform/tenant-readiness'), '/'),
                            'curl -H "Authorization: Bearer {token}" '.rtrim((string) url('/api/v1/platform/openapi'), '/'),
                        ],
                    ],
                    'resources' => [
                        ['name' => 'workspace', 'path' => '/api/v1/workspace'],
                        ['name' => 'platform-deployment-profile', 'path' => '/api/v1/platform/deployment-profile'],
                        ['name' => 'platform-tenant-readiness', 'path' => '/api/v1/platform/tenant-readiness'],
                        ['name' => 'platform-core-pulse', 'path' => '/api/v1/platform/core-pulse'],
                        ['name' => 'platform-openapi', 'path' => '/api/v1/platform/openapi'],
                        ['name' => 'platform-connection-secrets', 'path' => '/api/v1/platform/connections/{integrationConnection}/secrets'],
                        ['name' => 'automation-rules', 'path' => '/api/v1/automation/rules'],
                        ['name' => 'products', 'path' => '/api/v1/products'],
                        ['name' => 'partners', 'path' => '/api/v1/partners'],
                        ['name' => 'sales-invoices', 'path' => '/api/v1/sales-invoices'],
                        ['name' => 'payments', 'path' => '/api/v1/payments'],
                        ['name' => 'hr-departments', 'path' => '/api/v1/hr/departments'],
                        ['name' => 'hr-employees', 'path' => '/api/v1/hr/employees'],
                        ['name' => 'hr-leave-requests', 'path' => '/api/v1/hr/leave-requests'],
                        ['name' => 'payroll-runs', 'path' => '/api/v1/payroll/runs'],
                        ['name' => 'payroll-slips', 'path' => '/api/v1/payroll/slips'],
                        ['name' => 'projects', 'path' => '/api/v1/projects'],
                        ['name' => 'manufacturing-boms', 'path' => '/api/v1/manufacturing/boms'],
                        ['name' => 'production-orders', 'path' => '/api/v1/production-orders'],
                        ['name' => 'commerce-channels', 'path' => '/api/v1/commerce/channels'],
                        ['name' => 'accounting-localization', 'path' => '/api/v1/accounting/localization'],
                        ['name' => 'integration-events', 'path' => '/api/v1/integration-events'],
                        ['name' => 'platform-capabilities', 'path' => '/api/v1/platform/capabilities'],
                    ],
                ],
                'token_hygiene' => [
                    'active' => $apiTokens->count(),
                    'expiring_soon' => $apiTokens->filter(fn (ApiToken $token) => $token->expires_at && $token->expires_at->lte(now()->addDays(7)))->count(),
                    'stale' => $apiTokens->filter(fn (ApiToken $token) => ! $token->last_used_at || $token->last_used_at->lt(now()->subDays(30)))->count(),
                    'recent_tokens' => $apiTokens->map(fn (ApiToken $token) => [
                        'name' => $token->name,
                        'created_by' => $token->creator?->name,
                        'last_used_at' => $token->last_used_at,
                        'expires_at' => $token->expires_at,
                    ])->all(),
                ],
                'connections' => [
                    'summary' => [
                        'total' => $connections->count(),
                        'active' => $connections->where('status', 'active')->count(),
                        'critical' => $connections->where('health_status', 'critical')->count(),
                        'bidirectional' => $connections->where('sync_mode', 'bidirectional')->count(),
                    ],
                    'secret_governance' => $secretGovernance,
                    'items' => $connections->map(fn (IntegrationConnection $connection) => [
                        'id' => $connection->id,
                        'code' => $connection->code,
                        'name' => $connection->name,
                        'partner_name' => $connection->partner_name,
                        'connection_type' => $connection->connection_type,
                        'sync_mode' => $connection->sync_mode,
                        'status' => $connection->status,
                        'health_status' => $connection->health_status,
                        'external_reference' => $connection->external_reference,
                        'last_sync_at' => $connection->last_sync_at,
                        'last_health_at' => $connection->last_health_at,
                        'authentication_mode' => $connection->authentication_mode,
                        'secret_health_status' => $connection->secret_health_status,
                        'secret_profile' => $this->integrationSecretGovernanceService->profile($connection),
                        'scope_summary' => $connection->scope_summary,
                        'notes' => $connection->notes,
                        'branch_name' => $connection->branch?->name,
                        'owner_name' => $connection->owner?->name,
                        'secret_owner_id' => $connection->secret_owner_id,
                        'secret_owner_name' => $connection->secretOwner?->name,
                    ])->all(),
                ],
                'monitoring' => [
                    'outbox_pending' => $companyId ? IntegrationEvent::query()->where('company_id', $companyId)->where('status', 'pending')->count() : 0,
                    'outbox_failed' => $companyId ? IntegrationEvent::query()->where('company_id', $companyId)->where('status', 'failed')->count() : 0,
                    'inbound_accepted' => $companyId ? IntegrationInboundWebhook::query()->where('company_id', $companyId)->where('status', 'accepted')->count() : 0,
                    'inbound_rejected' => $companyId ? IntegrationInboundWebhook::query()->where('company_id', $companyId)->where('status', 'rejected')->count() : 0,
                    'recent_outbox' => $outboxEvents->map(fn (IntegrationEvent $event) => [
                        'event_name' => $event->event_name,
                        'status' => $event->status,
                        'attempts' => $event->attempts,
                        'last_error' => $event->last_error,
                        'latest_delivery_status' => $event->latestDelivery?->status,
                        'latest_delivery_target' => $event->latestDelivery?->target_url,
                    ])->all(),
                    'recent_inbound' => $inboundWebhooks->map(fn (IntegrationInboundWebhook $webhook) => [
                        'source' => $webhook->source,
                        'event_name' => $webhook->event_name,
                        'status' => $webhook->status,
                        'processed_at' => $webhook->processed_at,
                        'error_message' => $webhook->error_message,
                    ])->all(),
                ],
                'automation' => [
                    'rule_count' => $companyId ? AutomationRule::query()->where('company_id', $companyId)->count() : 0,
                    'matched_last_24h' => $companyId ? AutomationExecution::query()->where('company_id', $companyId)->where('matched', true)->where('executed_at', '>=', now()->subDay())->count() : 0,
                    'outbox_commands' => [
                        'php artisan nema:automation:run',
                        'php artisan nema:integrations:dispatch-outbox --limit=50',
                        'php artisan nema:ops:outbox-retry-failed --limit=50',
                        'php artisan nema:ops:outbox-prune --days=30',
                        'php artisan nema:ops:backup-offsite-verify',
                        'php artisan nema:core:pulse',
                    ],
                    'partner_channels' => [
                        'Connecteurs ERP et BI via API token',
                        'Webhook outbox vers middleware ou SI tiers',
                        'Portails clients devis, commandes et reglements',
                        'Automatisations noyau avec regles, cooldown et alertes transverses',
                    ],
                ],
            ],
            'modules' => [
                [
                    'key' => 'automation',
                    'label' => 'Automatisations',
                    'route_name' => 'automation.index',
                    'path' => '/automatisations',
                    'description' => 'Regles noyau, signaux transverses et executions automatiques.',
                    'count' => $companyId ? AutomationRule::query()->where('company_id', $companyId)->count() : 0,
                ],
                [
                    'key' => 'platform',
                    'label' => 'Plateforme',
                    'route_name' => 'platform.index',
                    'path' => '/plateforme',
                    'description' => 'Packaging, exploitation, API et readiness produit.',
                    'count' => 1,
                ],
                [
                    'key' => 'hr',
                    'label' => 'Capital humain',
                    'route_name' => 'hr.index',
                    'path' => '/capital-humain',
                    'description' => 'Departements, employes, contrats, conges et couverture operationnelle.',
                    'count' => $companyId ? HrEmployee::query()->where('company_id', $companyId)->count() : 0,
                ],
                [
                    'key' => 'payroll',
                    'label' => 'Paie',
                    'route_name' => 'payroll.index',
                    'path' => '/paie',
                    'description' => 'Executions de paie, bulletins, lignes de paie et preparation du paiement.',
                    'count' => $companyId ? PayrollRun::query()->where('company_id', $companyId)->count() : 0,
                ],
                [
                    'key' => 'projects',
                    'label' => 'Projets',
                    'route_name' => 'projects.index',
                    'path' => '/projets',
                    'description' => 'Pilotage execution, proprietaires, budget et avancement.',
                    'count' => $companyId ? Project::query()->where('company_id', $companyId)->count() : 0,
                ],
                [
                    'key' => 'manufacturing',
                    'label' => 'Production',
                    'route_name' => 'manufacturing.index',
                    'path' => '/production',
                    'description' => 'Ordres de fabrication, nomenclatures, quantites et couts matieres.',
                    'count' => $companyId ? ProductionOrder::query()->where('company_id', $companyId)->count() : 0,
                ],
                [
                    'key' => 'commerce',
                    'label' => 'Commerce unifie',
                    'route_name' => 'commerce.index',
                    'path' => '/commerce-unifie',
                    'description' => 'Canaux web, retail, marketplace et mobile money.',
                    'count' => $companyId ? CommerceChannel::query()->where('company_id', $companyId)->count() : 0,
                ],
            ],
            'metrics' => [
                'api_tokens' => $companyId ? ApiToken::query()->where('company_id', $companyId)->count() : 0,
                'outbox_pending' => $companyId ? IntegrationEvent::query()->where('company_id', $companyId)->where('status', 'pending')->count() : 0,
                'outbox_failed' => $companyId ? IntegrationEvent::query()->where('company_id', $companyId)->where('status', 'failed')->count() : 0,
                'integration_connections' => $companyId ? IntegrationConnection::query()->where('company_id', $companyId)->count() : 0,
                'connection_secrets_critical' => $secretGovernance['critical'],
                'automation_rules' => $companyId ? AutomationRule::query()->where('company_id', $companyId)->count() : 0,
                'automation_matches_24h' => $companyId ? AutomationExecution::query()->where('company_id', $companyId)->where('matched', true)->where('executed_at', '>=', now()->subDay())->count() : 0,
                'deployment_profiles' => $companyId ? DeploymentProfile::query()->where('company_id', $companyId)->count() : 0,
                'readiness_score' => $readiness['score'] ?? 0,
                'tenant_active_companies' => $tenantReadiness['active_companies'] ?? 0,
                'tenant_average_readiness' => $tenantReadiness['average_score'] ?? 0,
                'inbound_webhooks' => $companyId ? IntegrationInboundWebhook::query()->where('company_id', $companyId)->count() : 0,
                'departments' => $companyId ? HrDepartment::query()->where('company_id', $companyId)->count() : 0,
                'leave_requests' => $companyId ? HrLeaveRequest::query()->where('company_id', $companyId)->count() : 0,
                'payroll_slips' => $companyId ? PayrollSlip::query()->where('company_id', $companyId)->count() : 0,
                'manufacturing_boms' => $companyId ? ManufacturingBom::query()->where('company_id', $companyId)->count() : 0,
            ],
        ];
    }
}
