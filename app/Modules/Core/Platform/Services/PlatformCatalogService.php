<?php

namespace App\Modules\Core\Platform\Services;

use App\Modules\Commerce\Models\CommerceChannel;
use App\Modules\Core\Integrations\Models\ApiToken;
use App\Modules\Core\Integrations\Models\IntegrationEvent;
use App\Modules\Hr\Models\HrDepartment;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Manufacturing\Models\ProductionOrder;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Projects\Models\Project;

class PlatformCatalogService
{
    public function forCompany(?int $companyId): array
    {
        return [
            'product' => [
                'name' => config('app.name', 'Nema ERP'),
                'edition' => 'Growth Foundation',
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
                ],
                'runbooks' => [
                    ['label' => 'README', 'path' => 'README.md', 'purpose' => 'Lancement, verification rapide et comptes de demonstration.'],
                    ['label' => 'Deploiement local', 'path' => 'docs/DEPLOIEMENT-LOCAL.md', 'purpose' => 'Checklist poste, scripts et prerequisites.'],
                    ['label' => 'Publication cloud', 'path' => 'docs/PUBLICATION-LARAVEL-CLOUD.md', 'purpose' => 'Passage vers une exploitation cloud plus stable.'],
                    ['label' => 'Sauvegarde et restauration', 'path' => 'docs/SAUVEGARDE-RESTAURATION.md', 'purpose' => 'RPO/RTO, restoration guidee et verification.'],
                    ['label' => 'Plateforme & ecosysteme', 'path' => 'docs/PLATEFORME-ECOSYSTEME.md', 'purpose' => 'Vision packaging, API, partenaires et modules d expansion.'],
                ],
            ],
            'ecosystem' => [
                'api' => [
                    'base_path' => '/api/v1',
                    'authentication' => ['Bearer token', 'X-Api-Key'],
                    'resources' => [
                        ['name' => 'workspace', 'path' => '/api/v1/workspace'],
                        ['name' => 'products', 'path' => '/api/v1/products'],
                        ['name' => 'partners', 'path' => '/api/v1/partners'],
                        ['name' => 'sales-invoices', 'path' => '/api/v1/sales-invoices'],
                        ['name' => 'payments', 'path' => '/api/v1/payments'],
                        ['name' => 'hr-departments', 'path' => '/api/v1/hr/departments'],
                        ['name' => 'hr-employees', 'path' => '/api/v1/hr/employees'],
                        ['name' => 'payroll-runs', 'path' => '/api/v1/payroll/runs'],
                        ['name' => 'projects', 'path' => '/api/v1/projects'],
                        ['name' => 'production-orders', 'path' => '/api/v1/production-orders'],
                        ['name' => 'commerce-channels', 'path' => '/api/v1/commerce/channels'],
                        ['name' => 'integration-events', 'path' => '/api/v1/integration-events'],
                        ['name' => 'platform-capabilities', 'path' => '/api/v1/platform/capabilities'],
                    ],
                ],
                'automation' => [
                    'outbox_commands' => [
                        'php artisan nema:integrations:dispatch-outbox --limit=50',
                        'php artisan nema:ops:outbox-retry-failed --limit=50',
                        'php artisan nema:ops:outbox-prune --days=30',
                    ],
                    'partner_channels' => [
                        'Connecteurs ERP et BI via API token',
                        'Webhook outbox vers middleware ou SI tiers',
                        'Portails clients devis, commandes et reglements',
                    ],
                ],
            ],
            'modules' => [
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
                    'description' => 'Departements, employes, contrats et cycles de paie.',
                    'count' => $companyId ? HrEmployee::query()->where('company_id', $companyId)->count() : 0,
                ],
                [
                    'key' => 'payroll',
                    'label' => 'Paie',
                    'route_name' => 'payroll.index',
                    'path' => '/paie',
                    'description' => 'Executions de paie, calendrier et masses salariales.',
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
                    'description' => 'Ordres de fabrication, quantites et jalons atelier.',
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
                'departments' => $companyId ? HrDepartment::query()->where('company_id', $companyId)->count() : 0,
            ],
        ];
    }
}
