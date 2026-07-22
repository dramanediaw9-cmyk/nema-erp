<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Services\SectorProfileService;
use App\Modules\Core\Dashboard\Services\ExecutiveBriefingService;
use App\Modules\Core\Ops\Services\ApplicationMonitoringService;
use App\Modules\Purchases\Services\SupplierPerformanceService;
use App\Modules\Reporting\Services\ReportingService;
use App\Support\CurrentWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportingService $reportingService,
        private readonly SupplierPerformanceService $supplierPerformanceService,
        private readonly ApplicationMonitoringService $applicationMonitoringService,
        private readonly ExecutiveBriefingService $executiveBriefingService,
        private readonly SectorProfileService $sectorProfileService,
    ) {}

    public function index(Request $request, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);
        $sectorProfile = $this->sectorProfileService->profileForCompany($companyId);
        $businessVocabulary = $this->sectorProfileService->businessVocabularyForProfile($sectorProfile);

        $user = $request->user();
        $canViewMargin = (bool) $user?->hasPermission('reports.margin.view');
        $canAccessAllBranches = (bool) $user?->canAccessAllBranches();

        $branches = Branch::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $requestedBranchId = $request->has('branch_id')
            ? ($request->integer('branch_id') ?: null)
            : $workspace->branchId();

        $selectedBranchId = $user?->resolvedBranchScope($requestedBranchId, $workspace->branchId())
            ?? $workspace->branchId();

        if ($selectedBranchId && ! $branches->contains('id', $selectedBranchId)) {
            $selectedBranchId = $canAccessAllBranches ? null : $workspace->branchId();
        }

        $selectedBranch = $branches->firstWhere('id', $selectedBranchId);
        $dateFrom = $request->string('date_from')->value() ?: now()->startOfMonth()->format('Y-m-d');
        $dateTo = $request->string('date_to')->value() ?: now()->format('Y-m-d');

        if (Carbon::parse($dateFrom)->gt(Carbon::parse($dateTo))) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $sales = $this->reportingService->salesSummary($companyId, $dateFrom, $dateTo, $selectedBranchId);
        $purchases = $this->reportingService->purchasesSummary($companyId, $dateFrom, $dateTo, $selectedBranchId);
        $treasury = $this->reportingService->treasurySummary($companyId, $dateFrom, $dateTo, $selectedBranchId);
        $expenses = $this->reportingService->expensesSummary($companyId, $dateFrom, $dateTo, $selectedBranchId);
        $receivables = $this->reportingService->receivablesSummary($companyId, $selectedBranchId);
        $payables = $this->reportingService->payablesSummary($companyId, $selectedBranchId);
        $stock = $this->reportingService->stockSummary($companyId, $selectedBranchId);
        $grossMargin = $canViewMargin
            ? $this->reportingService->grossMarginSummary($companyId, $dateFrom, $dateTo, $selectedBranchId)
            : ['revenue' => 0.0, 'estimated_cost' => 0.0, 'margin' => 0.0, 'rate' => 0.0];
        $comparison = $this->reportingService->periodComparison($companyId, $dateFrom, $dateTo, $selectedBranchId);
        if (! $canViewMargin) {
            $comparison['margin'] = ['current' => 0.0, 'previous' => 0.0, 'delta_value' => 0.0, 'delta_percent' => 0.0, 'direction' => 'flat'];
        }

        $topProducts = $this->reportingService->topProducts($companyId, $dateFrom, $dateTo, $selectedBranchId);
        $marginByCategory = $canViewMargin
            ? $this->reportingService->marginByCategory($companyId, $dateFrom, $dateTo, $selectedBranchId)
            : [];
        $topCustomers = $this->reportingService->topCustomers($companyId, $dateFrom, $dateTo, $selectedBranchId);
        $salesByBranch = $canAccessAllBranches
            ? collect($this->reportingService->salesByBranch($companyId, $dateFrom, $dateTo))
                ->map(fn (array $row) => $row + ['is_selected' => $selectedBranchId ? (int) $row['branch_id'] === (int) $selectedBranchId : false])
                ->all()
            : [];
        $dormantProducts = $this->reportingService->dormantProducts($companyId, $selectedBranchId);
        $supplierPerformance = $this->supplierPerformanceService->ranking($companyId, $selectedBranchId, $dateFrom, $dateTo, 8);
        $appMonitoring = $this->applicationMonitoringService->summary();
        $executiveBrief = $this->executiveBriefingService->forReports([
            'sales' => $sales,
            'comparison' => $comparison,
            'grossMargin' => $grossMargin,
            'receivables' => $receivables,
            'payables' => $payables,
            'stock' => $stock,
            'supplierPerformance' => $supplierPerformance,
            'filters' => ['date_from' => $dateFrom, 'date_to' => $dateTo, 'branch_id' => $selectedBranchId],
            'canViewMargin' => $canViewMargin,
            'appMonitoring' => $appMonitoring,
            'businessVocabulary' => $businessVocabulary,
        ]);

        return view('reports.index', [
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'branch_id' => $selectedBranchId,
            ],
            'branches' => $branches,
            'selectedBranch' => $selectedBranch,
            'scopeLabel' => $selectedBranch ? $selectedBranch->name : 'Toutes les agences',
            'canViewMargin' => $canViewMargin,
            'canAccessAllBranches' => $canAccessAllBranches,
            'sales' => $sales,
            'purchases' => $purchases,
            'treasury' => $treasury,
            'expenses' => $expenses,
            'receivables' => $receivables,
            'payables' => $payables,
            'stock' => $stock,
            'grossMargin' => $grossMargin,
            'comparison' => $comparison,
            'topProducts' => $topProducts,
            'marginByCategory' => $marginByCategory,
            'topCustomers' => $topCustomers,
            'salesByBranch' => $salesByBranch,
            'dormantProducts' => $dormantProducts,
            'supplierPerformance' => $supplierPerformance,
            'sectorProfile' => $sectorProfile,
            'reportBlueprint' => $this->reportBlueprint($sectorProfile, $businessVocabulary),
            'executiveBrief' => $executiveBrief,
            'signals' => $this->pilotageSignals(
                sales: $sales,
                grossMargin: $grossMargin,
                receivables: $receivables,
                salesByBranch: $salesByBranch,
                dormantProducts: $dormantProducts,
                supplierPerformance: $supplierPerformance,
                filters: ['date_from' => $dateFrom, 'date_to' => $dateTo, 'branch_id' => $selectedBranchId],
                canViewMargin: $canViewMargin,
                businessVocabulary: $businessVocabulary,
            ),
        ]);
    }

    private function reportBlueprint(array $sectorProfile, array $businessVocabulary): array
    {
        $profileLabel = $sectorProfile['label'] ?? 'Commerce general';
        $salesLabel = $businessVocabulary['sales'] ?? 'Ventes';
        $stockLabel = $businessVocabulary['stock'] ?? 'Stock';
        $customersLabel = $businessVocabulary['clients'] ?? 'Clients';
        $suppliersLabel = $businessVocabulary['suppliers'] ?? 'Fournisseurs';

        return [
            'title' => 'Pilotage '.$profileLabel,
            'subtitle' => 'Les rapports mettent en avant les chiffres utiles pour '.$profileLabel.'.',
            'kpis' => array_slice($sectorProfile['kpis'] ?? [], 0, 6),
            'alerts' => array_slice($sectorProfile['alerts'] ?? [], 0, 5),
            'documents' => array_slice($sectorProfile['documents'] ?? [], 0, 5),
            'quick_links' => [
                ['label' => $salesLabel, 'url' => route('sales.index')],
                ['label' => $stockLabel, 'url' => route('stock.index')],
                ['label' => $customersLabel, 'url' => route('customers.index')],
                ['label' => $suppliersLabel, 'url' => route('suppliers.index')],
            ],
        ];
    }

    private function pilotageSignals(array $sales, array $grossMargin, array $receivables, array $salesByBranch, array $dormantProducts, array $supplierPerformance, array $filters, bool $canViewMargin, array $businessVocabulary = []): array
    {
        $signals = [];
        $salesTotal = (float) ($sales['total'] ?? 0);
        $marginRate = (float) ($grossMargin['rate'] ?? 0);
        $dormantValue = collect($dormantProducts)->sum('stock_value');
        $salesLabel = strtolower($businessVocabulary['sales'] ?? 'ventes');
        $stockLabel = strtolower($businessVocabulary['stock'] ?? 'stock');
        $supplierLabel = strtolower($businessVocabulary['supplier'] ?? 'fournisseur');

        if ($canViewMargin && $salesTotal > 0 && $marginRate < 20) {
            $signals[] = [
                'level' => 'danger',
                'title' => 'Marge estimee sous surveillance',
                'message' => 'La marge estimee tombe a '.$marginRate.' % sur la periode analysee.',
                'action_url' => route('reports.index', $filters),
            ];
        }

        if ($salesTotal > 0 && (float) $receivables['total'] > ($salesTotal * 0.6)) {
            $signals[] = [
                'level' => 'warning',
                'title' => 'Recouvrement a accelerer',
                'message' => 'Les creances ouvertes representent plus de 60 % du chiffre d affaires '.$salesLabel.' de la periode.',
                'action_url' => route('collections.index'),
            ];
        }

        if ($dormantValue >= 200000) {
            $signals[] = [
                'level' => 'warning',
                'title' => ucfirst($stockLabel).' dormant significatif',
                'message' => 'Le '.$stockLabel.' dormant visible sur ce perimetre represente '.number_format($dormantValue, 0, ',', ' ').' XOF.',
                'action_url' => route('stock.index'),
            ];
        }

        $branchDrop = collect($salesByBranch)
            ->filter(fn (array $row) => (float) $row['previous_total'] > 0 && (float) $row['total'] < ((float) $row['previous_total'] * 0.8))
            ->sortBy('delta_percent')
            ->first();

        if ($branchDrop) {
            $signals[] = [
                'level' => 'warning',
                'title' => 'Agence en retrait',
                'message' => $branchDrop['branch_name'].' recule de '.abs((float) $branchDrop['delta_percent']).' % par rapport a la periode precedente.',
                'action_url' => route('sales.index', ['branch_id' => $branchDrop['branch_id'], 'date_from' => $filters['date_from'], 'date_to' => $filters['date_to']]),
            ];
        }

        $weakSupplier = collect($supplierPerformance)
            ->filter(fn (array $row) => (float) $row['spend_total'] > 0)
            ->sortBy('score')
            ->first();

        if ($weakSupplier && (float) $weakSupplier['score'] < 60) {
            $signals[] = [
                'level' => 'warning',
                'title' => ucfirst($supplierLabel).' a surveiller',
                'message' => $weakSupplier['supplier_name'].' descend a un score de '.number_format((float) $weakSupplier['score'], 1, ',', ' ').' / 100 avec '.$weakSupplier['score_label'].'.',
                'action_url' => route('suppliers.show', $weakSupplier['supplier_id']),
            ];
        }

        if ($signals === []) {
            $signals[] = [
                'level' => 'success',
                'title' => 'Aucun signal critique',
                'message' => 'Le pilotage ne remonte aucun point majeur sur ce perimetre pour l instant.',
                'action_url' => route('reports.index', $filters),
            ];
        }

        return $signals;
    }
}
