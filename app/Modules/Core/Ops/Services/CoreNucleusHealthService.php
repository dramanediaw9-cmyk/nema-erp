<?php

namespace App\Modules\Core\Ops\Services;

use App\Modules\Core\Company\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CoreNucleusHealthService
{
    public function report(Company $company): array
    {
        $checks = [
            $this->foundationCheck($company),
            $this->tenantIsolationCheck($company),
            $this->accessControlCheck($company),
            $this->documentEngineCheck($company),
            $this->stockEngineCheck($company),
            $this->posEngineCheck($company),
            $this->auditCheck($company),
        ];

        $warningCount = collect($checks)->where('status', 'warning')->count();
        $failureCount = collect($checks)->where('status', 'fail')->count();
        $score = $this->score($checks);

        return [
            'captured_at' => now(),
            'company_id' => $company->id,
            'company_name' => $company->name,
            'score' => $score,
            'status' => $failureCount > 0 ? 'fail' : ($warningCount > 0 ? 'warning' : 'ok'),
            'warning_count' => $warningCount,
            'failure_count' => $failureCount,
            'checks' => $checks,
            'next_actions' => $this->nextActions($checks),
        ];
    }

    private function foundationCheck(Company $company): array
    {
        $metrics = [
            'branches' => $this->count('branches', $company->id, ['is_active' => true]),
            'warehouses' => $this->count('warehouses', $company->id, ['is_active' => true]),
            'cash_accounts' => $this->count('cash_accounts', $company->id, ['is_active' => true]),
            'users' => $this->count('users', $company->id, ['is_active' => true]),
            'roles' => $this->count('roles', $company->id),
            'settings' => $this->count('settings', $company->id),
            'tax_rules' => $this->count('tax_rules', $company->id, ['is_active' => true]),
        ];

        $missingCritical = collect(['branches', 'warehouses', 'cash_accounts', 'users', 'roles'])
            ->filter(fn (string $key): bool => ($metrics[$key] ?? 0) < 1)
            ->values()
            ->all();
        $missingRecommended = collect(['settings', 'tax_rules'])
            ->filter(fn (string $key): bool => ($metrics[$key] ?? 0) < 1)
            ->values()
            ->all();

        return [
            'key' => 'foundation',
            'label' => 'Socle entreprise',
            'status' => $missingCritical ? 'fail' : ($missingRecommended ? 'warning' : 'ok'),
            'message' => $missingCritical
                ? 'Des briques de base manquent pour exploiter cette entreprise.'
                : ($missingRecommended ? 'Le socle fonctionne mais des parametres restent a completer.' : 'Entreprise exploitable: agence, depot, caisse, utilisateurs et roles sont presents.'),
            'metrics' => $metrics,
            'action' => 'Ouvrir Entreprises puis utiliser Reparer le socle si une brique manque.',
        ];
    }

    private function tenantIsolationCheck(Company $company): array
    {
        $tenantMismatch = 0;
        foreach (['users', 'branches', 'roles', 'warehouses', 'cash_accounts', 'settings', 'document_sequences', 'tax_rules'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            $tenantMismatch += DB::table($table)
                ->where('company_id', $company->id)
                ->where('tenant_id', '!=', $company->tenant_id)
                ->count();
        }

        $branchMismatch = 0;
        foreach (['users', 'warehouses', 'cash_accounts', 'stock_movements', 'pos_sessions'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            $branchMismatch += DB::table($table)
                ->leftJoin('branches', $table.'.branch_id', '=', 'branches.id')
                ->where($table.'.company_id', $company->id)
                ->whereNotNull($table.'.branch_id')
                ->where(function ($query) use ($table): void {
                    $query->whereNull('branches.id')
                        ->orWhereColumn('branches.company_id', '!=', $table.'.company_id');
                })
                ->count();
        }

        $issues = $tenantMismatch + $branchMismatch;

        return [
            'key' => 'tenant_isolation',
            'label' => 'Isolation multi-entreprise',
            'status' => $issues > 0 ? 'fail' : 'ok',
            'message' => $issues > 0
                ? 'Des lignes semblent rattachees a un mauvais tenant ou a une mauvaise agence.'
                : 'Aucune fuite de rattachement detectee sur les tables critiques.',
            'metrics' => [
                'tenant_mismatch' => $tenantMismatch,
                'branch_mismatch' => $branchMismatch,
            ],
            'action' => 'Ne pas ouvrir l exploitation si une fuite multi-entreprise est detectee.',
        ];
    }

    private function accessControlCheck(Company $company): array
    {
        $cashierRole = DB::table('roles')
            ->where('company_id', $company->id)
            ->where('slug', 'cashier')
            ->first();

        if (! $cashierRole) {
            return [
                'key' => 'access_control',
                'label' => 'Droits caissier',
                'status' => 'warning',
                'message' => 'Aucun role caissier n existe encore pour cette entreprise.',
                'metrics' => ['cashier_users' => 0, 'forbidden_permissions' => []],
                'action' => 'Creer un role caissier avant de confier la caisse a un employe.',
            ];
        }

        $cashierUsers = DB::table('role_user')
            ->join('users', 'role_user.user_id', '=', 'users.id')
            ->where('role_user.role_id', $cashierRole->id)
            ->where('users.company_id', $company->id)
            ->where('users.is_active', true)
            ->count();

        $forbidden = DB::table('permission_role')
            ->join('permissions', 'permission_role.permission_id', '=', 'permissions.id')
            ->where('permission_role.role_id', $cashierRole->id)
            ->whereNotIn('permissions.slug', $this->cashierAllowedPermissions())
            ->orderBy('permissions.slug')
            ->pluck('permissions.slug')
            ->all();

        return [
            'key' => 'access_control',
            'label' => 'Droits caissier',
            'status' => $forbidden ? 'warning' : 'ok',
            'message' => $forbidden
                ? 'Le role caissier contient des permissions larges, mais le noyau bloque maintenant les routes hors caisse.'
                : 'Le role caissier est limite aux permissions de caisse.',
            'metrics' => [
                'cashier_users' => $cashierUsers,
                'forbidden_permissions' => $forbidden,
            ],
            'action' => 'Garder le caissier dans le point de vente; manager et administrateur gardent les rapports et reglages.',
        ];
    }

    private function documentEngineCheck(Company $company): array
    {
        $required = ['sales_invoice', 'payment', 'pos_session', 'stock_count', 'stock_transfer', 'purchase_order'];
        $existing = DB::table('document_sequences')
            ->where('company_id', $company->id)
            ->whereIn('document_type', $required)
            ->pluck('document_type')
            ->all();
        $missing = array_values(array_diff($required, $existing));

        return [
            'key' => 'document_engine',
            'label' => 'Moteur documents',
            'status' => $missing ? 'warning' : 'ok',
            'message' => $missing
                ? 'Certaines numerotations importantes manquent.'
                : 'Les numerotations essentielles sont pretes.',
            'metrics' => [
                'required' => $required,
                'missing' => $missing,
            ],
            'action' => 'Verifier les sequences dans Parametres generaux.',
        ];
    }

    private function stockEngineCheck(Company $company): array
    {
        $stockableProducts = $this->count('products', $company->id, ['type' => 'stockable', 'is_active' => true]);
        $productsWithoutBarcode = DB::table('products')
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('barcode')->orWhere('barcode', '');
            })
            ->count();
        $negativeStock = DB::table('products')
            ->leftJoin('stock_movements', 'stock_movements.product_id', '=', 'products.id')
            ->where('products.company_id', $company->id)
            ->where('products.type', 'stockable')
            ->select('products.id')
            ->selectRaw('COALESCE(SUM(stock_movements.quantity_in - stock_movements.quantity_out), 0) as balance')
            ->groupBy('products.id')
            ->havingRaw('balance < 0')
            ->count();
        $movements = $this->count('stock_movements', $company->id);

        $status = $negativeStock > 0 ? 'fail' : ($productsWithoutBarcode > 0 ? 'warning' : 'ok');

        return [
            'key' => 'stock_engine',
            'label' => 'Moteur stock',
            'status' => $status,
            'message' => $negativeStock > 0
                ? 'Des produits ont un stock negatif.'
                : ($productsWithoutBarcode > 0 ? 'Certains produits n ont pas encore de code scannable.' : 'Stock coherent sur les controles de base.'),
            'metrics' => [
                'stockable_products' => $stockableProducts,
                'products_without_barcode' => $productsWithoutBarcode,
                'negative_stock_products' => $negativeStock,
                'stock_movements' => $movements,
            ],
            'action' => 'Utiliser inventaire rapide ou stock initial pour corriger les ecarts.',
        ];
    }

    private function posEngineCheck(Company $company): array
    {
        $openSessions = $this->count('pos_sessions', $company->id, ['status' => 'open']);
        $sessions = $this->count('pos_sessions', $company->id);
        $sales = Schema::hasTable('sales_invoices') && Schema::hasColumn('sales_invoices', 'sale_channel')
            ? DB::table('sales_invoices')
                ->where('company_id', $company->id)
                ->where('sale_channel', 'pos')
                ->count()
            : 0;
        $paymentsWithoutSession = 0;

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'pos_session_id')) {
            $paymentsWithoutSession = DB::table('payments')
                ->where('company_id', $company->id)
                ->where('direction', 'in')
                ->whereNull('pos_session_id')
                ->count();
        }

        return [
            'key' => 'pos_engine',
            'label' => 'Moteur caisse',
            'status' => $paymentsWithoutSession > 0 ? 'warning' : 'ok',
            'message' => $paymentsWithoutSession > 0
                ? 'Certains encaissements ne sont pas rattaches a une session de caisse.'
                : 'Sessions, ventes POS et encaissements sont sous controle.',
            'metrics' => [
                'open_sessions' => $openSessions,
                'total_sessions' => $sessions,
                'pos_sales' => $sales,
                'payments_without_session' => $paymentsWithoutSession,
            ],
            'action' => 'Cloturer chaque session et imprimer le rapport journalier.',
        ];
    }

    private function auditCheck(Company $company): array
    {
        $logs = $this->count('activity_logs', $company->id);
        $recentLogs = DB::table('activity_logs')
            ->where('company_id', $company->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return [
            'key' => 'audit',
            'label' => 'Journal audit',
            'status' => $logs > 0 ? 'ok' : 'warning',
            'message' => $logs > 0
                ? 'Les actions sont tracees dans le journal.'
                : 'Aucune trace audit n est encore disponible.',
            'metrics' => [
                'total_logs' => $logs,
                'recent_logs_7d' => $recentLogs,
            ],
            'action' => 'Verifier regulierement qui modifie les prix, stocks, caisses et utilisateurs.',
        ];
    }

    private function count(string $table, int $companyId, array $where = []): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table)->where('company_id', $companyId);

        foreach ($where as $column => $value) {
            if (Schema::hasColumn($table, $column)) {
                $query->where($column, $value);
            }
        }

        return (int) $query->count();
    }

    private function cashierAllowedPermissions(): array
    {
        return [
            'dashboard.view',
            'pos.view',
            'pos.manage',
        ];
    }

    private function score(array $checks): int
    {
        $map = ['ok' => 100, 'warning' => 65, 'fail' => 20];
        $total = collect($checks)->sum(fn (array $check): int => $map[$check['status']] ?? 65);

        return (int) round($total / max(count($checks), 1));
    }

    private function nextActions(array $checks): array
    {
        return collect($checks)
            ->reject(fn (array $check): bool => $check['status'] === 'ok')
            ->map(fn (array $check): string => $check['label'].' : '.$check['action'])
            ->values()
            ->take(5)
            ->all();
    }
}
