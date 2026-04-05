<?php

namespace App\Modules\Core\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Pos\Services\PosService;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\SalesInvoice;
use App\Support\CurrentWorkspace;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MerchantRoutineController extends Controller
{
    public function __construct(
        private readonly PosService $posService,
    ) {
    }

    public function __invoke(CurrentWorkspace $workspace, Request $request): View
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        $user = $request->user();
        abort_if(! $companyId || ! $branchId || ! $user, 403);

        $today = now()->toDateString();
        $currentSession = $this->posService->currentOpenSession($companyId, $branchId, $user->id);
        $reportFilters = ['date' => $today];

        if ($currentSession) {
            $reportFilters['warehouse_id'] = $currentSession->warehouse_id;
            $reportFilters['cash_account_id'] = $currentSession->cash_account_id;
        }

        $dailyReport = $this->posService->dailyReport($companyId, $branchId, $reportFilters);
        $sessionSummary = $currentSession ? $this->posService->summary($currentSession) : null;
        $recentTickets = $currentSession ? $this->posService->recentInvoices($currentSession, 5) : collect();
        $stockAlerts = $this->stockAlerts($companyId, $branchId);

        $customerReceivables = [
            'count' => SalesInvoice::query()
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('status', 'validated')
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->count(),
            'amount' => (float) SalesInvoice::query()
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('status', 'validated')
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->sum('balance_due'),
        ];

        $supplierPayables = [
            'count' => PurchaseBill::query()
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('status', 'validated')
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->count(),
            'amount' => (float) PurchaseBill::query()
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('status', 'validated')
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->sum('balance_due'),
        ];

        $steps = collect([
            [
                'number' => '1',
                'title' => $currentSession ? 'Session de caisse deja ouverte' : 'Ouvrir la caisse',
                'status' => $currentSession ? 'ok' : 'todo',
                'status_label' => $currentSession ? 'Deja fait' : 'A faire',
                'description' => $currentSession
                    ? 'La session '.$currentSession->session_number.' est ouverte sur ton poste. Tu peux reprendre directement la caisse.'
                    : 'Choisis le compte de caisse, l entrepot de vente et le fond initial pour demarrer ta journee.',
                'permission' => 'pos.view',
                'action_url' => $currentSession ? route('pos.show', $currentSession) : route('pos.index'),
                'action_label' => $currentSession ? 'Voir la session' : 'Ouvrir la caisse',
            ],
            [
                'number' => '2',
                'title' => 'Vendre et encaisser',
                'status' => $currentSession ? 'ready' : 'blocked',
                'status_label' => $currentSession ? 'Pret' : 'Bloque',
                'description' => $currentSession
                    ? 'Ajoute les articles, encaisse le client et imprime le ticket sans repasser par d autres menus.'
                    : 'Cette etape devient disponible des qu une session de caisse est ouverte.',
                'permission' => 'pos.view',
                'action_url' => $currentSession ? route('pos.sales.create', ['session' => $currentSession->id]) : route('pos.index'),
                'action_label' => $currentSession ? 'Aller a la caisse' : 'Ouvrir d abord une session',
            ],
            [
                'number' => '3',
                'title' => 'Tickets et retours',
                'status' => $currentSession && ($dailyReport['sales_count'] ?? 0) > 0 ? 'ready' : 'wait',
                'status_label' => $currentSession && ($dailyReport['sales_count'] ?? 0) > 0 ? 'Disponible' : 'Apres les ventes',
                'description' => $currentSession
                    ? 'Retrouve rapidement les tickets de la session, relance une impression ou traite un retour client.'
                    : 'Les tickets et retours apparaitront ici apres ouverture de la caisse.',
                'permission' => 'pos.view',
                'action_url' => $currentSession ? route('pos.show', $currentSession).'#tickets-session' : route('pos.index'),
                'action_label' => $currentSession ? 'Voir tickets et retours' : 'Ouvrir la caisse',
            ],
            [
                'number' => '4',
                'title' => 'Verifier ce qui manque',
                'status' => $stockAlerts > 0 ? 'warning' : 'ok',
                'status_label' => $stockAlerts > 0 ? 'A surveiller' : 'Sous controle',
                'description' => $stockAlerts > 0
                    ? $stockAlerts.' produit(s) sont au minimum de stock sur l agence active.'
                    : 'Aucun produit critique pour l instant sur l agence active.',
                'permission' => 'stock.view',
                'action_url' => route('stock.index', ['stock_state' => 'low']),
                'action_label' => $stockAlerts > 0 ? 'Voir le stock critique' : 'Voir le stock',
            ],
            [
                'number' => '5',
                'title' => 'Cloturer la caisse',
                'status' => $currentSession ? 'ready' : 'wait',
                'status_label' => $currentSession ? 'A preparer' : 'En attente',
                'description' => $currentSession
                    ? 'Compte les especes, verifie les ecarts par mode et cloture la session avec justification si besoin.'
                    : 'La cloture apparaitra ici des que tu auras ouvert une session sur ton poste.',
                'permission' => 'pos.view',
                'action_url' => $currentSession ? route('pos.show', $currentSession).'#cloture-session' : route('pos.index'),
                'action_label' => $currentSession ? 'Preparer la cloture' : 'Ouvrir la caisse',
            ],
            [
                'number' => '6',
                'title' => 'Sortir le resume du jour',
                'status' => 'ready',
                'status_label' => 'Disponible',
                'description' => 'Voir les tickets du jour, les encaissements, le panier moyen et les produits qui tournent le plus.',
                'permission' => 'pos.view',
                'action_url' => route('pos.report', $reportFilters),
                'action_label' => 'Voir le rapport simple',
            ],
        ])
            ->filter(fn (array $step) => $user->hasPermission($step['permission']))
            ->values();

        return view('dashboard.merchant-routine', [
            'today' => $today,
            'currentSession' => $currentSession,
            'sessionSummary' => $sessionSummary,
            'dailyReport' => $dailyReport,
            'reportFilters' => $reportFilters,
            'recentTickets' => $recentTickets,
            'stockAlerts' => $stockAlerts,
            'customerReceivables' => $customerReceivables,
            'supplierPayables' => $supplierPayables,
            'steps' => $steps,
        ]);
    }

    private function stockAlerts(int $companyId, int $branchId): int
    {
        $balances = StockMovement::query()
            ->select('product_id')
            ->selectRaw('SUM(quantity_in - quantity_out) as current_stock')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->groupBy('product_id');

        return Product::query()
            ->where('company_id', $companyId)
            ->where('type', 'stockable')
            ->leftJoinSub($balances, 'balances', fn ($join) => $join->on('products.id', '=', 'balances.product_id'))
            ->whereRaw('COALESCE(balances.current_stock, 0) <= products.min_stock')
            ->count();
    }
}
