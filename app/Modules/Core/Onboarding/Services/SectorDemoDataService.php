<?php

namespace App\Modules\Core\Onboarding\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCategory;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Company\Models\PaymentTerm;
use App\Modules\Core\Company\Models\PriceList;
use App\Modules\Core\Company\Models\PriceListItem;
use App\Modules\Core\Company\Models\Setting;
use App\Modules\Core\Company\Services\SectorProfileService;
use App\Modules\Inventory\Models\ProductLot;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Partners\Models\Partner;
use App\Modules\Partners\Models\PartnerBankAccount;
use App\Modules\Partners\Models\PartnerMobileWallet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SectorDemoDataService
{
    public function __construct(
        private readonly SectorProfileService $sectorProfileService,
        private readonly SectorStarterService $sectorStarterService,
        private readonly StockService $stockService,
    ) {}

    public function status(int $companyId): array
    {
        $profile = $this->sectorProfileService->profileForCompany($companyId);
        $setting = Setting::query()->where('company_id', $companyId)->where('key', 'sector_demo_data')->first();
        $payload = is_array($setting?->value) ? $setting->value : [];
        $playbooks = $this->playbooks($profile['key']);

        return [
            'is_applied' => filled($payload['applied_at'] ?? null) && ($payload['profile'] ?? null) === $profile['key'],
            'applied_at' => $payload['applied_at'] ?? null,
            'applied_profile' => $payload['profile'] ?? null,
            'suppliers_count' => Partner::query()->suppliers()->where('company_id', $companyId)->count(),
            'customers_count' => Partner::query()->customers()->where('company_id', $companyId)->count(),
            'products_count' => Product::query()->where('company_id', $companyId)->count(),
            'price_items_count' => PriceListItem::query()->where('company_id', $companyId)->count(),
            'stock_entries_count' => StockMovement::query()
                ->where('company_id', $companyId)
                ->where(fn ($query) => $query
                    ->where('notes', 'like', 'SECTOR-DEMO-%')
                    ->orWhere('notes', 'like', 'SECTOR-DEMO-LOT-%'))
                ->count(),
            'lots_count' => ProductLot::query()
                ->where('company_id', $companyId)
                ->where('notes', 'like', 'SECTOR-DEMO-%')
                ->count(),
            'supplier_links_count' => ProductSupplier::query()->where('company_id', $companyId)->count(),
            'branch_name' => $payload['branch_name'] ?? null,
            'warehouse_name' => $payload['warehouse_name'] ?? null,
            'catalog_highlights' => is_array($payload['catalog_highlights'] ?? null) ? $payload['catalog_highlights'] : [],
            'created' => is_array($payload['created'] ?? null) ? $payload['created'] : [],
            'playbooks' => $playbooks,
            'playbooks_count' => count($playbooks),
        ];
    }

    public function apply(Company $company): array
    {
        return DB::transaction(function () use ($company) {
            $starter = $this->sectorStarterService->apply($company);
            $profile = $this->sectorProfileService->profileForCompany($company->id);
            $blueprint = $this->blueprint($profile['key']);

            [$branch, $warehouse] = $this->resolveBranchAndWarehouse($company);
            $paymentTerms = PaymentTerm::query()->where('company_id', $company->id)->get()->keyBy('code');
            $priceLists = PriceList::query()->where('company_id', $company->id)->get()->keyBy('code');
            $categories = ProductCategory::query()->where('company_id', $company->id)->get()->keyBy('name');

            $supplierSync = $this->syncPartners(
                company: $company,
                definitions: $blueprint['suppliers'],
                type: 'supplier',
                paymentTerms: $paymentTerms,
                priceLists: $priceLists,
            );
            $customerSync = $this->syncPartners(
                company: $company,
                definitions: $blueprint['customers'],
                type: 'customer',
                paymentTerms: $paymentTerms,
                priceLists: $priceLists,
            );
            $productSync = $this->syncProducts($company, $blueprint['products'], $categories);
            $priceItemCount = $this->syncPriceListItems($company, $blueprint['products'], $productSync['items'], $priceLists);
            $supplierLinkCount = $this->syncProductSuppliers($company, $blueprint['products'], $productSync['items'], $supplierSync['items']);
            $stockSync = $this->syncStock($company, $branch, $warehouse, $blueprint['products'], $productSync['items']);

            $created = [
                'suppliers' => $supplierSync['created'],
                'customers' => $customerSync['created'],
                'products' => $productSync['created'],
                'price_items' => $priceItemCount,
                'supplier_links' => $supplierLinkCount,
                'stock_entries' => $stockSync['stock_entries'],
                'lots' => $stockSync['lots'],
            ];

            $catalogHighlights = collect($blueprint['products'])
                ->map(fn (array $product): string => $product['name'])
                ->take(6)
                ->values()
                ->all();

            Setting::query()->updateOrCreate(
                ['company_id' => $company->id, 'key' => 'sector_demo_data'],
                [
                    'tenant_id' => $company->tenant_id,
                    'value' => [
                        'applied_at' => now()->toDateTimeString(),
                        'profile' => $profile['key'],
                        'profile_label' => $profile['label'],
                        'branch_id' => $branch->id,
                        'branch_name' => $branch->name,
                        'warehouse_id' => $warehouse->id,
                        'warehouse_name' => $warehouse->name,
                        'created' => $created,
                        'catalog_highlights' => $catalogHighlights,
                        'playbooks' => $this->playbooks($profile['key']),
                    ],
                ],
            );

            return [
                'profile' => $profile,
                'starter' => $starter,
                'created' => $created,
                'branch' => $branch,
                'warehouse' => $warehouse,
                'status' => $this->status($company->id),
            ];
        });
    }

    private function syncPartners(
        Company $company,
        array $definitions,
        string $type,
        Collection $paymentTerms,
        Collection $priceLists,
    ): array {
        $created = 0;
        $partners = collect();

        foreach ($definitions as $definition) {
            $partner = Partner::query()->firstOrNew([
                'company_id' => $company->id,
                'code' => $definition['code'],
            ]);

            $partner->fill([
                'tenant_id' => $company->tenant_id,
                'type' => $type,
                'name' => $definition['name'],
                'phone' => $definition['phone'] ?? null,
                'email' => $definition['email'] ?? null,
                'city' => $definition['city'] ?? null,
                'address' => $definition['address'] ?? null,
                'opening_balance' => $definition['opening_balance'] ?? 0,
                'payment_term_id' => $paymentTerms->get($definition['payment_term_code'] ?? '')?->id,
                'price_list_id' => $priceLists->get($definition['price_list_code'] ?? '')?->id,
                'notes' => $definition['notes'] ?? null,
                'is_active' => true,
            ])->save();

            $created += $partner->wasRecentlyCreated ? 1 : 0;
            $this->syncPartnerWallets($company, $partner, $definition['mobile_wallets'] ?? []);
            $this->syncPartnerBankAccounts($company, $partner, $definition['bank_accounts'] ?? []);
            $partners->put($definition['code'], $partner);
        }

        return [
            'created' => $created,
            'items' => $partners,
        ];
    }

    private function syncPartnerWallets(Company $company, Partner $partner, array $wallets): void
    {
        foreach ($wallets as $wallet) {
            PartnerMobileWallet::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'partner_id' => $partner->id,
                    'provider' => $wallet['provider'],
                    'wallet_number' => $wallet['wallet_number'],
                ],
                [
                    'tenant_id' => $company->tenant_id,
                    'account_name' => $wallet['account_name'] ?? $partner->name,
                    'is_primary' => (bool) ($wallet['is_primary'] ?? false),
                ]
            );
        }
    }

    private function syncPartnerBankAccounts(Company $company, Partner $partner, array $bankAccounts): void
    {
        foreach ($bankAccounts as $account) {
            PartnerBankAccount::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'partner_id' => $partner->id,
                    'bank_name' => $account['bank_name'],
                    'account_number' => $account['account_number'],
                ],
                [
                    'tenant_id' => $company->tenant_id,
                    'account_name' => $account['account_name'] ?? $partner->name,
                    'iban' => $account['iban'] ?? null,
                    'swift_code' => $account['swift_code'] ?? null,
                    'is_primary' => (bool) ($account['is_primary'] ?? false),
                ]
            );
        }
    }

    private function syncProducts(Company $company, array $definitions, Collection $categories): array
    {
        $created = 0;
        $products = collect();

        foreach ($definitions as $definition) {
            $product = Product::query()->firstOrNew([
                'company_id' => $company->id,
                'sku' => $definition['sku'],
            ]);

            $category = $categories->get($definition['category']);
            $trackingType = $definition['tracking_type'] ?? 'none';

            $product->fill([
                'tenant_id' => $company->tenant_id,
                'category_id' => $category?->id,
                'barcode' => $definition['barcode'] ?? null,
                'name' => $definition['name'],
                'unit' => $definition['unit'],
                'sales_unit_name' => $definition['sales_unit_name'] ?? null,
                'sales_unit_ratio' => $definition['sales_unit_ratio'] ?? null,
                'purchase_unit_name' => $definition['purchase_unit_name'] ?? null,
                'purchase_unit_ratio' => $definition['purchase_unit_ratio'] ?? null,
                'type' => $definition['type'] ?? 'stockable',
                'sale_ok' => (bool) ($definition['sale_ok'] ?? true),
                'purchase_ok' => (bool) ($definition['purchase_ok'] ?? true),
                'invoice_policy' => $definition['invoice_policy'] ?? 'ordered',
                'tracking_type' => $trackingType,
                'sale_price' => $definition['sale_price'],
                'purchase_price' => $definition['purchase_price'],
                'min_stock' => $definition['min_stock'] ?? 0,
                'auto_replenish' => (bool) ($definition['auto_replenish'] ?? true),
                'reorder_max_qty' => $definition['reorder_max_qty'] ?? null,
                'reorder_multiple_qty' => $definition['reorder_multiple_qty'] ?? null,
                'purchase_lead_time_days' => $definition['purchase_lead_time_days'] ?? null,
                'description' => $definition['description'] ?? null,
                'sales_description' => $definition['sales_description'] ?? null,
                'purchase_description' => $definition['purchase_description'] ?? null,
                'internal_notes' => $definition['internal_notes'] ?? null,
                'is_active' => true,
            ])->save();

            $created += $product->wasRecentlyCreated ? 1 : 0;
            $products->put($definition['sku'], $product);
        }

        return [
            'created' => $created,
            'items' => $products,
        ];
    }

    private function syncPriceListItems(Company $company, array $definitions, Collection $products, Collection $priceLists): int
    {
        $created = 0;

        foreach ($definitions as $definition) {
            $product = $products->get($definition['sku']);

            if (! $product) {
                continue;
            }

            foreach ($definition['price_lists'] ?? [] as $priceRule) {
                $priceList = $priceLists->get($priceRule['code']);

                if (! $priceList) {
                    continue;
                }

                $item = PriceListItem::query()->firstOrNew([
                    'price_list_id' => $priceList->id,
                    'product_id' => $product->id,
                    'min_qty' => $priceRule['min_qty'] ?? 1,
                ]);

                $item->fill([
                    'tenant_id' => $company->tenant_id,
                    'company_id' => $company->id,
                    'price' => $priceRule['price'],
                ])->save();

                $created += $item->wasRecentlyCreated ? 1 : 0;
            }
        }

        return $created;
    }

    private function syncProductSuppliers(Company $company, array $definitions, Collection $products, Collection $suppliers): int
    {
        $created = 0;

        foreach ($definitions as $definition) {
            $product = $products->get($definition['sku']);

            if (! $product) {
                continue;
            }

            foreach ($definition['supplier_infos'] ?? [] as $supplierInfo) {
                $supplier = $suppliers->get($supplierInfo['supplier_code']);

                if (! $supplier) {
                    continue;
                }

                $link = ProductSupplier::query()->firstOrNew([
                    'product_id' => $product->id,
                    'supplier_id' => $supplier->id,
                ]);

                $link->fill([
                    'tenant_id' => $company->tenant_id,
                    'company_id' => $company->id,
                    'supplier_product_code' => $supplierInfo['supplier_product_code'] ?? $definition['sku'],
                    'supplier_product_name' => $supplierInfo['supplier_product_name'] ?? $product->name,
                    'min_qty' => $supplierInfo['min_qty'] ?? null,
                    'unit_cost' => $supplierInfo['unit_cost'] ?? $product->purchase_price,
                    'lead_time_days' => $supplierInfo['lead_time_days'] ?? ($definition['purchase_lead_time_days'] ?? null),
                    'is_preferred' => (bool) ($supplierInfo['is_preferred'] ?? false),
                ])->save();

                $created += $link->wasRecentlyCreated ? 1 : 0;
            }
        }

        return $created;
    }

    private function syncStock(
        Company $company,
        Branch $branch,
        Warehouse $warehouse,
        array $definitions,
        Collection $products,
    ): array {
        $stockEntries = 0;
        $lots = 0;

        foreach ($definitions as $definition) {
            $product = $products->get($definition['sku']);

            if (! $product) {
                continue;
            }

            $lotDefinitions = $definition['lots'] ?? [];

            if (($definition['tracking_type'] ?? 'none') === 'lot' && $lotDefinitions !== []) {
                foreach ($lotDefinitions as $lotDefinition) {
                    $lotNumber = $lotDefinition['lot_number'];
                    $lot = ProductLot::query()->firstOrNew([
                        'company_id' => $company->id,
                        'product_id' => $product->id,
                        'lot_number' => $lotNumber,
                    ]);

                    if (! $lot->exists) {
                        $lot->fill([
                            'tenant_id' => $company->tenant_id,
                            'branch_id' => $branch->id,
                            'warehouse_id' => $warehouse->id,
                            'tracking_type' => 'lot',
                            'received_at' => $lotDefinition['received_at'] ?? now()->toDateString(),
                            'expires_at' => $lotDefinition['expires_at'] ?? null,
                            'unit_cost' => $lotDefinition['unit_cost'] ?? $product->purchase_price,
                            'quantity_received' => $lotDefinition['quantity'],
                            'quantity_available' => $lotDefinition['quantity'],
                            'notes' => 'SECTOR-DEMO-'.$product->sku,
                        ])->save();

                        $lots++;
                    }

                    $movement = StockMovement::query()->firstOrNew([
                        'company_id' => $company->id,
                        'product_id' => $product->id,
                        'product_lot_id' => $lot->id,
                        'movement_type' => 'purchase',
                        'notes' => 'SECTOR-DEMO-LOT-'.$lotNumber,
                    ]);

                    if (! $movement->exists) {
                        $movement->fill([
                            'branch_id' => $branch->id,
                            'warehouse_id' => $warehouse->id,
                            'quantity_in' => $lotDefinition['quantity'],
                            'quantity_out' => 0,
                            'unit_cost' => $lotDefinition['unit_cost'] ?? $product->purchase_price,
                            'reason' => 'Stock demo secteur',
                            'movement_date' => Carbon::parse($lotDefinition['received_at'] ?? now()->toDateString()),
                        ])->save();

                        $stockEntries++;
                    }
                }

                continue;
            }

            $openingQuantity = (float) ($definition['opening_qty'] ?? 0);
            if ($openingQuantity <= 0) {
                continue;
            }

            $note = 'SECTOR-DEMO-'.$product->sku;
            $exists = StockMovement::query()
                ->where('company_id', $company->id)
                ->where('product_id', $product->id)
                ->where('movement_type', 'opening')
                ->where('notes', $note)
                ->exists();

            if ($exists) {
                continue;
            }

            $this->stockService->recordOpening(
                product: $product,
                companyId: $company->id,
                branchId: $branch->id,
                quantity: $openingQuantity,
                unitCost: (float) ($definition['opening_unit_cost'] ?? $product->purchase_price),
                notes: $note,
                user: null,
                movementDate: now()->subDays(2),
                warehouseId: $warehouse->id,
            );

            $stockEntries++;
        }

        return [
            'stock_entries' => $stockEntries,
            'lots' => $lots,
        ];
    }

    private function resolveBranchAndWarehouse(Company $company): array
    {
        $branch = Branch::query()
            ->where('company_id', $company->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->first();

        if (! $branch) {
            $branch = Branch::query()->create([
                'tenant_id' => $company->tenant_id,
                'company_id' => $company->id,
                'name' => 'Agence principale',
                'code' => 'HQ',
                'city' => 'Bamako',
                'address' => $company->address,
                'is_active' => true,
                'is_default' => true,
            ]);
        }

        $warehouseId = $this->stockService->defaultWarehouseId($company->id, $branch->id);
        $warehouse = Warehouse::query()->findOrFail($warehouseId);

        return [$branch, $warehouse];
    }

    private function playbooks(string $profileKey): array
    {
        return match ($profileKey) {
            'food_store' => [
                [
                    'title' => 'Vente comptoir en boutique',
                    'description' => 'Encaisser rapidement un panier mixte en especes et mobile money.',
                    'permission' => 'pos.view',
                    'route_name' => 'pos.index',
                    'action' => 'Ouvrir la caisse',
                    'steps' => [
                        'Ouvre le POS puis ajoute Jus mangue 1L et Yaourt nature 500g.',
                        'Saisis un reglement mixte Especes + Orange Money.',
                        'Controle la monnaie, le ticket et la baisse de stock immediate.',
                    ],
                ],
                [
                    'title' => 'Controle peremption rayon',
                    'description' => 'Verifier les lots proches de date et les alertes internes.',
                    'permission' => 'stock.view',
                    'route_name' => 'stock.lots',
                    'action' => 'Voir les lots',
                    'steps' => [
                        'Filtre les lots sur Yaourt nature 500g.',
                        'Repere le lot qui expire bientot.',
                        'Verifie ensuite les alertes depuis le centre notifications.',
                    ],
                ],
                [
                    'title' => 'Reassort automatique',
                    'description' => 'Piloter une suggestion de reappro sur les produits qui tournent.',
                    'permission' => 'purchase_requests.view',
                    'route_name' => 'replenishments.index',
                    'action' => 'Voir le reassort',
                    'steps' => [
                        'Observe les seuils mini sur l epicerie et les produits frais.',
                        'Genere une suggestion de demande d achat.',
                        'Controle le fournisseur recommande et le delai estime.',
                    ],
                ],
            ],
            'wholesale_distribution' => [
                [
                    'title' => 'Devis puis commande client',
                    'description' => 'Tester le cycle avant-vente grossiste avec prix volume.',
                    'permission' => 'quotes.view',
                    'route_name' => 'quotes.create',
                    'action' => 'Creer un devis',
                    'steps' => [
                        'Choisis Client semi-gros Sogoniko.',
                        'Ajoute Riz import 50kg et Huile vegetale carton 12.',
                        'Controle le tarif grossiste puis convertis en commande.',
                    ],
                ],
                [
                    'title' => 'Promesse de disponibilite',
                    'description' => 'Voir les lignes couvertes, reservees ou a risque.',
                    'permission' => 'orders.view',
                    'route_name' => 'orders.index',
                    'action' => 'Voir les commandes',
                    'steps' => [
                        'Ouvre une commande client.',
                        'Observe l ATP, les quantites entrantes et la date attendue.',
                        'Genere une demande d achat depuis une ligne non couverte.',
                    ],
                ],
                [
                    'title' => 'Pilotage agences et marges',
                    'description' => 'Comparer volume, top clients et performance commerciale.',
                    'permission' => 'reports.view',
                    'route_name' => 'reports.index',
                    'action' => 'Ouvrir les rapports',
                    'steps' => [
                        'Affiche les ventes par agence.',
                        'Controle les top clients et produits dormants.',
                        'Compare la periode avec le mois precedent.',
                    ],
                ],
            ],
            'hardware_store' => [
                [
                    'title' => 'Recherche catalogue technique',
                    'description' => 'Tester les references techniques et prix chantier.',
                    'permission' => 'products.view',
                    'route_name' => 'products.index',
                    'action' => 'Voir le catalogue',
                    'steps' => [
                        'Recherche Cable electrique 2.5 mm.',
                        'Controle le conditionnement rouleau et boite.',
                        'Observe les fournisseurs preferes sur la fiche produit.',
                    ],
                ],
                [
                    'title' => 'Devis chantier',
                    'description' => 'Monter rapidement un devis avec plusieurs references.',
                    'permission' => 'quotes.view',
                    'route_name' => 'quotes.create',
                    'action' => 'Creer un devis',
                    'steps' => [
                        'Ajoute Perceuse 710W et Vis autoforeuses.',
                        'Choisis le tarif chantier.',
                        'Partage ensuite le devis via le portail client.',
                    ],
                ],
                [
                    'title' => 'Reappro fournisseur prefere',
                    'description' => 'Verifier les recommandations automatiques achat.',
                    'permission' => 'purchase_requests.view',
                    'route_name' => 'purchase-requests.index',
                    'action' => 'Voir les demandes achats',
                    'steps' => [
                        'Ouvre une demande ou une suggestion de reassort.',
                        'Observe le fournisseur recommande par reference.',
                        'Convertis vers une commande fournisseur.',
                    ],
                ],
            ],
            'pharmacy_parapharmacy' => [
                [
                    'title' => 'Controle lots et peremption',
                    'description' => 'Surveiller les lots proches ou expires.',
                    'permission' => 'stock.view',
                    'route_name' => 'stock.lots',
                    'action' => 'Ouvrir les lots',
                    'steps' => [
                        'Filtre sur Paracetamol 500mg.',
                        'Observe les lots avec date de peremption.',
                        'Controle l etat expiring puis les alertes associees.',
                    ],
                ],
                [
                    'title' => 'Vente comptoir produit trace',
                    'description' => 'Tester une sortie de stock FEFO sur un produit sensible.',
                    'permission' => 'sales.view',
                    'route_name' => 'sales.create',
                    'action' => 'Creer une vente',
                    'steps' => [
                        'Ajoute Serum physiologique 500ml.',
                        'Valide la vente sur stock trace disponible.',
                        'Controle ensuite le mouvement FEFO dans le stock.',
                    ],
                ],
                [
                    'title' => 'Rupture et reassort securise',
                    'description' => 'Piloter le stock critique et le fournisseur recommande.',
                    'permission' => 'purchase_requests.view',
                    'route_name' => 'replenishments.index',
                    'action' => 'Voir le reassort',
                    'steps' => [
                        'Repere les produits sensibles sous mini.',
                        'Genere une demande d achat.',
                        'Controle le delai fournisseur et le cout recommande.',
                    ],
                ],
            ],
            'cosmetics_beauty' => [
                [
                    'title' => 'POS retail premium',
                    'description' => 'Tester une vente rapide avec coffret et paiement mobile.',
                    'permission' => 'pos.view',
                    'route_name' => 'pos.index',
                    'action' => 'Ouvrir le POS',
                    'steps' => [
                        'Ajoute Coffret eclat et Brume parfumee.',
                        'Saisis un paiement Wave ou Orange Money.',
                        'Verifie le ticket et la mise a jour des ventes du jour.',
                    ],
                ],
                [
                    'title' => 'Catalogue VIP et promo',
                    'description' => 'Comparer les listes de prix boutique, promo et VIP.',
                    'permission' => 'settings.view',
                    'route_name' => 'settings.index',
                    'action' => 'Voir les parametres',
                    'steps' => [
                        'Ouvre les listes de prix.',
                        'Controle les lignes tarifaires du coffret et du serum.',
                        'Compare detail, promo et VIP.',
                    ],
                ],
                [
                    'title' => 'Top ventes boutique',
                    'description' => 'Piloter la rotation des produits premium.',
                    'permission' => 'reports.view',
                    'route_name' => 'reports.index',
                    'action' => 'Voir les rapports',
                    'steps' => [
                        'Affiche le top produits.',
                        'Observe les marges par categorie.',
                        'Identifie les produits dormants a pousser en promo.',
                    ],
                ],
            ],
            default => [
                [
                    'title' => 'Catalogue et ventes',
                    'description' => 'Parcourir les produits de demo puis lancer une premiere vente.',
                    'permission' => 'products.view',
                    'route_name' => 'products.index',
                    'action' => 'Voir le catalogue',
                    'steps' => [
                        'Controle les categories et prix detail / gros.',
                        'Ouvre ensuite une vente ou un devis.',
                        'Observe la baisse de stock et les alertes eventuelles.',
                    ],
                ],
                [
                    'title' => 'Achats et fournisseurs',
                    'description' => 'Verifier les infos fournisseur par produit et le reassort.',
                    'permission' => 'purchase_requests.view',
                    'route_name' => 'replenishments.index',
                    'action' => 'Voir le reassort',
                    'steps' => [
                        'Repere les produits avec fournisseur prefere.',
                        'Genere une demande d achat.',
                        'Controle le cout et le delai recommandes.',
                    ],
                ],
                [
                    'title' => 'Pilotage dirigeant',
                    'description' => 'Lire la performance commerciale sur les rapports.',
                    'permission' => 'reports.view',
                    'route_name' => 'reports.index',
                    'action' => 'Ouvrir les rapports',
                    'steps' => [
                        'Observe ventes par agence.',
                        'Controle top clients et marge par categorie.',
                        'Compare la periode au mois precedent.',
                    ],
                ],
            ],
        };
    }

    private function blueprint(string $profileKey): array
    {
        return match ($profileKey) {
            'food_store' => [
                'suppliers' => [
                    [
                        'code' => 'DEMO-FOU-ALIM-01',
                        'name' => 'Sahel Boissons Distribution',
                        'phone' => '70010001',
                        'city' => 'Bamako',
                        'address' => 'Zone industrielle, Bamako',
                        'payment_term_code' => 'CPT',
                        'notes' => 'Fournisseur demo boissons et produits frais.',
                        'mobile_wallets' => [
                            ['provider' => 'Orange Money', 'wallet_number' => '70010001', 'account_name' => 'Sahel Boissons Distribution', 'is_primary' => true],
                        ],
                    ],
                    [
                        'code' => 'DEMO-FOU-ALIM-02',
                        'name' => 'Mali Epicerie Import',
                        'phone' => '70010002',
                        'city' => 'Bamako',
                        'address' => 'Yirimadio, Bamako',
                        'payment_term_code' => 'TERM-7',
                        'notes' => 'Fournisseur demo epicerie et surgele.',
                        'bank_accounts' => [
                            ['bank_name' => 'BDM', 'account_number' => 'ML-ALIM-002', 'account_name' => 'Mali Epicerie Import', 'is_primary' => true],
                        ],
                    ],
                ],
                'customers' => [
                    [
                        'code' => 'DEMO-CLI-ALIM-01',
                        'name' => 'Client quartier Hippodrome',
                        'phone' => '76020001',
                        'city' => 'Bamako',
                        'address' => 'Hippodrome, Bamako',
                        'payment_term_code' => 'CPT',
                        'price_list_code' => 'DETAIL',
                        'notes' => 'Client demo comptoir.',
                    ],
                    [
                        'code' => 'DEMO-CLI-ALIM-02',
                        'name' => 'Mini market ACI 2000',
                        'phone' => '76020002',
                        'city' => 'Bamako',
                        'address' => 'ACI 2000, Bamako',
                        'payment_term_code' => 'TERM-7',
                        'price_list_code' => 'DEMIGROS',
                        'notes' => 'Client demo livraison courte.',
                        'mobile_wallets' => [
                            ['provider' => 'Wave', 'wallet_number' => '76020002', 'account_name' => 'Mini market ACI 2000', 'is_primary' => true],
                        ],
                    ],
                ],
                'products' => [
                    [
                        'sku' => 'DEMO-FD-001',
                        'barcode' => '8901000000011',
                        'name' => 'Jus mangue 1L',
                        'category' => 'Boissons',
                        'unit' => 'unite',
                        'sales_unit_name' => 'Pack',
                        'sales_unit_ratio' => 6,
                        'purchase_unit_name' => 'Carton',
                        'purchase_unit_ratio' => 12,
                        'sale_price' => 1200,
                        'purchase_price' => 760,
                        'min_stock' => 18,
                        'reorder_max_qty' => 72,
                        'reorder_multiple_qty' => 12,
                        'purchase_lead_time_days' => 2,
                        'description' => 'Reference demo boutique rotation rapide.',
                        'opening_qty' => 48,
                        'opening_unit_cost' => 760,
                        'price_lists' => [
                            ['code' => 'DETAIL', 'min_qty' => 1, 'price' => 1200],
                            ['code' => 'DEMIGROS', 'min_qty' => 6, 'price' => 1100],
                            ['code' => 'GROSSISTE', 'min_qty' => 24, 'price' => 990],
                        ],
                        'supplier_infos' => [
                            ['supplier_code' => 'DEMO-FOU-ALIM-01', 'min_qty' => 12, 'unit_cost' => 760, 'lead_time_days' => 2, 'is_preferred' => true],
                        ],
                    ],
                    [
                        'sku' => 'DEMO-FD-002',
                        'barcode' => '8901000000012',
                        'name' => 'Yaourt nature 500g',
                        'category' => 'Produits frais',
                        'unit' => 'pot',
                        'tracking_type' => 'lot',
                        'sale_price' => 850,
                        'purchase_price' => 540,
                        'min_stock' => 10,
                        'reorder_max_qty' => 36,
                        'reorder_multiple_qty' => 6,
                        'purchase_lead_time_days' => 1,
                        'internal_notes' => 'Produit sensible a date courte.',
                        'price_lists' => [
                            ['code' => 'DETAIL', 'min_qty' => 1, 'price' => 850],
                            ['code' => 'DEMIGROS', 'min_qty' => 6, 'price' => 780],
                        ],
                        'supplier_infos' => [
                            ['supplier_code' => 'DEMO-FOU-ALIM-01', 'min_qty' => 6, 'unit_cost' => 540, 'lead_time_days' => 1, 'is_preferred' => true],
                        ],
                        'lots' => [
                            ['lot_number' => 'LOT-YAOURT-A1', 'quantity' => 12, 'unit_cost' => 540, 'received_at' => now()->subDays(4)->toDateString(), 'expires_at' => now()->addDays(8)->toDateString()],
                            ['lot_number' => 'LOT-YAOURT-B1', 'quantity' => 18, 'unit_cost' => 540, 'received_at' => now()->subDays(1)->toDateString(), 'expires_at' => now()->addDays(18)->toDateString()],
                        ],
                    ],
                    [
                        'sku' => 'DEMO-FD-003',
                        'barcode' => '8901000000013',
                        'name' => 'Riz brise 25kg',
                        'category' => 'Epicerie',
                        'unit' => 'sac',
                        'purchase_unit_name' => 'Palette',
                        'purchase_unit_ratio' => 20,
                        'sale_price' => 18500,
                        'purchase_price' => 15400,
                        'min_stock' => 8,
                        'reorder_max_qty' => 40,
                        'reorder_multiple_qty' => 5,
                        'purchase_lead_time_days' => 4,
                        'opening_qty' => 20,
                        'opening_unit_cost' => 15400,
                        'price_lists' => [
                            ['code' => 'DETAIL', 'min_qty' => 1, 'price' => 18500],
                            ['code' => 'DEMIGROS', 'min_qty' => 5, 'price' => 17600],
                            ['code' => 'GROSSISTE', 'min_qty' => 10, 'price' => 16950],
                        ],
                        'supplier_infos' => [
                            ['supplier_code' => 'DEMO-FOU-ALIM-02', 'min_qty' => 5, 'unit_cost' => 15400, 'lead_time_days' => 4, 'is_preferred' => true],
                        ],
                    ],
                    [
                        'sku' => 'DEMO-FD-004',
                        'barcode' => '8901000000014',
                        'name' => 'Poulet surgele 1kg',
                        'category' => 'Surgeles',
                        'unit' => 'sachet',
                        'tracking_type' => 'lot',
                        'sale_price' => 3900,
                        'purchase_price' => 2800,
                        'min_stock' => 12,
                        'reorder_max_qty' => 48,
                        'reorder_multiple_qty' => 6,
                        'purchase_lead_time_days' => 3,
                        'price_lists' => [
                            ['code' => 'DETAIL', 'min_qty' => 1, 'price' => 3900],
                            ['code' => 'DEMIGROS', 'min_qty' => 6, 'price' => 3600],
                        ],
                        'supplier_infos' => [
                            ['supplier_code' => 'DEMO-FOU-ALIM-02', 'min_qty' => 6, 'unit_cost' => 2800, 'lead_time_days' => 3, 'is_preferred' => true],
                        ],
                        'lots' => [
                            ['lot_number' => 'LOT-SURG-2401', 'quantity' => 16, 'unit_cost' => 2800, 'received_at' => now()->subDays(6)->toDateString(), 'expires_at' => now()->addMonths(4)->toDateString()],
                            ['lot_number' => 'LOT-SURG-2402', 'quantity' => 14, 'unit_cost' => 2800, 'received_at' => now()->subDays(2)->toDateString(), 'expires_at' => now()->addMonths(5)->toDateString()],
                        ],
                    ],
                ],
            ],
            'wholesale_distribution' => [
                'suppliers' => [
                    [
                        'code' => 'DEMO-FOU-GROS-01',
                        'name' => 'Sahel Agro Import',
                        'phone' => '70130001',
                        'city' => 'Bamako',
                        'address' => 'Faladie, Bamako',
                        'payment_term_code' => 'TERM-15',
                        'bank_accounts' => [
                            ['bank_name' => 'BOA', 'account_number' => 'ML-GROS-001', 'account_name' => 'Sahel Agro Import', 'is_primary' => true],
                        ],
                    ],
                    [
                        'code' => 'DEMO-FOU-GROS-02',
                        'name' => 'LogiTrans Distribution',
                        'phone' => '70130002',
                        'city' => 'Sikasso',
                        'address' => 'Route de Sikasso',
                        'payment_term_code' => 'TERM-30',
                        'notes' => 'Fournisseur demo conditionnements et logistique.',
                    ],
                ],
                'customers' => [
                    [
                        'code' => 'DEMO-CLI-GROS-01',
                        'name' => 'Client semi-gros Sogoniko',
                        'phone' => '76140001',
                        'city' => 'Bamako',
                        'address' => 'Sogoniko, Bamako',
                        'payment_term_code' => 'TERM-15',
                        'price_list_code' => 'SEMIGROS',
                    ],
                    [
                        'code' => 'DEMO-CLI-GROS-02',
                        'name' => 'Distributeur regional Kayes',
                        'phone' => '76140002',
                        'city' => 'Kayes',
                        'address' => 'Zone depot, Kayes',
                        'payment_term_code' => 'TERM-30',
                        'price_list_code' => 'GROSSISTE',
                    ],
                ],
                'products' => [
                    [
                        'sku' => 'DEMO-GR-001',
                        'barcode' => '8901000001011',
                        'name' => 'Riz import 50kg',
                        'category' => 'Produits gros',
                        'unit' => 'sac',
                        'purchase_unit_name' => 'Palette',
                        'purchase_unit_ratio' => 20,
                        'sale_price' => 32500,
                        'purchase_price' => 28750,
                        'min_stock' => 15,
                        'reorder_max_qty' => 80,
                        'reorder_multiple_qty' => 5,
                        'purchase_lead_time_days' => 6,
                        'opening_qty' => 36,
                        'opening_unit_cost' => 28750,
                        'price_lists' => [
                            ['code' => 'DETAIL', 'min_qty' => 1, 'price' => 32500],
                            ['code' => 'SEMIGROS', 'min_qty' => 5, 'price' => 31400],
                            ['code' => 'GROSSISTE', 'min_qty' => 15, 'price' => 30200],
                        ],
                        'supplier_infos' => [
                            ['supplier_code' => 'DEMO-FOU-GROS-01', 'min_qty' => 10, 'unit_cost' => 28750, 'lead_time_days' => 6, 'is_preferred' => true],
                        ],
                    ],
                    [
                        'sku' => 'DEMO-GR-002',
                        'barcode' => '8901000001012',
                        'name' => 'Huile vegetale carton 12',
                        'category' => 'Conditionnements',
                        'unit' => 'carton',
                        'sales_unit_name' => 'Pack',
                        'sales_unit_ratio' => 4,
                        'purchase_unit_name' => 'Palette',
                        'purchase_unit_ratio' => 30,
                        'sale_price' => 18800,
                        'purchase_price' => 16100,
                        'min_stock' => 20,
                        'reorder_max_qty' => 90,
                        'reorder_multiple_qty' => 6,
                        'purchase_lead_time_days' => 5,
                        'opening_qty' => 42,
                        'opening_unit_cost' => 16100,
                        'price_lists' => [
                            ['code' => 'DETAIL', 'min_qty' => 1, 'price' => 18800],
                            ['code' => 'SEMIGROS', 'min_qty' => 4, 'price' => 17900],
                            ['code' => 'GROSSISTE', 'min_qty' => 12, 'price' => 17150],
                        ],
                        'supplier_infos' => [
                            ['supplier_code' => 'DEMO-FOU-GROS-01', 'min_qty' => 6, 'unit_cost' => 16100, 'lead_time_days' => 5, 'is_preferred' => true],
                            ['supplier_code' => 'DEMO-FOU-GROS-02', 'min_qty' => 12, 'unit_cost' => 16250, 'lead_time_days' => 4, 'is_preferred' => false],
                        ],
                    ],
                    [
                        'sku' => 'DEMO-GR-003',
                        'barcode' => '8901000001013',
                        'name' => 'Sucre 1kg carton 20',
                        'category' => 'Produits detail',
                        'unit' => 'carton',
                        'sale_price' => 21400,
                        'purchase_price' => 18750,
                        'min_stock' => 18,
                        'reorder_max_qty' => 70,
                        'reorder_multiple_qty' => 5,
                        'purchase_lead_time_days' => 5,
                        'opening_qty' => 28,
                        'opening_unit_cost' => 18750,
                        'price_lists' => [
                            ['code' => 'DETAIL', 'min_qty' => 1, 'price' => 21400],
                            ['code' => 'SEMIGROS', 'min_qty' => 5, 'price' => 20400],
                            ['code' => 'GROSSISTE', 'min_qty' => 15, 'price' => 19600],
                        ],
                        'supplier_infos' => [
                            ['supplier_code' => 'DEMO-FOU-GROS-02', 'min_qty' => 5, 'unit_cost' => 18750, 'lead_time_days' => 5, 'is_preferred' => true],
                        ],
                    ],
                    [
                        'sku' => 'DEMO-GR-004',
                        'barcode' => '8901000001014',
                        'name' => 'Frais logistiques livraison regionale',
                        'category' => 'Services logistiques',
                        'unit' => 'service',
                        'type' => 'service',
                        'sale_price' => 25000,
                        'purchase_price' => 0,
                        'min_stock' => 0,
                        'auto_replenish' => false,
                        'sale_ok' => true,
                        'purchase_ok' => false,
                        'price_lists' => [
                            ['code' => 'DETAIL', 'min_qty' => 1, 'price' => 25000],
                            ['code' => 'SEMIGROS', 'min_qty' => 1, 'price' => 22000],
                            ['code' => 'GROSSISTE', 'min_qty' => 1, 'price' => 20000],
                        ],
                    ],
                ],
            ],
            'hardware_store' => [
                'suppliers' => [
                    [
                        'code' => 'DEMO-FOU-QUI-01',
                        'name' => 'Atelier Technique Bamako',
                        'phone' => '70250001',
                        'city' => 'Bamako',
                        'address' => 'Marché Rail Da, Bamako',
                        'payment_term_code' => 'TERM-15',
                    ],
                    [
                        'code' => 'DEMO-FOU-QUI-02',
                        'name' => 'Import Materiaux Pro',
                        'phone' => '70250002',
                        'city' => 'Bamako',
                        'address' => 'Sotuba ACI, Bamako',
                        'payment_term_code' => 'CPT',
                        'bank_accounts' => [
                            ['bank_name' => 'Ecobank', 'account_number' => 'ML-QUI-002', 'account_name' => 'Import Materiaux Pro', 'is_primary' => true],
                        ],
                    ],
                ],
                'customers' => [
                    [
                        'code' => 'DEMO-CLI-QUI-01',
                        'name' => 'Entreprise chantier Faso BTP',
                        'phone' => '76260001',
                        'city' => 'Bamako',
                        'address' => 'Route de Koulikoro',
                        'payment_term_code' => 'TERM-15',
                        'price_list_code' => 'CHANTIER',
                    ],
                    [
                        'code' => 'DEMO-CLI-QUI-02',
                        'name' => 'Revendeur outillage Medina',
                        'phone' => '76260002',
                        'city' => 'Bamako',
                        'address' => 'Medina Coura',
                        'payment_term_code' => 'CPT',
                        'price_list_code' => 'REVENDEUR',
                    ],
                ],
                'products' => [
                    [
                        'sku' => 'DEMO-QI-001',
                        'barcode' => '8901000002011',
                        'name' => 'Perceuse 710W',
                        'category' => 'Outillage',
                        'unit' => 'piece',
                        'sale_price' => 38500,
                        'purchase_price' => 29200,
                        'min_stock' => 4,
                        'reorder_max_qty' => 18,
                        'reorder_multiple_qty' => 2,
                        'purchase_lead_time_days' => 7,
                        'opening_qty' => 7,
                        'opening_unit_cost' => 29200,
                        'price_lists' => [
                            ['code' => 'DETAIL', 'min_qty' => 1, 'price' => 38500],
                            ['code' => 'CHANTIER', 'min_qty' => 2, 'price' => 36900],
                            ['code' => 'REVENDEUR', 'min_qty' => 3, 'price' => 35200],
                        ],
                        'supplier_infos' => [
                            ['supplier_code' => 'DEMO-FOU-QUI-01', 'min_qty' => 2, 'unit_cost' => 29200, 'lead_time_days' => 7, 'is_preferred' => true],
                        ],
                    ],
                    [
                        'sku' => 'DEMO-QI-002',
                        'barcode' => '8901000002012',
                        'name' => 'Cable electrique 2.5 mm rouleau',
                        'category' => 'Electricite',
                        'unit' => 'rouleau',
                        'sale_price' => 14200,
                        'purchase_price' => 11350,
                        'min_stock' => 10,
                        'reorder_max_qty' => 45,
                        'reorder_multiple_qty' => 5,
                        'purchase_lead_time_days' => 5,
                        'opening_qty' => 18,
                        'opening_unit_cost' => 11350,
                        'price_lists' => [
                            ['code' => 'DETAIL', 'min_qty' => 1, 'price' => 14200],
                            ['code' => 'CHANTIER', 'min_qty' => 5, 'price' => 13550],
                            ['code' => 'REVENDEUR', 'min_qty' => 10, 'price' => 12900],
                        ],
                        'supplier_infos' => [
                            ['supplier_code' => 'DEMO-FOU-QUI-02', 'min_qty' => 5, 'unit_cost' => 11350, 'lead_time_days' => 5, 'is_preferred' => true],
                        ],
                    ],
                    [
                        'sku' => 'DEMO-QI-003',
                        'barcode' => '8901000002013',
                        'name' => 'Vis autoforeuses boite 500',
                        'category' => 'Fixations',
                        'unit' => 'boite',
                        'sale_price' => 6900,
                        'purchase_price' => 4700,
                        'min_stock' => 12,
                        'reorder_max_qty' => 60,
                        'reorder_multiple_qty' => 6,
                        'purchase_lead_time_days' => 4,
                        'opening_qty' => 26,
                        'opening_unit_cost' => 4700,
                        'price_lists' => [
                            ['code' => 'DETAIL', 'min_qty' => 1, 'price' => 6900],
                            ['code' => 'CHANTIER', 'min_qty' => 6, 'price' => 6200],
                            ['code' => 'REVENDEUR', 'min_qty' => 12, 'price' => 5900],
                        ],
                        'supplier_infos' => [
                            ['supplier_code' => 'DEMO-FOU-QUI-01', 'min_qty' => 6, 'unit_cost' => 4700, 'lead_time_days' => 4, 'is_preferred' => true],
                        ],
                    ],
                    [
                        'sku' => 'DEMO-QI-004',
                        'barcode' => '8901000002014',
                        'name' => 'Flexible plomberie 50 cm',
                        'category' => 'Plomberie',
                        'unit' => 'unite',
                        'sale_price' => 3200,
                        'purchase_price' => 2150,
                        'min_stock' => 20,
                        'reorder_max_qty' => 80,
                        'reorder_multiple_qty' => 10,
                        'purchase_lead_time_days' => 3,
                        'opening_qty' => 40,
                        'opening_unit_cost' => 2150,
                        'price_lists' => [
                            ['code' => 'DETAIL', 'min_qty' => 1, 'price' => 3200],
                            ['code' => 'CHANTIER', 'min_qty' => 10, 'price' => 2950],
                            ['code' => 'REVENDEUR', 'min_qty' => 20, 'price' => 2780],
                        ],
                        'supplier_infos' => [
                            ['supplier_code' => 'DEMO-FOU-QUI-02', 'min_qty' => 10, 'unit_cost' => 2150, 'lead_time_days' => 3, 'is_preferred' => true],
                        ],
                    ],
                ],
            ],
            'pharmacy_parapharmacy' => [
                'suppliers' => [
                    [
                        'code' => 'DEMO-FOU-PHA-01',
                        'name' => 'MediSupply Mali',
                        'phone' => '70370001',
                        'city' => 'Bamako',
                        'address' => 'ACI 2000, Bamako',
                        'payment_term_code' => 'TERM-30',
                        'bank_accounts' => [
                            ['bank_name' => 'BMS', 'account_number' => 'ML-PHA-001', 'account_name' => 'MediSupply Mali', 'is_primary' => true],
                        ],
                    ],
                    [
                        'code' => 'DEMO-FOU-PHA-02',
                        'name' => 'Sante Plus Distribution',
                        'phone' => '70370002',
                        'city' => 'Bamako',
                        'address' => 'Missira, Bamako',
                        'payment_term_code' => 'CPT',
                    ],
                ],
                'customers' => [
                    [
                        'code' => 'DEMO-CLI-PHA-01',
                        'name' => 'Cabinet Medical Horizon',
                        'phone' => '76380001',
                        'city' => 'Bamako',
                        'address' => 'Hamdallaye ACI',
                        'payment_term_code' => 'TERM-30',
                        'price_list_code' => 'CLINIQUE',
                    ],
                    [
                        'code' => 'DEMO-CLI-PHA-02',
                        'name' => 'Patient comptoir demo',
                        'phone' => '76380002',
                        'city' => 'Bamako',
                        'address' => 'Badalabougou',
                        'payment_term_code' => 'CPT',
                        'price_list_code' => 'DETAIL',
                    ],
                ],
                'products' => [
                    [
                        'sku' => 'DEMO-PH-001',
                        'barcode' => '8901000003011',
                        'name' => 'Paracetamol 500mg',
                        'category' => 'Prescription',
                        'unit' => 'boite',
                        'tracking_type' => 'lot',
                        'sale_price' => 1800,
                        'purchase_price' => 1050,
                        'min_stock' => 20,
                        'reorder_max_qty' => 120,
                        'reorder_multiple_qty' => 10,
                        'purchase_lead_time_days' => 7,
                        'price_lists' => [
                            ['code' => 'DETAIL', 'min_qty' => 1, 'price' => 1800],
                            ['code' => 'CLINIQUE', 'min_qty' => 10, 'price' => 1600],
                        ],
                        'supplier_infos' => [
                            ['supplier_code' => 'DEMO-FOU-PHA-01', 'min_qty' => 10, 'unit_cost' => 1050, 'lead_time_days' => 7, 'is_preferred' => true],
                        ],
                        'lots' => [
                            ['lot_number' => 'LOT-PARA-2401', 'quantity' => 40, 'unit_cost' => 1050, 'received_at' => now()->subDays(20)->toDateString(), 'expires_at' => now()->addMonths(6)->toDateString()],
                            ['lot_number' => 'LOT-PARA-2402', 'quantity' => 24, 'unit_cost' => 1050, 'received_at' => now()->subDays(5)->toDateString(), 'expires_at' => now()->addMonths(10)->toDateString()],
                        ],
                    ],
                    [
                        'sku' => 'DEMO-PH-002',
                        'barcode' => '8901000003012',
                        'name' => 'Serum physiologique 500ml',
                        'category' => 'Produits sensibles',
                        'unit' => 'flacon',
                        'tracking_type' => 'lot',
                        'sale_price' => 2400,
                        'purchase_price' => 1580,
                        'min_stock' => 10,
                        'reorder_max_qty' => 50,
                        'reorder_multiple_qty' => 5,
                        'purchase_lead_time_days' => 5,
                        'price_lists' => [
                            ['code' => 'DETAIL', 'min_qty' => 1, 'price' => 2400],
                            ['code' => 'CLINIQUE', 'min_qty' => 6, 'price' => 2180],
                        ],
                        'supplier_infos' => [
                            ['supplier_code' => 'DEMO-FOU-PHA-02', 'min_qty' => 5, 'unit_cost' => 1580, 'lead_time_days' => 5, 'is_preferred' => true],
                        ],
                        'lots' => [
                            ['lot_number' => 'LOT-SERUM-2401', 'quantity' => 18, 'unit_cost' => 1580, 'received_at' => now()->subDays(12)->toDateString(), 'expires_at' => now()->addMonths(5)->toDateString()],
                            ['lot_number' => 'LOT-SERUM-2402', 'quantity' => 12, 'unit_cost' => 1580, 'received_at' => now()->subDays(2)->toDateString(), 'expires_at' => now()->addMonths(7)->toDateString()],
                        ],
                    ],
                    [
                        'sku' => 'DEMO-PH-003',
                        'barcode' => '8901000003013',
                        'name' => 'Gel hydroalcoolique 250ml',
                        'category' => 'Hygiene',
                        'unit' => 'flacon',
                        'tracking_type' => 'lot',
                        'sale_price' => 2900,
                        'purchase_price' => 1750,
                        'min_stock' => 12,
                        'reorder_max_qty' => 60,
                        'reorder_multiple_qty' => 6,
                        'purchase_lead_time_days' => 4,
                        'price_lists' => [
                            ['code' => 'DETAIL', 'min_qty' => 1, 'price' => 2900],
                            ['code' => 'CLINIQUE', 'min_qty' => 6, 'price' => 2620],
                        ],
                        'supplier_infos' => [
                            ['supplier_code' => 'DEMO-FOU-PHA-02', 'min_qty' => 6, 'unit_cost' => 1750, 'lead_time_days' => 4, 'is_preferred' => true],
                        ],
                        'lots' => [
                            ['lot_number' => 'LOT-GEL-2401', 'quantity' => 20, 'unit_cost' => 1750, 'received_at' => now()->subDays(7)->toDateString(), 'expires_at' => now()->addMonths(9)->toDateString()],
                        ],
                    ],
                    [
                        'sku' => 'DEMO-PH-004',
                        'barcode' => '8901000003014',
                        'name' => 'Vitamine C effervescente',
                        'category' => 'Parapharmacie',
                        'unit' => 'boite',
                        'tracking_type' => 'lot',
                        'sale_price' => 4200,
                        'purchase_price' => 3100,
                        'min_stock' => 8,
                        'reorder_max_qty' => 36,
                        'reorder_multiple_qty' => 4,
                        'purchase_lead_time_days' => 6,
                        'price_lists' => [
                            ['code' => 'DETAIL', 'min_qty' => 1, 'price' => 4200],
                            ['code' => 'CLINIQUE', 'min_qty' => 4, 'price' => 3900],
                        ],
                        'supplier_infos' => [
                            ['supplier_code' => 'DEMO-FOU-PHA-01', 'min_qty' => 4, 'unit_cost' => 3100, 'lead_time_days' => 6, 'is_preferred' => true],
                        ],
                        'lots' => [
                            ['lot_number' => 'LOT-VITC-2401', 'quantity' => 14, 'unit_cost' => 3100, 'received_at' => now()->subDays(15)->toDateString(), 'expires_at' => now()->addMonths(11)->toDateString()],
                        ],
                    ],
                ],
            ],
            'cosmetics_beauty' => [
                'suppliers' => [
                    [
                        'code' => 'DEMO-FOU-COS-01',
                        'name' => 'Beauty Import Mali',
                        'phone' => '70490001',
                        'city' => 'Bamako',
                        'address' => 'ACI 2000, Bamako',
                        'payment_term_code' => 'CPT',
                    ],
                    [
                        'code' => 'DEMO-FOU-COS-02',
                        'name' => 'Parfum & Soin Distribution',
                        'phone' => '70490002',
                        'city' => 'Bamako',
                        'address' => 'Hamdallaye, Bamako',
                        'payment_term_code' => 'TERM-7',
                        'mobile_wallets' => [
                            ['provider' => 'Wave', 'wallet_number' => '70490002', 'account_name' => 'Parfum & Soin Distribution', 'is_primary' => true],
                        ],
                    ],
                ],
                'customers' => [
                    [
                        'code' => 'DEMO-CLI-COS-01',
                        'name' => 'Cliente VIP ACI',
                        'phone' => '76500001',
                        'city' => 'Bamako',
                        'address' => 'ACI 2000',
                        'payment_term_code' => 'CPT',
                        'price_list_code' => 'VIP',
                    ],
                    [
                        'code' => 'DEMO-CLI-COS-02',
                        'name' => 'Influenceuse boutique demo',
                        'phone' => '76500002',
                        'city' => 'Bamako',
                        'address' => 'Badalabougou',
                        'payment_term_code' => 'TERM-7',
                        'price_list_code' => 'PROMO',
                    ],
                ],
                'products' => [
                    [
                        'sku' => 'DEMO-CS-001',
                        'barcode' => '8901000004011',
                        'name' => 'Serum eclat 30ml',
                        'category' => 'Soins visage',
                        'unit' => 'unite',
                        'tracking_type' => 'lot',
                        'sale_price' => 12900,
                        'purchase_price' => 8600,
                        'min_stock' => 6,
                        'reorder_max_qty' => 24,
                        'reorder_multiple_qty' => 3,
                        'purchase_lead_time_days' => 8,
                        'price_lists' => [
                            ['code' => 'DETAIL', 'min_qty' => 1, 'price' => 12900],
                            ['code' => 'PROMO', 'min_qty' => 1, 'price' => 11900],
                            ['code' => 'VIP', 'min_qty' => 1, 'price' => 11250],
                        ],
                        'supplier_infos' => [
                            ['supplier_code' => 'DEMO-FOU-COS-01', 'min_qty' => 3, 'unit_cost' => 8600, 'lead_time_days' => 8, 'is_preferred' => true],
                        ],
                        'lots' => [
                            ['lot_number' => 'LOT-SERUM-EC-01', 'quantity' => 10, 'unit_cost' => 8600, 'received_at' => now()->subDays(10)->toDateString(), 'expires_at' => now()->addMonths(10)->toDateString()],
                        ],
                    ],
                    [
                        'sku' => 'DEMO-CS-002',
                        'barcode' => '8901000004012',
                        'name' => 'Brume parfumee 100ml',
                        'category' => 'Parfums',
                        'unit' => 'unite',
                        'tracking_type' => 'lot',
                        'sale_price' => 9800,
                        'purchase_price' => 6400,
                        'min_stock' => 8,
                        'reorder_max_qty' => 30,
                        'reorder_multiple_qty' => 4,
                        'purchase_lead_time_days' => 6,
                        'price_lists' => [
                            ['code' => 'DETAIL', 'min_qty' => 1, 'price' => 9800],
                            ['code' => 'PROMO', 'min_qty' => 1, 'price' => 9100],
                            ['code' => 'VIP', 'min_qty' => 1, 'price' => 8750],
                        ],
                        'supplier_infos' => [
                            ['supplier_code' => 'DEMO-FOU-COS-02', 'min_qty' => 4, 'unit_cost' => 6400, 'lead_time_days' => 6, 'is_preferred' => true],
                        ],
                        'lots' => [
                            ['lot_number' => 'LOT-BRUME-01', 'quantity' => 14, 'unit_cost' => 6400, 'received_at' => now()->subDays(6)->toDateString(), 'expires_at' => now()->addMonths(12)->toDateString()],
                        ],
                    ],
                    [
                        'sku' => 'DEMO-CS-003',
                        'barcode' => '8901000004013',
                        'name' => 'Lait corps nourrissant',
                        'category' => 'Soins corps',
                        'unit' => 'unite',
                        'tracking_type' => 'lot',
                        'sale_price' => 7500,
                        'purchase_price' => 4700,
                        'min_stock' => 10,
                        'reorder_max_qty' => 32,
                        'reorder_multiple_qty' => 4,
                        'purchase_lead_time_days' => 5,
                        'price_lists' => [
                            ['code' => 'DETAIL', 'min_qty' => 1, 'price' => 7500],
                            ['code' => 'PROMO', 'min_qty' => 1, 'price' => 6900],
                            ['code' => 'VIP', 'min_qty' => 1, 'price' => 6650],
                        ],
                        'supplier_infos' => [
                            ['supplier_code' => 'DEMO-FOU-COS-01', 'min_qty' => 4, 'unit_cost' => 4700, 'lead_time_days' => 5, 'is_preferred' => true],
                        ],
                        'lots' => [
                            ['lot_number' => 'LOT-LAIT-01', 'quantity' => 16, 'unit_cost' => 4700, 'received_at' => now()->subDays(8)->toDateString(), 'expires_at' => now()->addMonths(9)->toDateString()],
                        ],
                    ],
                    [
                        'sku' => 'DEMO-CS-004',
                        'barcode' => '8901000004014',
                        'name' => 'Coffret eclat premium',
                        'category' => 'Coffrets',
                        'unit' => 'coffret',
                        'sale_price' => 24900,
                        'purchase_price' => 16800,
                        'min_stock' => 3,
                        'reorder_max_qty' => 12,
                        'reorder_multiple_qty' => 2,
                        'purchase_lead_time_days' => 7,
                        'opening_qty' => 6,
                        'opening_unit_cost' => 16800,
                        'price_lists' => [
                            ['code' => 'DETAIL', 'min_qty' => 1, 'price' => 24900],
                            ['code' => 'PROMO', 'min_qty' => 1, 'price' => 23200],
                            ['code' => 'VIP', 'min_qty' => 1, 'price' => 22500],
                        ],
                        'supplier_infos' => [
                            ['supplier_code' => 'DEMO-FOU-COS-02', 'min_qty' => 2, 'unit_cost' => 16800, 'lead_time_days' => 7, 'is_preferred' => true],
                        ],
                    ],
                ],
            ],
            default => [
                'suppliers' => [
                    [
                        'code' => 'DEMO-FOU-GEN-01',
                        'name' => 'Nema Distribution Fournisseur A',
                        'phone' => '70080001',
                        'city' => 'Bamako',
                        'address' => 'Zone industrielle, Bamako',
                        'payment_term_code' => 'CPT',
                    ],
                    [
                        'code' => 'DEMO-FOU-GEN-02',
                        'name' => 'Nema Distribution Fournisseur B',
                        'phone' => '70080002',
                        'city' => 'Bamako',
                        'address' => 'Sotuba, Bamako',
                        'payment_term_code' => 'TERM-30',
                    ],
                ],
                'customers' => [
                    [
                        'code' => 'DEMO-CLI-GEN-01',
                        'name' => 'Client detail demo',
                        'phone' => '76090001',
                        'city' => 'Bamako',
                        'address' => 'Bacodjicoroni',
                        'payment_term_code' => 'CPT',
                        'price_list_code' => 'DETAIL',
                    ],
                    [
                        'code' => 'DEMO-CLI-GEN-02',
                        'name' => 'Client gros demo',
                        'phone' => '76090002',
                        'city' => 'Bamako',
                        'address' => 'Kalaban Coura',
                        'payment_term_code' => 'TERM-30',
                        'price_list_code' => 'GROS',
                    ],
                ],
                'products' => [
                    [
                        'sku' => 'DEMO-GT-001',
                        'barcode' => '8901000005011',
                        'name' => 'Boisson malt 33cl',
                        'category' => 'Distribution detail',
                        'unit' => 'unite',
                        'sales_unit_name' => 'Pack',
                        'sales_unit_ratio' => 6,
                        'purchase_unit_name' => 'Carton',
                        'purchase_unit_ratio' => 24,
                        'sale_price' => 550,
                        'purchase_price' => 350,
                        'min_stock' => 30,
                        'reorder_max_qty' => 120,
                        'reorder_multiple_qty' => 24,
                        'purchase_lead_time_days' => 3,
                        'opening_qty' => 96,
                        'opening_unit_cost' => 350,
                        'price_lists' => [
                            ['code' => 'DETAIL', 'min_qty' => 1, 'price' => 550],
                            ['code' => 'GROS', 'min_qty' => 24, 'price' => 470],
                        ],
                        'supplier_infos' => [
                            ['supplier_code' => 'DEMO-FOU-GEN-01', 'min_qty' => 24, 'unit_cost' => 350, 'lead_time_days' => 3, 'is_preferred' => true],
                        ],
                    ],
                    [
                        'sku' => 'DEMO-GT-002',
                        'barcode' => '8901000005012',
                        'name' => 'Spaghetti premium 500g',
                        'category' => 'Distribution detail',
                        'unit' => 'unite',
                        'sale_price' => 700,
                        'purchase_price' => 430,
                        'min_stock' => 25,
                        'reorder_max_qty' => 100,
                        'reorder_multiple_qty' => 20,
                        'purchase_lead_time_days' => 4,
                        'opening_qty' => 80,
                        'opening_unit_cost' => 430,
                        'price_lists' => [
                            ['code' => 'DETAIL', 'min_qty' => 1, 'price' => 700],
                            ['code' => 'GROS', 'min_qty' => 20, 'price' => 620],
                        ],
                        'supplier_infos' => [
                            ['supplier_code' => 'DEMO-FOU-GEN-02', 'min_qty' => 20, 'unit_cost' => 430, 'lead_time_days' => 4, 'is_preferred' => true],
                        ],
                    ],
                    [
                        'sku' => 'DEMO-GT-003',
                        'barcode' => '8901000005013',
                        'name' => 'Sac distribution 50x70',
                        'category' => 'Services',
                        'unit' => 'pack',
                        'type' => 'service',
                        'sale_price' => 3500,
                        'purchase_price' => 0,
                        'min_stock' => 0,
                        'auto_replenish' => false,
                        'purchase_ok' => false,
                        'price_lists' => [
                            ['code' => 'DETAIL', 'min_qty' => 1, 'price' => 3500],
                            ['code' => 'GROS', 'min_qty' => 5, 'price' => 3200],
                        ],
                    ],
                    [
                        'sku' => 'DEMO-GT-004',
                        'barcode' => '8901000005014',
                        'name' => 'Huile 1L',
                        'category' => 'Distribution gros',
                        'unit' => 'carton',
                        'sales_unit_name' => 'Pack',
                        'sales_unit_ratio' => 4,
                        'purchase_unit_name' => 'Palette',
                        'purchase_unit_ratio' => 30,
                        'sale_price' => 16200,
                        'purchase_price' => 13950,
                        'min_stock' => 12,
                        'reorder_max_qty' => 50,
                        'reorder_multiple_qty' => 4,
                        'purchase_lead_time_days' => 5,
                        'opening_qty' => 24,
                        'opening_unit_cost' => 13950,
                        'price_lists' => [
                            ['code' => 'DETAIL', 'min_qty' => 1, 'price' => 16200],
                            ['code' => 'GROS', 'min_qty' => 8, 'price' => 15400],
                        ],
                        'supplier_infos' => [
                            ['supplier_code' => 'DEMO-FOU-GEN-01', 'min_qty' => 4, 'unit_cost' => 13950, 'lead_time_days' => 5, 'is_preferred' => true],
                        ],
                    ],
                ],
            ],
        };
    }
}
