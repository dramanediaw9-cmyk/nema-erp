<?php

namespace App\Modules\Core\Onboarding\Services;

use App\Modules\Catalog\Models\ProductCategory;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Company\Models\PaymentTerm;
use App\Modules\Core\Company\Models\PriceList;
use App\Modules\Core\Company\Models\Setting;
use App\Modules\Core\Company\Services\SectorProfileService;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\Treasury\Services\PaymentGatewayService;
use Illuminate\Support\Str;

class SectorStarterService
{
    public function __construct(
        private readonly SectorProfileService $sectorProfileService,
        private readonly PaymentGatewayService $paymentGatewayService,
    ) {}

    public function status(int $companyId): array
    {
        $profile = $this->sectorProfileService->profileForCompany($companyId);
        $setting = Setting::query()->where('company_id', $companyId)->where('key', 'sector_onboarding')->first();
        $payload = is_array($setting?->value) ? $setting->value : [];
        $recommendedPayments = $profile['starter']['payments'] ?? [];
        $recommendedUnits = $profile['starter']['units'] ?? [];
        $gateways = $this->paymentGatewayService->configurationForCompany($companyId);
        $recommendedGatewayKeys = $this->supportedGatewayKeys($recommendedPayments);
        $readyGatewayCount = collect($recommendedGatewayKeys)
            ->filter(fn (string $key) => (bool) ($gateways[$key]['enabled'] ?? false))
            ->count();

        return [
            'is_applied' => filled($payload['applied_at'] ?? null)
                && $this->sectorProfileService->canonicalKey($payload['profile'] ?? null) === $profile['key'],
            'applied_at' => $payload['applied_at'] ?? null,
            'applied_profile' => $payload['profile'] ?? null,
            'categories_count' => ProductCategory::query()->where('company_id', $companyId)->count(),
            'expense_categories_count' => ExpenseCategory::query()->where('company_id', $companyId)->count(),
            'payment_terms_count' => PaymentTerm::query()->where('company_id', $companyId)->count(),
            'price_lists_count' => PriceList::query()->where('company_id', $companyId)->count(),
            'recommended_gateways_count' => count($recommendedGatewayKeys),
            'recommended_gateways_ready' => $readyGatewayCount,
            'units' => is_array($payload['units'] ?? null) ? $payload['units'] : $recommendedUnits,
        ];
    }

    public function apply(Company $company): array
    {
        $profile = $this->sectorProfileService->profileForCompany($company->id);
        $blueprint = $this->blueprint($profile['key'], $profile);

        $created = [
            'product_categories' => 0,
            'expense_categories' => 0,
            'payment_terms' => 0,
            'price_lists' => 0,
        ];

        foreach ($blueprint['product_categories'] as $categoryData) {
            $category = ProductCategory::query()->updateOrCreate(
                ['company_id' => $company->id, 'name' => $categoryData['name']],
                [
                    'tenant_id' => $company->tenant_id,
                    'description' => $categoryData['description'],
                    'is_active' => true,
                ]
            );

            $created['product_categories'] += $category->wasRecentlyCreated ? 1 : 0;
        }

        foreach ($blueprint['expense_categories'] as $expenseData) {
            $category = ExpenseCategory::query()->updateOrCreate(
                ['company_id' => $company->id, 'name' => $expenseData['name']],
                [
                    'description' => $expenseData['description'],
                    'default_account_code' => $expenseData['default_account_code'],
                    'is_active' => true,
                ]
            );

            $created['expense_categories'] += $category->wasRecentlyCreated ? 1 : 0;
        }

        $created['payment_terms'] = $this->syncPaymentTerms($company, $blueprint['payment_terms']);
        $created['price_lists'] = $this->syncPriceLists($company, $blueprint['price_lists']);
        $configuredGateways = $this->syncPaymentGateways($company, $profile['starter']['payments'] ?? []);

        Setting::query()->updateOrCreate(
            ['company_id' => $company->id, 'key' => 'sector_units'],
            [
                'tenant_id' => $company->tenant_id,
                'value' => [
                    'profile' => $profile['key'],
                    'units' => $profile['starter']['units'] ?? [],
                ],
            ]
        );

        Setting::query()->updateOrCreate(
            ['company_id' => $company->id, 'key' => 'sector_onboarding'],
            [
                'tenant_id' => $company->tenant_id,
                'value' => [
                    'applied_at' => now()->toDateTimeString(),
                    'profile' => $profile['key'],
                    'profile_label' => $profile['label'],
                    'units' => $profile['starter']['units'] ?? [],
                    'icon' => $profile['icon'] ?? null,
                    'modules' => $profile['recommended_modules'] ?? [],
                    'specific_fields' => $profile['specific_fields'] ?? [],
                    'workflows' => $profile['workflows'] ?? [],
                    'kpis' => $profile['kpis'] ?? [],
                    'alerts' => $profile['alerts'] ?? [],
                    'documents' => $profile['documents'] ?? [],
                    'starter' => $profile['starter'] ?? [],
                    'product_categories' => collect($blueprint['product_categories'])->pluck('name')->all(),
                    'expense_categories' => collect($blueprint['expense_categories'])->pluck('name')->all(),
                    'payment_terms' => collect($blueprint['payment_terms'])->pluck('name')->all(),
                    'price_lists' => collect($blueprint['price_lists'])->pluck('name')->all(),
                    'configured_gateways' => $configuredGateways,
                ],
            ]
        );

        return [
            'profile' => $profile,
            'created' => $created,
            'configured_gateways' => $configuredGateways,
            'status' => $this->status($company->id),
        ];
    }

    private function syncPaymentTerms(Company $company, array $terms): int
    {
        $created = 0;
        $hasDefault = PaymentTerm::query()->where('company_id', $company->id)->where('is_default', true)->exists();

        foreach ($terms as $index => $termData) {
            $term = PaymentTerm::query()->firstOrNew([
                'company_id' => $company->id,
                'code' => $termData['code'],
            ]);

            $shouldBeDefault = (bool) ($termData['is_default'] ?? false) && (! $hasDefault || $term->exists && $term->is_default);

            if ($shouldBeDefault) {
                PaymentTerm::query()->where('company_id', $company->id)->update(['is_default' => false]);
                $hasDefault = true;
            }

            $term->fill([
                'tenant_id' => $company->tenant_id,
                'name' => $termData['name'],
                'days' => $termData['days'],
                'description' => $termData['description'],
                'is_default' => $shouldBeDefault,
                'is_active' => true,
            ])->save();

            $created += $term->wasRecentlyCreated ? 1 : 0;
        }

        return $created;
    }

    private function syncPriceLists(Company $company, array $priceLists): int
    {
        $created = 0;
        $hasDefault = PriceList::query()->where('company_id', $company->id)->where('is_default', true)->exists();

        foreach ($priceLists as $priceListData) {
            $priceList = PriceList::query()->firstOrNew([
                'company_id' => $company->id,
                'code' => $priceListData['code'],
            ]);

            $shouldBeDefault = (bool) ($priceListData['is_default'] ?? false) && (! $hasDefault || $priceList->exists && $priceList->is_default);

            if ($shouldBeDefault) {
                PriceList::query()->where('company_id', $company->id)->update(['is_default' => false]);
                $hasDefault = true;
            }

            $priceList->fill([
                'tenant_id' => $company->tenant_id,
                'name' => $priceListData['name'],
                'currency_code' => $company->currency_code ?: 'XOF',
                'description' => $priceListData['description'],
                'is_default' => $shouldBeDefault,
                'is_active' => true,
            ])->save();

            $created += $priceList->wasRecentlyCreated ? 1 : 0;
        }

        return $created;
    }

    private function syncPaymentGateways(Company $company, array $recommendedPayments): array
    {
        $configuration = $this->paymentGatewayService->configurationForCompany($company->id);
        $enabled = [];

        foreach ($this->supportedGatewayKeys($recommendedPayments) as $method) {
            $channel = $configuration[$method] ?? null;

            if (! is_array($channel)) {
                continue;
            }

            $configuration[$method] = [
                'label' => $channel['label'] ?: Str::headline(str_replace('_', ' ', $method)),
                'enabled' => true,
                'account_name' => $channel['account_name'] ?: $company->name,
                'collection_number' => $channel['collection_number'] ?: $this->fallbackCollectionTarget($company, $method),
                'instructions' => $channel['instructions'] ?: $this->gatewayInstruction($method),
            ];

            $enabled[] = $method;
        }

        $this->paymentGatewayService->updateConfiguration($company->id, $company->tenant_id, $configuration);

        return $enabled;
    }

    private function supportedGatewayKeys(array $recommendedPayments): array
    {
        $aliases = [
            'wave' => 'wave',
            'orange_money' => 'orange_money',
            'orange money' => 'orange_money',
            'moov_money' => 'moov_money',
            'moov money' => 'moov_money',
            'bank_transfer' => 'bank_transfer',
            'virement' => 'bank_transfer',
            'virement bancaire' => 'bank_transfer',
        ];

        return collect($recommendedPayments)
            ->map(function (string $method) use ($aliases): ?string {
                $normalized = Str::of($method)
                    ->lower()
                    ->replace('_', ' ')
                    ->squish()
                    ->value();

                return $aliases[$normalized] ?? null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function fallbackCollectionTarget(Company $company, string $method): string
    {
        if ($method === 'bank_transfer') {
            return trim((string) ($company->email ?: $company->phone ?: ''));
        }

        return trim((string) ($company->phone ?: ''));
    }

    private function gatewayInstruction(string $method): string
    {
        return match ($method) {
            'wave', 'orange_money', 'moov_money' => 'Utilise la reference facture comme motif ou commentaire puis partage le numero de transaction.',
            'bank_transfer' => 'Indique la reference facture dans l ordre de virement avant de confirmer le paiement.',
            default => 'Communique toujours la reference facture lors du paiement.',
        };
    }

    private function blueprint(string $profileKey, array $profile = []): array
    {
        return match ($profileKey) {
            'general_trade' => [
                'product_categories' => [
                    ['name' => 'Produits courants', 'description' => 'Articles vendus regulierement au comptoir'],
                    ['name' => 'Produits gros', 'description' => 'Articles vendus en carton, pack ou volume'],
                    ['name' => 'Services', 'description' => 'Prestations simples facturees aux clients'],
                ],
                'expense_categories' => [
                    ['name' => 'Transport', 'description' => 'Transport, livraison et deplacements', 'default_account_code' => '624100'],
                    ['name' => 'Fournitures boutique', 'description' => 'Sacs, etiquettes, papier et consommables', 'default_account_code' => '606300'],
                    ['name' => 'Charges magasin', 'description' => 'Loyer, electricite et fonctionnement', 'default_account_code' => '613000'],
                ],
                'payment_terms' => [
                    ['code' => 'CPT', 'name' => 'Comptant', 'days' => 0, 'description' => 'Paiement immediat.', 'is_default' => true],
                    ['code' => 'TERM-7', 'name' => '7 jours', 'days' => 7, 'description' => 'Petit credit client suivi.', 'is_default' => false],
                    ['code' => 'TERM-30', 'name' => '30 jours', 'days' => 30, 'description' => 'Condition B2B standard.', 'is_default' => false],
                ],
                'price_lists' => [
                    ['code' => 'DETAIL', 'name' => 'Tarif detail', 'description' => 'Prix comptoir standard.', 'is_default' => true],
                    ['code' => 'GROS', 'name' => 'Tarif gros', 'description' => 'Prix pour volumes et clients professionnels.', 'is_default' => false],
                    ['code' => 'PROMO', 'name' => 'Tarif promo', 'description' => 'Prix temporaire pour offres commerciales.', 'is_default' => false],
                ],
            ],
            'food_store' => [
                'product_categories' => [
                    ['name' => 'Boissons', 'description' => 'Produits de boisson et rafraichissement'],
                    ['name' => 'Epicerie', 'description' => 'Produits de consommation courante'],
                    ['name' => 'Produits frais', 'description' => 'Produits frais a rotation rapide'],
                    ['name' => 'Surgeles', 'description' => 'Produits sensibles a la chaine de froid'],
                ],
                'expense_categories' => [
                    ['name' => 'Carburant', 'description' => 'Livraisons et approvisionnement boutique', 'default_account_code' => '625100'],
                    ['name' => 'Chaine froide', 'description' => 'Glace, energie et maintien du froid', 'default_account_code' => '606800'],
                    ['name' => 'Fournitures boutique', 'description' => 'Sacs, papier ticket et consommables comptoir', 'default_account_code' => '606300'],
                ],
                'payment_terms' => [
                    ['code' => 'CPT', 'name' => 'Comptant', 'days' => 0, 'description' => 'Paiement immediat en caisse.', 'is_default' => true],
                    ['code' => 'TERM-7', 'name' => '7 jours', 'days' => 7, 'description' => 'Petit credit client ou livraison proche.', 'is_default' => false],
                ],
                'price_lists' => [
                    ['code' => 'DETAIL', 'name' => 'Tarif detail', 'description' => 'Prix comptoir standard.', 'is_default' => true],
                    ['code' => 'DEMIGROS', 'name' => 'Tarif demi-gros', 'description' => 'Prix accorde a partir des volumes intermediaires.', 'is_default' => false],
                    ['code' => 'GROSSISTE', 'name' => 'Tarif grossiste', 'description' => 'Prix reserve aux gros volumes.', 'is_default' => false],
                ],
            ],
            'restaurant_cafe' => [
                'product_categories' => [
                    ['name' => 'Menus et plats', 'description' => 'Plats, menus et compositions vendus au comptoir'],
                    ['name' => 'Boissons', 'description' => 'Boissons froides, chaudes et rafraichissements'],
                    ['name' => 'Ingredients', 'description' => 'Matieres premieres suivies en stock cuisine'],
                    ['name' => 'Emballages', 'description' => 'Sachets, barquettes et consommables de service'],
                ],
                'expense_categories' => [
                    ['name' => 'Achats cuisine', 'description' => 'Ingredients, condiments et produits frais', 'default_account_code' => '601100'],
                    ['name' => 'Emballages', 'description' => 'Consommables de vente a emporter', 'default_account_code' => '606300'],
                    ['name' => 'Energie cuisine', 'description' => 'Gaz, charbon, electricite et froid', 'default_account_code' => '606800'],
                ],
                'payment_terms' => [
                    ['code' => 'CPT', 'name' => 'Comptant', 'days' => 0, 'description' => 'Paiement immediat au comptoir.', 'is_default' => true],
                    ['code' => 'EVENT-7', 'name' => 'Evenement 7 jours', 'days' => 7, 'description' => 'Reglement court pour commandes groupees.', 'is_default' => false],
                ],
                'price_lists' => [
                    ['code' => 'COMPTOIR', 'name' => 'Tarif comptoir', 'description' => 'Prix restaurant standard.', 'is_default' => true],
                    ['code' => 'LIVRAISON', 'name' => 'Tarif livraison', 'description' => 'Prix adapte aux commandes livrees.', 'is_default' => false],
                    ['code' => 'GROUPE', 'name' => 'Tarif groupe', 'description' => 'Prix pour commandes d equipe ou evenement.', 'is_default' => false],
                ],
            ],
            'services_agency' => [
                'product_categories' => [
                    ['name' => 'Prestations', 'description' => 'Services facturables au client'],
                    ['name' => 'Forfaits', 'description' => 'Offres packagees ou abonnements simples'],
                    ['name' => 'Frais refacturables', 'description' => 'Depenses reprises sur facture client'],
                ],
                'expense_categories' => [
                    ['name' => 'Sous-traitance', 'description' => 'Prestataires externes et freelances', 'default_account_code' => '611000'],
                    ['name' => 'Deplacements', 'description' => 'Transport, mission et rendez-vous client', 'default_account_code' => '625100'],
                    ['name' => 'Outils professionnels', 'description' => 'Logiciels, licences et abonnements', 'default_account_code' => '628100'],
                ],
                'payment_terms' => [
                    ['code' => 'CPT', 'name' => 'Comptant', 'days' => 0, 'description' => 'Paiement a la commande ou au debut de mission.', 'is_default' => true],
                    ['code' => 'TERM-15', 'name' => '15 jours', 'days' => 15, 'description' => 'Reglement court apres livraison.', 'is_default' => false],
                    ['code' => 'TERM-30', 'name' => '30 jours', 'days' => 30, 'description' => 'Conditions B2B standard.', 'is_default' => false],
                ],
                'price_lists' => [
                    ['code' => 'STANDARD', 'name' => 'Tarif standard', 'description' => 'Prix normal des prestations.', 'is_default' => true],
                    ['code' => 'ABONNEMENT', 'name' => 'Tarif abonnement', 'description' => 'Prix pour clients recurrents.', 'is_default' => false],
                ],
            ],
            'construction_projects' => [
                'product_categories' => [
                    ['name' => 'Materiaux', 'description' => 'Ciment, fer, sable, peinture et fournitures chantier'],
                    ['name' => 'Main d oeuvre', 'description' => 'Prestations et travaux facturables'],
                    ['name' => 'Location materiel', 'description' => 'Equipements et engins loues ou refactures'],
                    ['name' => 'Transport chantier', 'description' => 'Livraison et logistique chantier'],
                ],
                'expense_categories' => [
                    ['name' => 'Achats materiaux', 'description' => 'Approvisionnement chantier', 'default_account_code' => '601100'],
                    ['name' => 'Main d oeuvre chantier', 'description' => 'Equipes, journaliers et sous-traitance', 'default_account_code' => '611000'],
                    ['name' => 'Transport chantier', 'description' => 'Camions, carburant et livraison', 'default_account_code' => '624100'],
                ],
                'payment_terms' => [
                    ['code' => 'ACOMPTE', 'name' => 'Acompte chantier', 'days' => 0, 'description' => 'Acompte avant lancement des travaux.', 'is_default' => true],
                    ['code' => 'TERM-30', 'name' => '30 jours', 'days' => 30, 'description' => 'Reglement sur situation ou fin de travaux.', 'is_default' => false],
                ],
                'price_lists' => [
                    ['code' => 'CHANTIER', 'name' => 'Tarif chantier', 'description' => 'Prix de reference travaux.', 'is_default' => true],
                    ['code' => 'PRO', 'name' => 'Tarif professionnel', 'description' => 'Prix reserve aux partenaires et gros travaux.', 'is_default' => false],
                ],
            ],
            'electronics_phone' => [
                'product_categories' => [
                    ['name' => 'Telephones', 'description' => 'Smartphones, appareils et modeles suivis'],
                    ['name' => 'Accessoires', 'description' => 'Chargeurs, ecouteurs, coques et protections'],
                    ['name' => 'Pieces et reparation', 'description' => 'Pieces, interventions et services atelier'],
                ],
                'expense_categories' => [
                    ['name' => 'Transport marchandises', 'description' => 'Import, livraison et transit', 'default_account_code' => '624100'],
                    ['name' => 'Garantie et SAV', 'description' => 'Retours, reparations et gestes commerciaux', 'default_account_code' => '615500'],
                    ['name' => 'Fournitures boutique', 'description' => 'Etiquettes, emballages et consommables', 'default_account_code' => '606300'],
                ],
                'payment_terms' => [
                    ['code' => 'CPT', 'name' => 'Comptant', 'days' => 0, 'description' => 'Paiement immediat en boutique.', 'is_default' => true],
                    ['code' => 'RES-3', 'name' => 'Reservation 3 jours', 'days' => 3, 'description' => 'Reservation courte avec acompte.', 'is_default' => false],
                ],
                'price_lists' => [
                    ['code' => 'DETAIL', 'name' => 'Tarif detail', 'description' => 'Prix boutique standard.', 'is_default' => true],
                    ['code' => 'REVENDEUR', 'name' => 'Tarif revendeur', 'description' => 'Prix pour revendeurs et techniciens.', 'is_default' => false],
                ],
            ],
            'fashion_boutique' => [
                'product_categories' => [
                    ['name' => 'Vetements', 'description' => 'Articles de pret-a-porter et collections'],
                    ['name' => 'Chaussures', 'description' => 'Chaussures, sandales et pointures'],
                    ['name' => 'Accessoires mode', 'description' => 'Sacs, ceintures, bijoux et complements'],
                ],
                'expense_categories' => [
                    ['name' => 'Achats collection', 'description' => 'Approvisionnement saisonnier', 'default_account_code' => '601100'],
                    ['name' => 'Marketing boutique', 'description' => 'Photos, promotions et animation commerciale', 'default_account_code' => '623400'],
                    ['name' => 'Emballages boutique', 'description' => 'Sacs, etiquettes et conditionnement', 'default_account_code' => '606300'],
                ],
                'payment_terms' => [
                    ['code' => 'CPT', 'name' => 'Comptant', 'days' => 0, 'description' => 'Paiement immediat au point de vente.', 'is_default' => true],
                    ['code' => 'RES-7', 'name' => 'Reservation 7 jours', 'days' => 7, 'description' => 'Reservation courte pour clients suivis.', 'is_default' => false],
                ],
                'price_lists' => [
                    ['code' => 'BOUTIQUE', 'name' => 'Tarif boutique', 'description' => 'Prix normal magasin.', 'is_default' => true],
                    ['code' => 'PROMO', 'name' => 'Tarif promo', 'description' => 'Prix soldes ou animation.', 'is_default' => false],
                    ['code' => 'VIP', 'name' => 'Tarif VIP', 'description' => 'Prix clientes fideles.', 'is_default' => false],
                ],
            ],
            'beauty_salon' => [
                'product_categories' => [
                    ['name' => 'Coiffure', 'description' => 'Prestations de coiffure et entretien cheveux'],
                    ['name' => 'Soins', 'description' => 'Services de beaute, visage et corps'],
                    ['name' => 'Produits salon', 'description' => 'Articles vendus ou consommes au salon'],
                    ['name' => 'Forfaits', 'description' => 'Packs et offres groupees'],
                ],
                'expense_categories' => [
                    ['name' => 'Produits salon', 'description' => 'Achats de shampoings, soins et consommables', 'default_account_code' => '601100'],
                    ['name' => 'Fournitures hygiene', 'description' => 'Serviettes, gants, nettoyage et hygiene', 'default_account_code' => '606300'],
                    ['name' => 'Marketing salon', 'description' => 'Photos, promotions et communication', 'default_account_code' => '623400'],
                ],
                'payment_terms' => [
                    ['code' => 'CPT', 'name' => 'Comptant', 'days' => 0, 'description' => 'Paiement immediat apres prestation.', 'is_default' => true],
                    ['code' => 'RES-3', 'name' => 'Reservation 3 jours', 'days' => 3, 'description' => 'Reservation courte avec acompte.', 'is_default' => false],
                ],
                'price_lists' => [
                    ['code' => 'SALON', 'name' => 'Tarif salon', 'description' => 'Prix standard des prestations.', 'is_default' => true],
                    ['code' => 'VIP', 'name' => 'Tarif fidelite', 'description' => 'Prix clients reguliers.', 'is_default' => false],
                    ['code' => 'PROMO', 'name' => 'Tarif promo', 'description' => 'Prix offres speciales.', 'is_default' => false],
                ],
            ],
            'auto_parts_garage' => [
                'product_categories' => [
                    ['name' => 'Pieces auto', 'description' => 'Pieces mecaniques, filtres et accessoires'],
                    ['name' => 'Lubrifiants', 'description' => 'Huiles, liquides et consommables atelier'],
                    ['name' => 'Services atelier', 'description' => 'Main d oeuvre et interventions'],
                ],
                'expense_categories' => [
                    ['name' => 'Achats pieces', 'description' => 'Approvisionnement pieces et consommables', 'default_account_code' => '601100'],
                    ['name' => 'Outillage atelier', 'description' => 'Outils, maintenance et petit equipement', 'default_account_code' => '606300'],
                    ['name' => 'Transport pieces', 'description' => 'Livraisons et courses fournisseur', 'default_account_code' => '624100'],
                ],
                'payment_terms' => [
                    ['code' => 'CPT', 'name' => 'Comptant', 'days' => 0, 'description' => 'Paiement immediat ou avant remise vehicule.', 'is_default' => true],
                    ['code' => 'TERM-15', 'name' => '15 jours', 'days' => 15, 'description' => 'Conditions pour flottes et clients suivis.', 'is_default' => false],
                ],
                'price_lists' => [
                    ['code' => 'DETAIL', 'name' => 'Tarif detail', 'description' => 'Prix comptoir standard.', 'is_default' => true],
                    ['code' => 'GARAGE', 'name' => 'Tarif garage', 'description' => 'Prix atelier et clients professionnels.', 'is_default' => false],
                ],
            ],
            'workshop_manufacturing' => [
                'product_categories' => [
                    ['name' => 'Travaux atelier', 'description' => 'Prestations et commandes fabriquees'],
                    ['name' => 'Matieres premieres', 'description' => 'Composants, tissus, bois, metal ou intrants'],
                    ['name' => 'Main d oeuvre', 'description' => 'Temps de travail et interventions facturees'],
                    ['name' => 'Reparations', 'description' => 'Reprises, maintenance et petites corrections'],
                ],
                'expense_categories' => [
                    ['name' => 'Achats matieres', 'description' => 'Matieres et composants necessaires aux travaux', 'default_account_code' => '601100'],
                    ['name' => 'Main d oeuvre atelier', 'description' => 'Journaliers, sous-traitance et travaux externes', 'default_account_code' => '611000'],
                    ['name' => 'Maintenance outillage', 'description' => 'Entretien des outils et equipements', 'default_account_code' => '615500'],
                ],
                'payment_terms' => [
                    ['code' => 'ACOMPTE', 'name' => 'Acompte', 'days' => 0, 'description' => 'Acompte avant lancement de la commande.', 'is_default' => true],
                    ['code' => 'LIVRAISON', 'name' => 'A la livraison', 'days' => 7, 'description' => 'Solde a la remise du travail.', 'is_default' => false],
                ],
                'price_lists' => [
                    ['code' => 'ATELIER', 'name' => 'Tarif atelier', 'description' => 'Prix standard des travaux.', 'is_default' => true],
                    ['code' => 'PROJET', 'name' => 'Tarif projet', 'description' => 'Prix commandes importantes ou longues.', 'is_default' => false],
                ],
            ],
            'hotel_hospitality' => [
                'product_categories' => [
                    ['name' => 'Hebergement', 'description' => 'Chambres, nuitees et forfaits sejour'],
                    ['name' => 'Services hotel', 'description' => 'Blanchisserie, salle, petit dejeuner et extras'],
                    ['name' => 'Consommations', 'description' => 'Boissons et articles vendus sur place'],
                ],
                'expense_categories' => [
                    ['name' => 'Entretien chambres', 'description' => 'Nettoyage, linge et maintenance', 'default_account_code' => '615500'],
                    ['name' => 'Consommables hotel', 'description' => 'Produits accueil et fournitures client', 'default_account_code' => '606300'],
                    ['name' => 'Energie hotel', 'description' => 'Electricite, eau, groupe et climatisation', 'default_account_code' => '606800'],
                ],
                'payment_terms' => [
                    ['code' => 'CPT', 'name' => 'Comptant', 'days' => 0, 'description' => 'Paiement a l arrivee ou au depart.', 'is_default' => true],
                    ['code' => 'TERM-15', 'name' => '15 jours', 'days' => 15, 'description' => 'Clients entreprises et sejours groupes.', 'is_default' => false],
                ],
                'price_lists' => [
                    ['code' => 'STANDARD', 'name' => 'Tarif standard', 'description' => 'Prix reception.', 'is_default' => true],
                    ['code' => 'ENTREPRISE', 'name' => 'Tarif entreprise', 'description' => 'Prix conventionne pour clients B2B.', 'is_default' => false],
                ],
            ],
            'school_training' => [
                'product_categories' => [
                    ['name' => 'Frais scolaires', 'description' => 'Inscriptions, mensualites et frais administratifs'],
                    ['name' => 'Formations', 'description' => 'Sessions, modules et cycles de formation'],
                    ['name' => 'Supports', 'description' => 'Documents, kits et fournitures vendues'],
                ],
                'expense_categories' => [
                    ['name' => 'Charges pedagogiques', 'description' => 'Intervenants, supports et preparation cours', 'default_account_code' => '611000'],
                    ['name' => 'Fournitures administratives', 'description' => 'Papeterie, impressions et petits achats', 'default_account_code' => '606300'],
                    ['name' => 'Communication', 'description' => 'Campagnes, inscriptions et communication', 'default_account_code' => '623400'],
                ],
                'payment_terms' => [
                    ['code' => 'CPT', 'name' => 'Comptant', 'days' => 0, 'description' => 'Paiement immediat.', 'is_default' => true],
                    ['code' => 'MENSUEL', 'name' => 'Mensuel', 'days' => 30, 'description' => 'Paiement mensuel des frais.', 'is_default' => false],
                    ['code' => 'TRIM', 'name' => 'Trimestriel', 'days' => 90, 'description' => 'Paiement par trimestre ou session.', 'is_default' => false],
                ],
                'price_lists' => [
                    ['code' => 'STANDARD', 'name' => 'Tarif standard', 'description' => 'Prix normal formation ou frais.', 'is_default' => true],
                    ['code' => 'GROUPE', 'name' => 'Tarif groupe', 'description' => 'Prix entreprises, familles ou cohortes.', 'is_default' => false],
                ],
            ],
            'agriculture_livestock' => [
                'product_categories' => [
                    ['name' => 'Intrants', 'description' => 'Semences, engrais, produits et accessoires'],
                    ['name' => 'Produits recoltes', 'description' => 'Produits agricoles vendus ou stockes'],
                    ['name' => 'Aliments betail', 'description' => 'Nourriture, soins et consommables elevage'],
                ],
                'expense_categories' => [
                    ['name' => 'Intrants campagne', 'description' => 'Achats de production agricole', 'default_account_code' => '601100'],
                    ['name' => 'Main d oeuvre terrain', 'description' => 'Travaux saisonniers et prestations', 'default_account_code' => '611000'],
                    ['name' => 'Transport recolte', 'description' => 'Collecte, livraison et logistique', 'default_account_code' => '624100'],
                ],
                'payment_terms' => [
                    ['code' => 'CPT', 'name' => 'Comptant', 'days' => 0, 'description' => 'Paiement immediat.', 'is_default' => true],
                    ['code' => 'SAISON', 'name' => 'Fin de saison', 'days' => 90, 'description' => 'Reglement adapte aux cycles de campagne.', 'is_default' => false],
                ],
                'price_lists' => [
                    ['code' => 'DETAIL', 'name' => 'Tarif detail', 'description' => 'Prix standard.', 'is_default' => true],
                    ['code' => 'COOPERATIVE', 'name' => 'Tarif cooperative', 'description' => 'Prix pour groupements et volumes.', 'is_default' => false],
                ],
            ],
            'import_export' => [
                'product_categories' => [
                    ['name' => 'Lots import', 'description' => 'Marchandises importees par lot ou arrivage'],
                    ['name' => 'Frais transit', 'description' => 'Douane, transit et frais refacturables'],
                    ['name' => 'Transport', 'description' => 'Acheminement, livraison et logistique'],
                    ['name' => 'Services douane', 'description' => 'Prestations administratives et operations'],
                ],
                'expense_categories' => [
                    ['name' => 'Frais douane', 'description' => 'Droits, taxes et frais de transit', 'default_account_code' => '635800'],
                    ['name' => 'Transport international', 'description' => 'Fret, port, aeroport et acheminement', 'default_account_code' => '624100'],
                    ['name' => 'Frais bancaires import', 'description' => 'Virements, change et commissions', 'default_account_code' => '627000'],
                ],
                'payment_terms' => [
                    ['code' => 'ACOMPTE', 'name' => 'Acompte commande', 'days' => 0, 'description' => 'Acompte avant engagement du lot.', 'is_default' => true],
                    ['code' => 'TERM-30', 'name' => '30 jours', 'days' => 30, 'description' => 'Reglement B2B apres livraison.', 'is_default' => false],
                ],
                'price_lists' => [
                    ['code' => 'LOT', 'name' => 'Tarif lot', 'description' => 'Prix par arrivage ou lot.', 'is_default' => true],
                    ['code' => 'REVENDEUR', 'name' => 'Tarif revendeur', 'description' => 'Prix pour clients professionnels.', 'is_default' => false],
                ],
            ],
            'wholesale_distribution' => [
                'product_categories' => [
                    ['name' => 'Produits detail', 'description' => 'Articles vendus a l unite ou au petit lot'],
                    ['name' => 'Produits gros', 'description' => 'Articles vendus en carton, sac ou palette'],
                    ['name' => 'Conditionnements', 'description' => 'Lots et conditionnements multi-formats'],
                    ['name' => 'Services logistiques', 'description' => 'Prestations et frais de livraison facturables'],
                ],
                'expense_categories' => [
                    ['name' => 'Transport interville', 'description' => 'Acheminement regional et livraisons lourdes', 'default_account_code' => '624100'],
                    ['name' => 'Manutention', 'description' => 'Chargement, dechargement et preparation', 'default_account_code' => '611200'],
                    ['name' => 'Carburant', 'description' => 'Depenses flotte ou ravitaillement', 'default_account_code' => '625100'],
                ],
                'payment_terms' => [
                    ['code' => 'CPT', 'name' => 'Comptant', 'days' => 0, 'description' => 'Paiement a la commande.', 'is_default' => true],
                    ['code' => 'TERM-15', 'name' => '15 jours', 'days' => 15, 'description' => 'Petit credit commercial.', 'is_default' => false],
                    ['code' => 'TERM-30', 'name' => '30 jours', 'days' => 30, 'description' => 'Conditions B2B pour clients negocies.', 'is_default' => false],
                ],
                'price_lists' => [
                    ['code' => 'DETAIL', 'name' => 'Tarif detail', 'description' => 'Reference unite ou petit lot.', 'is_default' => true],
                    ['code' => 'SEMIGROS', 'name' => 'Tarif semi-gros', 'description' => 'Conditionnement intermediaire.', 'is_default' => false],
                    ['code' => 'GROSSISTE', 'name' => 'Tarif grossiste', 'description' => 'Prix dedie aux gros volumes et revendeurs.', 'is_default' => false],
                ],
            ],
            'hardware_store' => [
                'product_categories' => [
                    ['name' => 'Outillage', 'description' => 'Outils, equipements et accessoires'],
                    ['name' => 'Electricite', 'description' => 'Cables, prises, gaines et appareillage'],
                    ['name' => 'Plomberie', 'description' => 'Tuyaux, raccords, robinetterie'],
                    ['name' => 'Fixations', 'description' => 'Visserie, boulonnerie et quincaillerie fine'],
                ],
                'expense_categories' => [
                    ['name' => 'Transport chantier', 'description' => 'Livraisons et depenses sur site client', 'default_account_code' => '624100'],
                    ['name' => 'Maintenance atelier', 'description' => 'Reparation et entretien du materiel', 'default_account_code' => '615500'],
                    ['name' => 'Fournitures atelier', 'description' => 'Consommables techniques et petit materiel', 'default_account_code' => '606300'],
                ],
                'payment_terms' => [
                    ['code' => 'CPT', 'name' => 'Comptant', 'days' => 0, 'description' => 'Paiement immediat ou avant livraison.', 'is_default' => true],
                    ['code' => 'TERM-15', 'name' => '15 jours', 'days' => 15, 'description' => 'Petits comptes B2B ou artisans suivis.', 'is_default' => false],
                ],
                'price_lists' => [
                    ['code' => 'DETAIL', 'name' => 'Tarif detail', 'description' => 'Prix standard magasin.', 'is_default' => true],
                    ['code' => 'CHANTIER', 'name' => 'Tarif chantier', 'description' => 'Prix adapte aux commandes chantier.', 'is_default' => false],
                    ['code' => 'REVENDEUR', 'name' => 'Tarif revendeur', 'description' => 'Prix reserve aux clients professionnels reguliers.', 'is_default' => false],
                ],
            ],
            'pharmacy_parapharmacy' => [
                'product_categories' => [
                    ['name' => 'Prescription', 'description' => 'Produits et references soumis a prescription'],
                    ['name' => 'Parapharmacie', 'description' => 'Produits bien-etre, soins et sante preventive'],
                    ['name' => 'Hygiene', 'description' => 'Articles d hygiene et d entretien personnel'],
                    ['name' => 'Produits sensibles', 'description' => 'Articles a suivi strict de lot et peremption'],
                ],
                'expense_categories' => [
                    ['name' => 'Transport medical', 'description' => 'Approvisionnement et logistique sensible', 'default_account_code' => '624100'],
                    ['name' => 'Chaine froide', 'description' => 'Conservation, glaciere et suivi du froid', 'default_account_code' => '606800'],
                    ['name' => 'Fournitures medicales', 'description' => 'Consommables, blouses, emballages et kits', 'default_account_code' => '606400'],
                ],
                'payment_terms' => [
                    ['code' => 'CPT', 'name' => 'Comptant', 'days' => 0, 'description' => 'Paiement immediat au comptoir.', 'is_default' => true],
                    ['code' => 'TERM-30', 'name' => '30 jours', 'days' => 30, 'description' => 'Conditions reservees aux structures medicales.', 'is_default' => false],
                ],
                'price_lists' => [
                    ['code' => 'DETAIL', 'name' => 'Tarif detail', 'description' => 'Prix patient ou comptoir.', 'is_default' => true],
                    ['code' => 'CLINIQUE', 'name' => 'Tarif clinique', 'description' => 'Tarif dedie aux cabinets et structures suivies.', 'is_default' => false],
                ],
            ],
            'delivery_company' => [
                'product_categories' => [
                    ['name' => 'Livraison locale', 'description' => 'Courses et livraisons dans la meme ville'],
                    ['name' => 'Course express', 'description' => 'Livraisons urgentes a delai court'],
                    ['name' => 'Transport colis', 'description' => 'Colis, paquets et courses specialisees'],
                    ['name' => 'Frais supplementaires', 'description' => 'Attente, distance, manutention et options'],
                ],
                'expense_categories' => [
                    ['name' => 'Carburant', 'description' => 'Depenses carburant des livreurs', 'default_account_code' => '625100'],
                    ['name' => 'Entretien motos', 'description' => 'Maintenance et reparations vehicules', 'default_account_code' => '615500'],
                    ['name' => 'Communication livreurs', 'description' => 'Appels, internet et coordination terrain', 'default_account_code' => '628100'],
                ],
                'payment_terms' => [
                    ['code' => 'CPT', 'name' => 'Comptant', 'days' => 0, 'description' => 'Paiement a la course ou a la livraison.', 'is_default' => true],
                    ['code' => 'TERM-15', 'name' => '15 jours', 'days' => 15, 'description' => 'Compte client entreprise.', 'is_default' => false],
                ],
                'price_lists' => [
                    ['code' => 'VILLE', 'name' => 'Tarif ville', 'description' => 'Prix standard livraison locale.', 'is_default' => true],
                    ['code' => 'EXPRESS', 'name' => 'Tarif express', 'description' => 'Prix courses urgentes.', 'is_default' => false],
                    ['code' => 'ENTREPRISE', 'name' => 'Tarif entreprise', 'description' => 'Prix clients recurrents.', 'is_default' => false],
                ],
            ],
            'cosmetics_beauty' => [
                'product_categories' => [
                    ['name' => 'Soins visage', 'description' => 'Produits de soin et routine visage'],
                    ['name' => 'Soins corps', 'description' => 'Produits de soin corps et capillaires'],
                    ['name' => 'Parfums', 'description' => 'Parfums, brumes et fragrances'],
                    ['name' => 'Coffrets', 'description' => 'Packs premium et offres cadeaux'],
                ],
                'expense_categories' => [
                    ['name' => 'Marketing boutique', 'description' => 'Animations, promotion et acquisition client', 'default_account_code' => '623400'],
                    ['name' => 'Presentoirs', 'description' => 'Supports de presentation et mise en rayon', 'default_account_code' => '606300'],
                    ['name' => 'Fournitures boutique', 'description' => 'Sacs, emballages et consommables de vente', 'default_account_code' => '606300'],
                ],
                'payment_terms' => [
                    ['code' => 'CPT', 'name' => 'Comptant', 'days' => 0, 'description' => 'Paiement immediat au point de vente.', 'is_default' => true],
                    ['code' => 'TERM-7', 'name' => '7 jours', 'days' => 7, 'description' => 'Acompte ou reservation courte.', 'is_default' => false],
                ],
                'price_lists' => [
                    ['code' => 'DETAIL', 'name' => 'Tarif detail', 'description' => 'Prix boutique standard.', 'is_default' => true],
                    ['code' => 'PROMO', 'name' => 'Tarif promo', 'description' => 'Prix promotionnel ou animation commerciale.', 'is_default' => false],
                    ['code' => 'VIP', 'name' => 'Tarif VIP', 'description' => 'Prix privilegie pour les clientes fideles.', 'is_default' => false],
                ],
            ],
            default => [
                'product_categories' => $this->starterProductCategories($profile),
                'expense_categories' => [
                    ['name' => 'Transport', 'description' => 'Transport, livraison et deplacement', 'default_account_code' => '624100'],
                    ['name' => 'Loyer', 'description' => 'Charges de local et occupation', 'default_account_code' => '613000'],
                    ['name' => 'Frais administratifs', 'description' => 'Consommables et petit fonctionnement', 'default_account_code' => '606300'],
                ],
                'payment_terms' => [
                    ['code' => 'CPT', 'name' => 'Comptant', 'days' => 0, 'description' => 'Paiement immediat.', 'is_default' => true],
                    ['code' => 'TERM-30', 'name' => '30 jours', 'days' => 30, 'description' => 'Condition de paiement B2B standard.', 'is_default' => false],
                ],
                'price_lists' => [
                    ['code' => 'DETAIL', 'name' => 'Tarif detail', 'description' => 'Prix standard de reference.', 'is_default' => true],
                    ['code' => 'GROS', 'name' => 'Tarif gros', 'description' => 'Prix dedie aux volumes et partenaires professionnels.', 'is_default' => false],
                ],
            ],
        };
    }

    private function starterProductCategories(array $profile): array
    {
        $categories = $profile['starter']['categories'] ?? [];

        if ($categories === []) {
            $categories = ['Produits', 'Services', 'Frais'];
        }

        return collect($categories)
            ->map(fn (string $name): array => [
                'name' => $name,
                'description' => 'Categorie de depart pour '.$name,
            ])
            ->values()
            ->all();
    }
}
