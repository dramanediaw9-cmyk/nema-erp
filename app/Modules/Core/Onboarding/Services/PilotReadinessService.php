<?php

namespace App\Modules\Core\Onboarding\Services;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Inventory\Models\ProductLot;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Pos\Models\PosSession;
use App\Modules\Treasury\Models\CashAccount;

class PilotReadinessService
{
    public function summary(int $companyId): array
    {
        $pilotBranch = Branch::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->first();

        $pilotBranchId = $pilotBranch?->id;
        $cashAccountQuery = CashAccount::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->when($pilotBranchId, fn ($query, $branchId) => $query->where(fn ($builder) => $builder->whereNull('branch_id')->orWhere('branch_id', $branchId)));

        $cashAccounts = (clone $cashAccountQuery)->get();
        $warehouseCount = $pilotBranch
            ? $pilotBranch->warehouses()->where('is_active', true)->count()
            : 0;
        $cashCount = $cashAccounts->where('type', 'cash')->count();
        $mobileCount = $cashAccounts->where('type', 'mobile_money')->count();
        $cashierCount = $this->activeRoleCount($companyId, ['cashier']);
        $supervisorCount = $this->activeRoleCount($companyId, ['company_admin', 'director']);
        $operationsCount = $this->activeRoleCount($companyId, ['operations_officer']);

        $saleableProducts = Product::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('sale_ok', true)
            ->where('type', 'stockable')
            ->with('supplierInfos')
            ->get();

        $purchasableProducts = Product::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('purchase_ok', true)
            ->where('type', 'stockable')
            ->with('supplierInfos')
            ->get();

        $balances = StockMovement::query()
            ->where('company_id', $companyId)
            ->when($pilotBranchId, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->selectRaw('product_id, COALESCE(SUM(quantity_in - quantity_out), 0) as balance')
            ->groupBy('product_id')
            ->pluck('balance', 'product_id');

        $stockedSaleableCount = $saleableProducts->filter(fn (Product $product) => (float) ($balances[$product->id] ?? 0) > 0)->count();
        $missingBarcodeCount = $saleableProducts->filter(fn (Product $product) => blank($product->barcode))->count();
        $missingSalePriceCount = $saleableProducts->filter(fn (Product $product) => (float) $product->sale_price <= 0)->count();
        $missingSupplierCount = $purchasableProducts->filter(function (Product $product): bool {
            return $product->supplierInfos->where('is_preferred', true)->isEmpty();
        })->count();
        $belowMinStockCount = $saleableProducts->filter(function (Product $product) use ($balances): bool {
            return (float) $product->min_stock > 0 && (float) ($balances[$product->id] ?? 0) < (float) $product->min_stock;
        })->count();
        $expiringSoonLotCount = ProductLot::query()
            ->where('company_id', $companyId)
            ->when($pilotBranchId, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->where('quantity_available', '>', 0)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '>=', now()->toDateString())
            ->whereDate('expires_at', '<=', now()->addDays(15)->toDateString())
            ->count();
        $openSessionCount = PosSession::query()
            ->where('company_id', $companyId)
            ->when($pilotBranchId, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->where('status', 'open')
            ->count();

        $prerequisites = [
            [
                'title' => 'Agence pilote active',
                'completed' => (bool) $pilotBranch,
                'metric' => $pilotBranch ? $pilotBranch->name : 'Aucune agence active',
                'message' => $pilotBranch ? 'Une agence pilote est disponible pour le test terrain.' : 'Aucune agence active n est disponible pour organiser l essai reel.',
                'route' => route('branches.index'),
                'action' => 'Voir les agences',
            ],
            [
                'title' => 'Depot de vente disponible',
                'completed' => $warehouseCount > 0,
                'metric' => $warehouseCount.' depot(s) actif(s)',
                'message' => $warehouseCount > 0 ? 'Le stock peut etre suivi sur le depot pilote.' : 'Aucun depot actif n est rattache a l agence pilote.',
                'route' => route('warehouses.index'),
                'action' => 'Voir les depots',
            ],
            [
                'title' => 'Gestionnaire ou directeur actif',
                'completed' => $supervisorCount > 0,
                'metric' => $supervisorCount.' profil(s) supervision',
                'message' => $supervisorCount > 0 ? 'Une supervision est disponible pour valider les operations de test.' : 'Aucun profil gestionnaire/directeur actif pour piloter l essai.',
                'route' => route('users.index'),
                'action' => 'Voir les utilisateurs',
            ],
            [
                'title' => 'Caissier actif',
                'completed' => $cashierCount > 0,
                'metric' => $cashierCount.' caissier(s)',
                'message' => $cashierCount > 0 ? 'Un utilisateur peut tenir la caisse pendant le pilote.' : 'Aucun caissier actif n est disponible pour le test comptoir.',
                'route' => route('users.index'),
                'action' => 'Verifier les roles',
            ],
            [
                'title' => 'Compte especes comptoir',
                'completed' => $cashCount > 0,
                'metric' => $cashCount.' compte(s) cash',
                'message' => $cashCount > 0 ? 'La caisse espece est prete pour un encaissement reel.' : 'Aucun compte de tresorerie espece n est pret pour la caisse.',
                'route' => route('cash-accounts.index'),
                'action' => 'Voir la tresorerie',
            ],
            [
                'title' => 'Canal mobile money disponible',
                'completed' => $mobileCount > 0,
                'metric' => $mobileCount.' compte(s) mobile money',
                'message' => $mobileCount > 0 ? 'Le pilote peut tester un paiement terrain mobile money.' : 'Aucun compte mobile money actif n est configure pour l essai.',
                'route' => route('cash-accounts.index'),
                'action' => 'Configurer les paiements',
            ],
            [
                'title' => 'Catalogue pilote suffisant',
                'completed' => $saleableProducts->count() >= 5,
                'metric' => $saleableProducts->count().' produit(s) vendables',
                'message' => $saleableProducts->count() >= 5 ? 'Le catalogue est assez riche pour simuler des cas reels.' : 'Le catalogue vendable est trop leger pour un essai terrain credible.',
                'route' => route('products.index'),
                'action' => 'Voir les produits',
            ],
            [
                'title' => 'Stock vendable disponible',
                'completed' => $stockedSaleableCount >= 3,
                'metric' => $stockedSaleableCount.' produit(s) avec stock positif',
                'message' => $stockedSaleableCount >= 3 ? 'Le point de vente peut encaisser plusieurs cas reels.' : 'Le stock positif est encore trop faible pour un pilote terrain.',
                'route' => route('stock.index'),
                'action' => 'Voir le stock',
            ],
        ];

        $dataQuality = [
            [
                'title' => 'Produits vendables avec code-barres',
                'value' => max($saleableProducts->count() - $missingBarcodeCount, 0).' / '.$saleableProducts->count(),
                'status' => $missingBarcodeCount === 0 ? 'ok' : 'warning',
                'message' => $missingBarcodeCount === 0 ? 'Le scan caisse peut etre teste sur tout le catalogue pilote.' : $missingBarcodeCount.' produit(s) vendables sans code-barres.',
                'route' => route('products.index'),
                'action' => 'Corriger le catalogue',
            ],
            [
                'title' => 'Produits vendables avec prix de vente',
                'value' => max($saleableProducts->count() - $missingSalePriceCount, 0).' / '.$saleableProducts->count(),
                'status' => $missingSalePriceCount === 0 ? 'ok' : 'warning',
                'message' => $missingSalePriceCount === 0 ? 'Les tarifs de vente sont exploitables pour le pilote.' : $missingSalePriceCount.' produit(s) vendables sans prix de vente.',
                'route' => route('products.index'),
                'action' => 'Verifier les prix',
            ],
            [
                'title' => 'Produits achetables avec fournisseur prefere',
                'value' => max($purchasableProducts->count() - $missingSupplierCount, 0).' / '.$purchasableProducts->count(),
                'status' => $missingSupplierCount === 0 ? 'ok' : 'warning',
                'message' => $missingSupplierCount === 0 ? 'Le reappro automatique peut etre teste sans trou de sourcing.' : $missingSupplierCount.' produit(s) achetables sans fournisseur prefere.',
                'route' => route('products.index'),
                'action' => 'Verifier les fournisseurs',
            ],
            [
                'title' => 'Produits sous stock mini',
                'value' => (string) $belowMinStockCount,
                'status' => $belowMinStockCount === 0 ? 'ok' : 'warning',
                'message' => $belowMinStockCount === 0 ? 'Aucun produit pilote n est sous mini.' : $belowMinStockCount.' produit(s) sont deja sous stock mini.',
                'route' => route('replenishments.index'),
                'action' => 'Voir le reassort',
            ],
            [
                'title' => 'Lots a peremption proche',
                'value' => (string) $expiringSoonLotCount,
                'status' => $expiringSoonLotCount === 0 ? 'ok' : 'warning',
                'message' => $expiringSoonLotCount === 0 ? 'Aucun lot critique imminent sur le pilote.' : $expiringSoonLotCount.' lot(s) expirent dans les 15 prochains jours.',
                'route' => route('stock.lots'),
                'action' => 'Voir les lots',
            ],
            [
                'title' => 'Sessions POS ouvertes',
                'value' => (string) $openSessionCount,
                'status' => $openSessionCount > 0 ? 'warning' : 'ok',
                'message' => $openSessionCount > 0 ? 'Une session caisse est deja ouverte sur le pilote.' : 'Aucune session caisse ouverte avant le test, c est propre.',
                'route' => route('pos.index'),
                'action' => 'Ouvrir le POS',
            ],
        ];

        $blockers = collect($prerequisites)
            ->filter(fn (array $item) => ! $item['completed'])
            ->map(fn (array $item) => [
                'title' => $item['title'],
                'message' => $item['message'],
                'route' => $item['route'],
                'action' => $item['action'],
            ])
            ->values()
            ->all();

        $validationRuns = [
            [
                'title' => 'Ouverture et cloture caisse',
                'description' => 'Tester l ouverture de session, une vente simple puis la cloture avec ecart nul.',
                'route' => route('pos.index'),
                'action' => 'Lancer le POS',
                'steps' => [
                    'Ouvre une nouvelle session sur l agence pilote avec le compte espece comptoir.',
                    'Passe un ticket simple avec impression ticket ou recu thermique.',
                    'Cloture ensuite la session et controle l ecart de caisse.',
                ],
            ],
            [
                'title' => 'Vente terrain et encaissement mixte',
                'description' => 'Verifier cash, mobile money, stock sorti et encaissement relie au ticket.',
                'route' => route('pos.index'),
                'action' => 'Tester une vente',
                'steps' => [
                    'Ajoute plusieurs produits pilotes au panier.',
                    'Teste un paiement cash seul puis un paiement mixte cash + mobile money.',
                    'Controle ensuite le ticket, la monnaie et la baisse de stock.',
                ],
            ],
            [
                'title' => 'Achat, reception et stock',
                'description' => 'Valider la chaine fournisseur jusqu au stock disponible en depot.',
                'route' => route('purchase-orders.index'),
                'action' => 'Ouvrir les achats',
                'steps' => [
                    'Cree une commande fournisseur sur un produit pilote en reassort.',
                    'Saisis la reception fournisseur puis verifie le stock en depot.',
                    'Controle la facture fournisseur si tu veux boucler le cycle.',
                ],
            ],
            [
                'title' => 'Rapports fin de journee',
                'description' => 'Verifier que le dirigeant retrouve bien les chiffres apres le pilote.',
                'route' => route('reports.index'),
                'action' => 'Voir les rapports',
                'steps' => [
                    'Ouvre les rapports apres les tests terrain.',
                    'Controle ventes du jour, top produits et stock critique.',
                    'Valide que les chiffres sont coherents avec les tickets et achats testes.',
                ],
            ],
        ];

        $scoreItems = collect($prerequisites)->map(fn (array $item) => $item['completed'])
            ->concat(collect($dataQuality)->map(fn (array $item) => $item['status'] === 'ok'));
        $score = $scoreItems->isEmpty() ? 0 : (int) round(($scoreItems->filter()->count() / $scoreItems->count()) * 100);

        return [
            'pilot_branch' => $pilotBranch?->name,
            'warehouse_count' => $warehouseCount,
            'cashier_count' => $cashierCount,
            'operations_count' => $operationsCount,
            'supervisor_count' => $supervisorCount,
            'cash_count' => $cashCount,
            'mobile_count' => $mobileCount,
            'saleable_products_count' => $saleableProducts->count(),
            'stocked_saleable_count' => $stockedSaleableCount,
            'score' => $score,
            'is_ready' => count($blockers) === 0,
            'blockers_count' => count($blockers),
            'prerequisites' => $prerequisites,
            'data_quality' => $dataQuality,
            'blockers' => $blockers,
            'validation_runs' => $validationRuns,
        ];
    }

    private function activeRoleCount(int $companyId, array $slugs): int
    {
        return User::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', $slugs))
            ->count();
    }
}
