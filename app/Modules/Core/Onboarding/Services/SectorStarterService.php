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
        $gateways = $this->paymentGatewayService->configurationForCompany($companyId);
        $recommendedGatewayKeys = $this->supportedGatewayKeys($profile['recommended_payments'] ?? []);
        $readyGatewayCount = collect($recommendedGatewayKeys)
            ->filter(fn (string $key) => (bool) ($gateways[$key]['enabled'] ?? false))
            ->count();

        return [
            'is_applied' => filled($payload['applied_at'] ?? null) && ($payload['profile'] ?? null) === $profile['key'],
            'applied_at' => $payload['applied_at'] ?? null,
            'applied_profile' => $payload['profile'] ?? null,
            'categories_count' => ProductCategory::query()->where('company_id', $companyId)->count(),
            'expense_categories_count' => ExpenseCategory::query()->where('company_id', $companyId)->count(),
            'payment_terms_count' => PaymentTerm::query()->where('company_id', $companyId)->count(),
            'price_lists_count' => PriceList::query()->where('company_id', $companyId)->count(),
            'recommended_gateways_count' => count($recommendedGatewayKeys),
            'recommended_gateways_ready' => $readyGatewayCount,
            'units' => is_array($payload['units'] ?? null) ? $payload['units'] : ($profile['recommended_units'] ?? []),
        ];
    }

    public function apply(Company $company): array
    {
        $profile = $this->sectorProfileService->profileForCompany($company->id);
        $blueprint = $this->blueprint($profile['key']);

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
        $configuredGateways = $this->syncPaymentGateways($company, $profile['recommended_payments'] ?? []);

        Setting::query()->updateOrCreate(
            ['company_id' => $company->id, 'key' => 'sector_units'],
            [
                'tenant_id' => $company->tenant_id,
                'value' => [
                    'profile' => $profile['key'],
                    'units' => $profile['recommended_units'] ?? [],
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
                    'units' => $profile['recommended_units'] ?? [],
                    'starter_catalog' => $profile['starter_catalog'] ?? [],
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

    private function blueprint(string $profileKey): array
    {
        return match ($profileKey) {
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
                'product_categories' => [
                    ['name' => 'Distribution detail', 'description' => 'Articles vendus a l unite ou petit conditionnement'],
                    ['name' => 'Distribution gros', 'description' => 'Articles vendus en volume ou conditionnement lourd'],
                    ['name' => 'Services', 'description' => 'Prestations et services non stockes'],
                ],
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
}
