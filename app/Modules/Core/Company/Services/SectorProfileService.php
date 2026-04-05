<?php

namespace App\Modules\Core\Company\Services;

use App\Modules\Core\Company\Models\Setting;

class SectorProfileService
{
    public const DEFAULT_PROFILE = 'general_trade';

    public function profiles(): array
    {
        return [
            'general_trade' => [
                'key' => 'general_trade',
                'label' => 'Commerce general et distribution',
                'badge' => 'Polyvalent',
                'description' => 'Noyau ERP transversal pour distribution, negoce, multi-agences et activites commerciales mixtes.',
                'use_cases' => ['Commerce general', 'Distribution', 'Multi-agences'],
                'recommended_units' => ['Unite', 'Pack', 'Carton'],
                'recommended_payments' => ['Especes', 'Wave', 'Orange Money'],
                'operational_focus' => ['Catalogue propre', 'Listes de prix', 'Stock multi-depots'],
                'starter_catalog' => ['Produits stockables', 'Tarifs detail / gros', 'Fournisseurs multiples'],
                'recommended_modules' => [
                    ['permission' => 'products.view', 'label' => 'Catalogue produits', 'description' => 'Structurer variantes, unites, tarifs et fournisseurs.', 'route_name' => 'products.index'],
                    ['permission' => 'orders.view', 'label' => 'Commandes clients', 'description' => 'Suivre promesses, disponibilite et livraisons.', 'route_name' => 'orders.index'],
                    ['permission' => 'purchase_requests.view', 'label' => 'Reapprovisionnement', 'description' => 'Piloter suggestions et demandes d achat.', 'route_name' => 'replenishments.index'],
                    ['permission' => 'reports.view', 'label' => 'Rapports dirigeants', 'description' => 'Comparer ventes, marges et performance agence.', 'route_name' => 'reports.index'],
                ],
            ],
            'food_store' => [
                'key' => 'food_store',
                'label' => 'Alimentation et boutique',
                'badge' => 'Retail terrain',
                'description' => 'Optimise la caisse, les ruptures rayon, les dates de peremption et les paiements rapides en boutique.',
                'use_cases' => ['Alimentation', 'Superette', 'Boutique de quartier'],
                'recommended_units' => ['Unite', 'Pack', 'Carton', 'Kg'],
                'recommended_payments' => ['Especes', 'Wave', 'Orange Money', 'Moov Money'],
                'operational_focus' => ['POS rapide', 'Peremption', 'Rupture rayon'],
                'starter_catalog' => ['Boissons', 'Epicerie', 'Produits frais'],
                'recommended_modules' => [
                    ['permission' => 'pos.view', 'label' => 'Point de vente', 'description' => 'Encaisser vite, avec mode offline et ticket.', 'route_name' => 'pos.index'],
                    ['permission' => 'stock.view', 'label' => 'Lots et peremption', 'description' => 'Surveiller les lots stables, sensibles et expires.', 'route_name' => 'stock.lots'],
                    ['permission' => 'purchase_requests.view', 'label' => 'Reassort rayon', 'description' => 'Anticiper les ruptures sur les references qui tournent.', 'route_name' => 'replenishments.index'],
                    ['permission' => 'notifications.view', 'label' => 'Alertes de rupture', 'description' => 'Traiter les alertes de stock et les urgences terrain.', 'route_name' => 'notifications.index'],
                ],
            ],
            'wholesale_distribution' => [
                'key' => 'wholesale_distribution',
                'label' => 'Grossiste et distribution',
                'badge' => 'Volume et logistique',
                'description' => 'Mode pense pour devis, commandes, approvisionnement et promesses client sur des volumes multi-depots.',
                'use_cases' => ['Grossiste', 'Semi-gros', 'Distribution regionale'],
                'recommended_units' => ['Carton', 'Sac', 'Palette', 'Unite'],
                'recommended_payments' => ['Virement', 'Orange Money', 'Wave'],
                'operational_focus' => ['Promesse de stock', 'Reappro central', 'Recouvrement'],
                'starter_catalog' => ['Articles detail / gros', 'Tarifs client', 'Appro fournisseurs'],
                'recommended_modules' => [
                    ['permission' => 'quotes.view', 'label' => 'Devis et tarifs', 'description' => 'Gerer prix client, remises et avant-ventes.', 'route_name' => 'quotes.index'],
                    ['permission' => 'orders.view', 'label' => 'Commandes clients', 'description' => 'Suivre ATP, disponibilite et dates attendues.', 'route_name' => 'orders.index'],
                    ['permission' => 'purchase_orders.view', 'label' => 'Commandes fournisseurs', 'description' => 'Convertir les besoins en commandes sourcees.', 'route_name' => 'purchase-orders.index'],
                    ['permission' => 'reports.view', 'label' => 'Pilotage commercial', 'description' => 'Comparer agences, top clients et marges.', 'route_name' => 'reports.index'],
                ],
            ],
            'hardware_store' => [
                'key' => 'hardware_store',
                'label' => 'Quincaillerie et negoce technique',
                'badge' => 'Catalogue profond',
                'description' => 'Ideal pour references nombreuses, variantes techniques, devis et commandes avec suivi precis du stock.',
                'use_cases' => ['Quincaillerie', 'Materiaux', 'Negoce technique'],
                'recommended_units' => ['Unite', 'Boite', 'Rouleau', 'Carton'],
                'recommended_payments' => ['Especes', 'Virement', 'Orange Money'],
                'operational_focus' => ['Variantes', 'Devis techniques', 'Stock detaille'],
                'starter_catalog' => ['References techniques', 'Conditionnements', 'Fournisseurs preferes'],
                'recommended_modules' => [
                    ['permission' => 'products.view', 'label' => 'Catalogue technique', 'description' => 'Gerer variantes, attributs et fournisseurs par reference.', 'route_name' => 'products.index'],
                    ['permission' => 'quotes.view', 'label' => 'Devis techniques', 'description' => 'Assembler rapidement des offres clients detaillees.', 'route_name' => 'quotes.index'],
                    ['permission' => 'orders.view', 'label' => 'Commandes clients', 'description' => 'Suivre disponibilite et reservations de stock.', 'route_name' => 'orders.index'],
                    ['permission' => 'stock.view', 'label' => 'Stock depot', 'description' => 'Controler quantites, mouvements et ruptures reelles.', 'route_name' => 'stock.index'],
                ],
            ],
            'pharmacy_parapharmacy' => [
                'key' => 'pharmacy_parapharmacy',
                'label' => 'Pharmacie et parapharmacie',
                'badge' => 'Tracabilite sensible',
                'description' => 'Met l accent sur lots, peremption, disponibilite rayon et suivi strict du stock sensible.',
                'use_cases' => ['Pharmacie', 'Parapharmacie', 'Sante de proximite'],
                'recommended_units' => ['Boite', 'Plaquette', 'Flacon'],
                'recommended_payments' => ['Especes', 'Wave', 'Virement'],
                'operational_focus' => ['Tracabilite lot', 'Peremption', 'Stock critique'],
                'starter_catalog' => ['Produits traces', 'Familles sensibles', 'Reassort securise'],
                'recommended_modules' => [
                    ['permission' => 'stock.view', 'label' => 'Lots et peremption', 'description' => 'Suivre les lots, expirations et alertes critiques.', 'route_name' => 'stock.lots'],
                    ['permission' => 'products.view', 'label' => 'Catalogue trace', 'description' => 'Configurer variantes, suivis et fournisseurs preferes.', 'route_name' => 'products.index'],
                    ['permission' => 'sales.view', 'label' => 'Ventes comptoir', 'description' => 'Suivre la sortie produit et les tickets en caisse.', 'route_name' => 'sales.index'],
                    ['permission' => 'notifications.view', 'label' => 'Alertes sensibles', 'description' => 'Traiter expirations proches et risques de rupture.', 'route_name' => 'notifications.index'],
                ],
            ],
            'cosmetics_beauty' => [
                'key' => 'cosmetics_beauty',
                'label' => 'Cosmetique et beaute',
                'badge' => 'Retail premium',
                'description' => 'Concentre le catalogue, le POS, les prix et les animations commerciales pour des ventes retail plus fluides.',
                'use_cases' => ['Cosmetique', 'Parfumerie', 'Beaute'],
                'recommended_units' => ['Unite', 'Pack', 'Coffret'],
                'recommended_payments' => ['Especes', 'Wave', 'Orange Money'],
                'operational_focus' => ['Retail premium', 'POS', 'Top ventes'],
                'starter_catalog' => ['Coffrets', 'Variantes parfum', 'Prix promo'],
                'recommended_modules' => [
                    ['permission' => 'pos.view', 'label' => 'Point de vente', 'description' => 'Vendre vite avec panier, remises et moyens de paiement terrain.', 'route_name' => 'pos.index'],
                    ['permission' => 'products.view', 'label' => 'Catalogue marque', 'description' => 'Structurer les variantes et les familles premium.', 'route_name' => 'products.index'],
                    ['permission' => 'reports.view', 'label' => 'Performance boutique', 'description' => 'Suivre top produits et rotation rapide.', 'route_name' => 'reports.index'],
                    ['permission' => 'settings.view', 'label' => 'Listes de prix', 'description' => 'Regler detail, promo et client VIP.', 'route_name' => 'settings.index'],
                ],
            ],
        ];
    }

    public function keys(): array
    {
        return array_keys($this->profiles());
    }

    public function defaultProfile(): array
    {
        return $this->profiles()[self::DEFAULT_PROFILE];
    }

    public function isExplicitlyConfigured(?int $companyId): bool
    {
        if (! $companyId) {
            return false;
        }

        return Setting::query()
            ->where('company_id', $companyId)
            ->where('key', 'sector_profile')
            ->exists();
    }

    public function profileForCompany(?int $companyId): array
    {
        if (! $companyId) {
            return $this->defaultProfile();
        }

        $setting = Setting::query()
            ->where('company_id', $companyId)
            ->where('key', 'sector_profile')
            ->first();

        $profileKey = (string) ($setting?->value['profile'] ?? self::DEFAULT_PROFILE);

        return $this->profiles()[$profileKey] ?? $this->defaultProfile();
    }

    public function updateProfile(int $companyId, ?int $tenantId, string $profileKey): void
    {
        Setting::query()->updateOrCreate(
            ['company_id' => $companyId, 'key' => 'sector_profile'],
            [
                'tenant_id' => $tenantId,
                'value' => ['profile' => $profileKey],
            ]
        );
    }
}

