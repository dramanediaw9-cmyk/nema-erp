<?php

namespace App\Modules\Core\Platform\Services;

class PlatformOpenApiService
{
    public function spec(?int $companyId = null): array
    {
        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => config('app.name', 'Nema ERP').' Integrator API',
                'version' => '1.0.0',
                'description' => 'Contrat API v1 pour integrateurs, middleware, BI et partenaires operant autour de Nema ERP.',
            ],
            'servers' => [
                [
                    'url' => rtrim((string) url('/api/v1'), '/'),
                    'description' => 'Base URL v1',
                ],
                [
                    'url' => '/api/v1',
                    'description' => 'Chemin relatif',
                ],
            ],
            'security' => [
                ['BearerAuth' => []],
                ['ApiKeyAuth' => []],
            ],
            'tags' => [
                ['name' => 'workspace', 'description' => 'Contexte du tenant et de la societe rattaches au jeton.'],
                ['name' => 'platform', 'description' => 'Capacites produit, connecteurs et contrat integrateur.'],
                ['name' => 'catalog', 'description' => 'Catalogue produit, tiers, factures et reglements.'],
                ['name' => 'hr', 'description' => 'Capital humain, employes et conges.'],
                ['name' => 'payroll', 'description' => 'Executions de paie et bulletins.'],
                ['name' => 'projects', 'description' => 'Projets, jalons et execution.'],
                ['name' => 'manufacturing', 'description' => 'Nomenclatures et ordres de production.'],
                ['name' => 'commerce', 'description' => 'Canaux, snapshots et backlog omnicanal.'],
                ['name' => 'ops', 'description' => 'Flux d integration et lecture de sante.'],
            ],
            'paths' => $this->paths(),
            'components' => [
                'securitySchemes' => [
                    'BearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'API token',
                    ],
                    'ApiKeyAuth' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-Api-Key',
                    ],
                ],
                'schemas' => [
                    'ErrorResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'message' => ['type' => 'string', 'example' => 'Jeton API manquant.'],
                        ],
                    ],
                    'PlatformConnection' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer', 'example' => 12],
                            'code' => ['type' => 'string', 'example' => 'INT-0012'],
                            'name' => ['type' => 'string', 'example' => 'Connecteur data warehouse'],
                            'partner_name' => ['type' => 'string', 'example' => 'Fabric Lakehouse'],
                            'connection_type' => ['type' => 'string', 'example' => 'bi'],
                            'sync_mode' => ['type' => 'string', 'example' => 'outbound'],
                            'status' => ['type' => 'string', 'example' => 'active'],
                            'health_status' => ['type' => 'string', 'example' => 'healthy'],
                            'external_reference' => ['type' => 'string', 'nullable' => true, 'example' => 'fabric-warehouse-nema'],
                            'scope_summary' => ['type' => 'string', 'nullable' => true, 'example' => 'Ventes, achats, marges et projections stock.'],
                            'last_sync_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                            'last_health_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                        ],
                    ],
                ],
            ],
            'x-nema' => [
                'company_id' => $companyId,
                'generated_at' => now()->toIso8601String(),
                'support_commands' => [
                    'php artisan test',
                    'php artisan nema:ops:monitor-app',
                    'php artisan nema:integrations:dispatch-outbox --limit=50',
                ],
            ],
        ];
    }

    private function paths(): array
    {
        return [
            '/workspace' => [
                'get' => $this->operation('workspace', 'Lire le contexte workspace', 'Retourne le tenant, la societe et l agence lies au jeton API.'),
            ],
            '/platform/capabilities' => [
                'get' => $this->operation('platform', 'Lire le catalogue de capacites', 'Expose les modules, le packaging, les metriques et le hub partenaires.'),
            ],
            '/platform/openapi' => [
                'get' => $this->operation('platform', 'Lire le contrat OpenAPI', 'Retourne le contrat integrateur OpenAPI JSON de l API v1.'),
            ],
            '/platform/connections' => [
                'get' => $this->operation(
                    'platform',
                    'Lister les connexions partenaires',
                    'Filtre les connecteurs par statut, sante, type ou recherche texte.',
                    [
                        $this->queryParameter('status', 'Statut de la connexion', ['draft', 'active', 'paused', 'deprecated']),
                        $this->queryParameter('health_status', 'Etat de sante', ['healthy', 'watch', 'critical']),
                        $this->queryParameter('connection_type', 'Type de connexion', ['api', 'webhook', 'payment_gateway', 'marketplace', 'bi', 'logistics']),
                        $this->queryParameter('search', 'Recherche libre'),
                        $this->queryParameter('per_page', 'Taille de page', null, 'integer'),
                    ]
                ),
                'post' => $this->operation(
                    'platform',
                    'Creer une connexion partenaire',
                    'Enregistre un nouveau connecteur integrateur avec son perimetre et son responsable.',
                    [],
                    [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['name', 'partner_name', 'connection_type', 'sync_mode', 'status', 'health_status'],
                                    'properties' => [
                                        'code' => ['type' => 'string', 'example' => 'INT-0012'],
                                        'name' => ['type' => 'string', 'example' => 'Connecteur data warehouse'],
                                        'partner_name' => ['type' => 'string', 'example' => 'Fabric Lakehouse'],
                                        'connection_type' => ['type' => 'string', 'example' => 'bi'],
                                        'sync_mode' => ['type' => 'string', 'example' => 'outbound'],
                                        'status' => ['type' => 'string', 'example' => 'draft'],
                                        'health_status' => ['type' => 'string', 'example' => 'watch'],
                                        'scope_summary' => ['type' => 'string', 'example' => 'Ventes, achats, projections stock et marge.'],
                                        'external_reference' => ['type' => 'string', 'example' => 'fabric-warehouse-nema'],
                                    ],
                                ],
                            ],
                        ],
                    ]
                ),
            ],
            '/platform/connections/{integrationConnection}' => [
                'get' => $this->operation(
                    'platform',
                    'Lire une connexion partenaire',
                    'Retourne le detail d une connexion, avec son responsable et son agence.',
                    [$this->pathParameter('integrationConnection', 'integer', 'Identifiant de la connexion')]
                ),
            ],
            '/platform/connections/{integrationConnection}/status' => [
                'patch' => $this->operation(
                    'platform',
                    'Mettre a jour le statut de la connexion',
                    'Change le statut metier et l etat de sante du connecteur.',
                    [$this->pathParameter('integrationConnection', 'integer', 'Identifiant de la connexion')],
                    [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['status', 'health_status'],
                                    'properties' => [
                                        'status' => ['type' => 'string', 'example' => 'active'],
                                        'health_status' => ['type' => 'string', 'example' => 'healthy'],
                                    ],
                                ],
                            ],
                        ],
                    ]
                ),
            ],
            '/products' => [
                'get' => $this->operation('catalog', 'Lister les produits', 'Retourne le catalogue produit expose aux integrateurs.'),
            ],
            '/partners' => [
                'get' => $this->operation('catalog', 'Lister les tiers', 'Retourne les clients, fournisseurs et partenaires.'),
                'post' => $this->operation('catalog', 'Creer un tiers', 'Ajoute un client ou partenaire dans l ERP.'),
            ],
            '/partners/{partner}' => [
                'get' => $this->operation('catalog', 'Lire un tiers', 'Retourne la fiche d un tiers.', [$this->pathParameter('partner', 'integer', 'Identifiant du tiers')]),
                'patch' => $this->operation('catalog', 'Mettre a jour un tiers', 'Met a jour la fiche d un tiers.', [$this->pathParameter('partner', 'integer', 'Identifiant du tiers')]),
            ],
            '/sales-invoices' => [
                'get' => $this->operation('catalog', 'Lister les factures client', 'Retourne les factures de vente disponibles pour synchronisation.'),
                'post' => $this->operation('catalog', 'Creer une facture client', 'Injecte une facture de vente depuis un systeme tiers.'),
            ],
            '/sales-invoices/{salesInvoice}' => [
                'get' => $this->operation('catalog', 'Lire une facture client', 'Retourne le detail d une facture.', [$this->pathParameter('salesInvoice', 'integer', 'Identifiant de la facture')]),
            ],
            '/payments' => [
                'get' => $this->operation('catalog', 'Lister les reglements', 'Retourne les paiements disponibles.'),
                'post' => $this->operation('catalog', 'Creer un reglement', 'Ajoute un reglement ou un retour de paiement.'),
            ],
            '/payments/{payment}' => [
                'get' => $this->operation('catalog', 'Lire un reglement', 'Retourne le detail d un reglement.', [$this->pathParameter('payment', 'integer', 'Identifiant du reglement')]),
            ],
            '/hr/departments' => [
                'get' => $this->operation('hr', 'Lister les departements RH', 'Retourne les equipes et leurs responsables.'),
                'post' => $this->operation('hr', 'Creer un departement RH', 'Ajoute un departement au capital humain.'),
            ],
            '/hr/employees' => [
                'get' => $this->operation('hr', 'Lister les employes', 'Retourne les employes actifs et leur rattachement.'),
                'post' => $this->operation('hr', 'Creer un employe', 'Ajoute un employe et ses informations de paie.'),
            ],
            '/hr/leave-requests' => [
                'get' => $this->operation('hr', 'Lister les demandes de conge', 'Retourne les absences et demandes de conge.'),
                'post' => $this->operation('hr', 'Creer une demande de conge', 'Ajoute une demande de conge ou absence.'),
            ],
            '/payroll/runs' => [
                'get' => $this->operation('payroll', 'Lister les executions de paie', 'Retourne les campagnes de paie.'),
                'post' => $this->operation('payroll', 'Creer une execution de paie', 'Ajoute une execution de paie.'),
            ],
            '/payroll/slips' => [
                'get' => $this->operation('payroll', 'Lister les bulletins', 'Retourne les bulletins calcules.'),
                'post' => $this->operation('payroll', 'Creer un bulletin', 'Ajoute un bulletin ou un correctif de paie.'),
            ],
            '/projects' => [
                'get' => $this->operation('projects', 'Lister les projets', 'Retourne les projets, budgets et avancement.'),
                'post' => $this->operation('projects', 'Creer un projet', 'Ajoute un projet et sa cible d execution.'),
            ],
            '/projects/{project}' => [
                'get' => $this->operation('projects', 'Lire un projet', 'Retourne le detail d un projet.', [$this->pathParameter('project', 'integer', 'Identifiant du projet')]),
            ],
            '/projects/{project}/tasks' => [
                'post' => $this->operation('projects', 'Creer une tache projet', 'Ajoute une tache, un jalon ou un point de blocage.', [$this->pathParameter('project', 'integer', 'Identifiant du projet')]),
            ],
            '/projects/{project}/tasks/{projectTask}' => [
                'patch' => $this->operation(
                    'projects',
                    'Mettre a jour une tache projet',
                    'Fait evoluer l etat et la progression d une tache projet.',
                    [
                        $this->pathParameter('project', 'integer', 'Identifiant du projet'),
                        $this->pathParameter('projectTask', 'integer', 'Identifiant de la tache'),
                    ]
                ),
            ],
            '/manufacturing/boms' => [
                'get' => $this->operation('manufacturing', 'Lister les nomenclatures', 'Retourne les nomenclatures de production.'),
                'post' => $this->operation('manufacturing', 'Creer une nomenclature', 'Ajoute une nouvelle BOM.'),
            ],
            '/production-orders' => [
                'get' => $this->operation('manufacturing', 'Lister les ordres de production', 'Retourne les OF de production.'),
                'post' => $this->operation('manufacturing', 'Creer un ordre de production', 'Ajoute un OF ou un ordre pilote.'),
            ],
            '/commerce/channels' => [
                'get' => $this->operation('commerce', 'Lister les canaux commerce', 'Retourne les canaux retail, mobile, marketplace ou web.'),
                'post' => $this->operation('commerce', 'Creer un canal commerce', 'Ajoute un nouveau canal omnicanal.'),
            ],
            '/commerce/channels/{commerceChannel}' => [
                'get' => $this->operation('commerce', 'Lire un canal commerce', 'Retourne le detail d un canal et son execution.', [$this->pathParameter('commerceChannel', 'integer', 'Identifiant du canal')]),
            ],
            '/commerce/channels/{commerceChannel}/snapshots' => [
                'post' => $this->operation('commerce', 'Creer un snapshot de canal', 'Capture les KPI du canal a une date donnee.', [$this->pathParameter('commerceChannel', 'integer', 'Identifiant du canal')]),
            ],
            '/commerce/channels/{commerceChannel}/actions' => [
                'post' => $this->operation('commerce', 'Creer une action omnicanale', 'Ajoute une action corrective ou de croissance.', [$this->pathParameter('commerceChannel', 'integer', 'Identifiant du canal')]),
            ],
            '/commerce/channels/{commerceChannel}/actions/{commerceChannelAction}' => [
                'patch' => $this->operation(
                    'commerce',
                    'Mettre a jour une action omnicanale',
                    'Fait evoluer le backlog d actions commerce.',
                    [
                        $this->pathParameter('commerceChannel', 'integer', 'Identifiant du canal'),
                        $this->pathParameter('commerceChannelAction', 'integer', 'Identifiant de l action'),
                    ]
                ),
            ],
            '/accounting/localization' => [
                'get' => $this->operation('ops', 'Lire la localisation comptable', 'Expose les informations OHADA / SYSCOHADA disponibles.'),
            ],
            '/integration-events' => [
                'get' => $this->operation('ops', 'Lister les evenements d integration', 'Retourne les flux outbox publies, en attente ou en echec.'),
            ],
            '/integration-events/{integrationEvent}' => [
                'get' => $this->operation('ops', 'Lire un evenement d integration', 'Retourne le detail d un evenement outbox.', [$this->pathParameter('integrationEvent', 'integer', 'Identifiant de l evenement')]),
            ],
        ];
    }

    private function operation(
        string $tag,
        string $summary,
        string $description,
        array $parameters = [],
        ?array $requestBody = null,
    ): array {
        $operation = [
            'tags' => [$tag],
            'summary' => $summary,
            'description' => $description,
            'security' => [
                ['BearerAuth' => []],
                ['ApiKeyAuth' => []],
            ],
            'responses' => [
                '200' => [
                    'description' => 'Operation reussie',
                ],
                '401' => [
                    'description' => 'Jeton manquant, invalide ou expire',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                        ],
                    ],
                ],
                '403' => [
                    'description' => 'Permission insuffisante',
                ],
            ],
        ];

        if ($parameters !== []) {
            $operation['parameters'] = $parameters;
        }

        if ($requestBody !== null) {
            $operation['requestBody'] = $requestBody;
            $operation['responses']['201'] = ['description' => 'Ressource creee'];
        }

        return $operation;
    }

    private function queryParameter(string $name, string $description, ?array $enum = null, string $type = 'string'): array
    {
        $parameter = [
            'name' => $name,
            'in' => 'query',
            'required' => false,
            'schema' => ['type' => $type],
            'description' => $description,
        ];

        if ($enum !== null) {
            $parameter['schema']['enum'] = $enum;
        }

        return $parameter;
    }

    private function pathParameter(string $name, string $type, string $description): array
    {
        return [
            'name' => $name,
            'in' => 'path',
            'required' => true,
            'schema' => ['type' => $type],
            'description' => $description,
        ];
    }
}
