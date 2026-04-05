<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Expenses\Models\Expense;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Treasury\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportingService
{
    public function salesSummary(int $companyId, ?string $dateFrom = null, ?string $dateTo = null, ?int $branchId = null): array
    {
        $query = SalesInvoice::query()->where('company_id', $companyId)->where('status', 'validated');
        $this->applyBranchScope($query, $branchId);
        $this->applyDateRange($query, 'invoice_date', $dateFrom, $dateTo);

        return [
            'count' => (clone $query)->count(),
            'total' => (float) (clone $query)->sum('total'),
            'paid' => (float) (clone $query)->sum('amount_paid'),
            'due' => (float) (clone $query)->sum('balance_due'),
        ];
    }

    public function purchasesSummary(int $companyId, ?string $dateFrom = null, ?string $dateTo = null, ?int $branchId = null): array
    {
        $query = PurchaseBill::query()->where('company_id', $companyId)->where('status', 'validated');
        $this->applyBranchScope($query, $branchId);
        $this->applyDateRange($query, 'bill_date', $dateFrom, $dateTo);

        return [
            'count' => (clone $query)->count(),
            'total' => (float) (clone $query)->sum('total'),
            'paid' => (float) (clone $query)->sum('amount_paid'),
            'due' => (float) (clone $query)->sum('balance_due'),
        ];
    }

    public function treasurySummary(int $companyId, ?string $dateFrom = null, ?string $dateTo = null, ?int $branchId = null): array
    {
        $query = Payment::query()->where('company_id', $companyId);
        $this->applyBranchScope($query, $branchId);
        $this->applyDateRange($query, 'payment_date', $dateFrom, $dateTo);

        return [
            'in' => (float) (clone $query)->where('direction', 'in')->sum('amount'),
            'out' => (float) (clone $query)->where('direction', 'out')->sum('amount'),
            'count' => (clone $query)->count(),
        ];
    }

    public function expensesSummary(int $companyId, ?string $dateFrom = null, ?string $dateTo = null, ?int $branchId = null): array
    {
        $query = Expense::query()->where('company_id', $companyId)->where('status', 'validated');
        $this->applyBranchScope($query, $branchId);
        $this->applyDateRange($query, 'expense_date', $dateFrom, $dateTo);

        return [
            'count' => (clone $query)->count(),
            'total' => (float) (clone $query)->sum('total'),
            'unpaid' => (clone $query)->where('payment_status', 'unpaid')->count(),
        ];
    }

    public function receivablesSummary(int $companyId, ?int $branchId = null): array
    {
        $query = SalesInvoice::query()
            ->where('company_id', $companyId)
            ->where('status', 'validated')
            ->whereIn('payment_status', ['unpaid', 'partial']);
        $this->applyBranchScope($query, $branchId);

        return [
            'count' => (clone $query)->count(),
            'total' => (float) (clone $query)->sum('balance_due'),
        ];
    }

    public function payablesSummary(int $companyId, ?int $branchId = null): array
    {
        $query = PurchaseBill::query()
            ->where('company_id', $companyId)
            ->where('status', 'validated')
            ->whereIn('payment_status', ['unpaid', 'partial']);
        $this->applyBranchScope($query, $branchId);

        return [
            'count' => (clone $query)->count(),
            'total' => (float) (clone $query)->sum('balance_due'),
        ];
    }

    public function stockSummary(int $companyId, ?int $branchId = null): array
    {
        $balances = StockMovement::query()
            ->select('product_id')
            ->selectRaw('SUM(quantity_in - quantity_out) as current_stock')
            ->where('company_id', $companyId)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->groupBy('product_id');

        $rows = DB::table('products')
            ->leftJoinSub($balances, 'balances', fn ($join) => $join->on('products.id', '=', 'balances.product_id'))
            ->where('products.company_id', $companyId)
            ->where('products.type', 'stockable')
            ->selectRaw('COUNT(products.id) as product_count')
            ->selectRaw('COALESCE(SUM(COALESCE(balances.current_stock, 0) * products.purchase_price), 0) as valuation')
            ->selectRaw('SUM(CASE WHEN COALESCE(balances.current_stock, 0) <= products.min_stock THEN 1 ELSE 0 END) as alerts')
            ->first();

        return [
            'product_count' => (int) ($rows->product_count ?? 0),
            'valuation' => (float) ($rows->valuation ?? 0),
            'alerts' => (int) ($rows->alerts ?? 0),
        ];
    }

    public function grossMarginSummary(int $companyId, ?string $dateFrom = null, ?string $dateTo = null, ?int $branchId = null): array
    {
        $query = DB::table('sales_invoice_items')
            ->join('sales_invoices', 'sales_invoices.id', '=', 'sales_invoice_items.sales_invoice_id')
            ->join('products', 'products.id', '=', 'sales_invoice_items.product_id')
            ->where('sales_invoices.company_id', $companyId)
            ->where('sales_invoices.status', 'validated');

        $this->applyBranchScope($query, $branchId, 'sales_invoices.branch_id');
        $this->applyDateRange($query, 'sales_invoices.invoice_date', $dateFrom, $dateTo);

        $row = $query
            ->selectRaw('COALESCE(SUM(sales_invoice_items.line_total), 0) as revenue')
            ->selectRaw('COALESCE(SUM(sales_invoice_items.qty * products.purchase_price), 0) as estimated_cost')
            ->first();

        $revenue = (float) ($row->revenue ?? 0);
        $estimatedCost = (float) ($row->estimated_cost ?? 0);
        $margin = $revenue - $estimatedCost;

        return [
            'revenue' => $revenue,
            'estimated_cost' => $estimatedCost,
            'margin' => $margin,
            'rate' => $revenue > 0 ? round(($margin / $revenue) * 100, 1) : 0.0,
        ];
    }

    public function periodComparison(int $companyId, string $dateFrom, string $dateTo, ?int $branchId = null): array
    {
        $window = $this->comparisonWindow($dateFrom, $dateTo);

        $currentSales = $this->salesSummary($companyId, $dateFrom, $dateTo, $branchId);
        $previousSales = $this->salesSummary($companyId, $window['previous_from'], $window['previous_to'], $branchId);
        $currentMargin = $this->grossMarginSummary($companyId, $dateFrom, $dateTo, $branchId);
        $previousMargin = $this->grossMarginSummary($companyId, $window['previous_from'], $window['previous_to'], $branchId);
        $currentTreasury = $this->treasurySummary($companyId, $dateFrom, $dateTo, $branchId);
        $previousTreasury = $this->treasurySummary($companyId, $window['previous_from'], $window['previous_to'], $branchId);
        $currentExpenses = $this->expensesSummary($companyId, $dateFrom, $dateTo, $branchId);
        $previousExpenses = $this->expensesSummary($companyId, $window['previous_from'], $window['previous_to'], $branchId);

        return [
            'window' => $window,
            'sales' => $this->comparisonMetric($currentSales['total'], $previousSales['total']),
            'margin' => $this->comparisonMetric($currentMargin['margin'], $previousMargin['margin']),
            'cash_net' => $this->comparisonMetric(
                (float) $currentTreasury['in'] - (float) $currentTreasury['out'],
                (float) $previousTreasury['in'] - (float) $previousTreasury['out'],
            ),
            'expenses' => $this->comparisonMetric($currentExpenses['total'], $previousExpenses['total']),
        ];
    }

    public function topProducts(int $companyId, ?string $dateFrom = null, ?string $dateTo = null, ?int $branchId = null, int $limit = 8): array
    {
        return DB::table('sales_invoice_items')
            ->join('sales_invoices', 'sales_invoices.id', '=', 'sales_invoice_items.sales_invoice_id')
            ->join('products', 'products.id', '=', 'sales_invoice_items.product_id')
            ->leftJoin('product_categories', 'product_categories.id', '=', 'products.category_id')
            ->where('sales_invoices.company_id', $companyId)
            ->where('sales_invoices.status', 'validated')
            ->when($branchId, fn ($query) => $query->where('sales_invoices.branch_id', $branchId))
            ->when($dateFrom, fn ($query) => $query->whereDate('sales_invoices.invoice_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('sales_invoices.invoice_date', '<=', $dateTo))
            ->selectRaw('products.id, products.name, products.sku, product_categories.name as category_name')
            ->selectRaw('COALESCE(SUM(sales_invoice_items.qty), 0) as qty')
            ->selectRaw('COALESCE(SUM(sales_invoice_items.line_total), 0) as amount')
            ->selectRaw('COALESCE(SUM(sales_invoice_items.line_total - (sales_invoice_items.qty * products.purchase_price)), 0) as estimated_margin')
            ->groupBy('products.id', 'products.name', 'products.sku', 'product_categories.name')
            ->orderByDesc('amount')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'sku' => $row->sku,
                'category_name' => $row->category_name,
                'qty' => (float) $row->qty,
                'amount' => (float) $row->amount,
                'estimated_margin' => (float) $row->estimated_margin,
            ])
            ->all();
    }

    public function marginByCategory(int $companyId, ?string $dateFrom = null, ?string $dateTo = null, ?int $branchId = null, int $limit = 8): array
    {
        return DB::table('sales_invoice_items')
            ->join('sales_invoices', 'sales_invoices.id', '=', 'sales_invoice_items.sales_invoice_id')
            ->join('products', 'products.id', '=', 'sales_invoice_items.product_id')
            ->leftJoin('product_categories', 'product_categories.id', '=', 'products.category_id')
            ->where('sales_invoices.company_id', $companyId)
            ->where('sales_invoices.status', 'validated')
            ->when($branchId, fn ($query) => $query->where('sales_invoices.branch_id', $branchId))
            ->when($dateFrom, fn ($query) => $query->whereDate('sales_invoices.invoice_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('sales_invoices.invoice_date', '<=', $dateTo))
            ->selectRaw("COALESCE(product_categories.name, 'Sans categorie') as category_name")
            ->selectRaw('COALESCE(SUM(sales_invoice_items.qty), 0) as qty')
            ->selectRaw('COALESCE(SUM(sales_invoice_items.line_total), 0) as amount')
            ->selectRaw('COALESCE(SUM(sales_invoice_items.line_total - (sales_invoice_items.qty * products.purchase_price)), 0) as estimated_margin')
            ->groupBy('category_name')
            ->orderByDesc('estimated_margin')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $amount = (float) $row->amount;
                $margin = (float) $row->estimated_margin;

                return [
                    'category_name' => $row->category_name,
                    'qty' => (float) $row->qty,
                    'amount' => $amount,
                    'estimated_margin' => $margin,
                    'rate' => $amount > 0 ? round(($margin / $amount) * 100, 1) : 0.0,
                ];
            })
            ->all();
    }

    public function topCustomers(int $companyId, ?string $dateFrom = null, ?string $dateTo = null, ?int $branchId = null, int $limit = 8): array
    {
        return DB::table('sales_invoices')
            ->join('partners', 'partners.id', '=', 'sales_invoices.customer_id')
            ->where('sales_invoices.company_id', $companyId)
            ->where('sales_invoices.status', 'validated')
            ->when($branchId, fn ($query) => $query->where('sales_invoices.branch_id', $branchId))
            ->when($dateFrom, fn ($query) => $query->whereDate('sales_invoices.invoice_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('sales_invoices.invoice_date', '<=', $dateTo))
            ->selectRaw('partners.id, partners.name, partners.code')
            ->selectRaw('COUNT(sales_invoices.id) as invoice_count')
            ->selectRaw('COALESCE(SUM(sales_invoices.total), 0) as total')
            ->selectRaw('COALESCE(SUM(sales_invoices.balance_due), 0) as due')
            ->groupBy('partners.id', 'partners.name', 'partners.code')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'code' => $row->code,
                'invoice_count' => (int) $row->invoice_count,
                'total' => (float) $row->total,
                'due' => (float) $row->due,
            ])
            ->all();
    }

    public function salesByBranch(int $companyId, string $dateFrom, string $dateTo): array
    {
        $window = $this->comparisonWindow($dateFrom, $dateTo);

        $branches = DB::table('branches')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $currentRows = DB::table('sales_invoices')
            ->where('company_id', $companyId)
            ->where('status', 'validated')
            ->whereDate('invoice_date', '>=', $dateFrom)
            ->whereDate('invoice_date', '<=', $dateTo)
            ->selectRaw('branch_id, COUNT(id) as invoice_count, COALESCE(SUM(total), 0) as total, COALESCE(SUM(balance_due), 0) as due')
            ->groupBy('branch_id')
            ->get()
            ->keyBy('branch_id');

        $previousTotals = DB::table('sales_invoices')
            ->where('company_id', $companyId)
            ->where('status', 'validated')
            ->whereDate('invoice_date', '>=', $window['previous_from'])
            ->whereDate('invoice_date', '<=', $window['previous_to'])
            ->selectRaw('branch_id, COALESCE(SUM(total), 0) as total')
            ->groupBy('branch_id')
            ->pluck('total', 'branch_id');

        return collect($branches)
            ->map(function ($branch) use ($currentRows, $previousTotals) {
                $current = $currentRows->get($branch->id);
                $currentTotal = (float) ($current->total ?? 0);
                $previousTotal = (float) ($previousTotals[$branch->id] ?? 0);
                $comparison = $this->comparisonMetric($currentTotal, $previousTotal);

                return [
                    'branch_id' => (int) $branch->id,
                    'branch_name' => $branch->name,
                    'invoice_count' => (int) ($current->invoice_count ?? 0),
                    'total' => $currentTotal,
                    'due' => (float) ($current->due ?? 0),
                    'previous_total' => $previousTotal,
                    'delta_value' => $comparison['delta_value'],
                    'delta_percent' => $comparison['delta_percent'],
                    'direction' => $comparison['direction'],
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    public function dormantProducts(int $companyId, ?int $branchId = null, int $days = 30, int $limit = 8): array
    {
        $balances = StockMovement::query()
            ->select('product_id')
            ->selectRaw('SUM(quantity_in - quantity_out) as current_stock')
            ->where('company_id', $companyId)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->groupBy('product_id');

        $recentSales = DB::table('sales_invoice_items')
            ->join('sales_invoices', 'sales_invoices.id', '=', 'sales_invoice_items.sales_invoice_id')
            ->where('sales_invoices.company_id', $companyId)
            ->where('sales_invoices.status', 'validated')
            ->when($branchId, fn ($query) => $query->where('sales_invoices.branch_id', $branchId))
            ->whereDate('sales_invoices.invoice_date', '>=', now()->subDays($days)->toDateString())
            ->selectRaw('sales_invoice_items.product_id, COALESCE(SUM(sales_invoice_items.qty), 0) as recent_qty')
            ->groupBy('sales_invoice_items.product_id');

        return DB::table('products')
            ->leftJoin('product_categories', 'product_categories.id', '=', 'products.category_id')
            ->leftJoinSub($balances, 'balances', fn ($join) => $join->on('products.id', '=', 'balances.product_id'))
            ->leftJoinSub($recentSales, 'recent_sales', fn ($join) => $join->on('products.id', '=', 'recent_sales.product_id'))
            ->where('products.company_id', $companyId)
            ->where('products.type', 'stockable')
            ->where('products.is_active', true)
            ->whereRaw('COALESCE(balances.current_stock, 0) > 0.0001')
            ->whereRaw('COALESCE(recent_sales.recent_qty, 0) <= 0.0001')
            ->selectRaw("products.id, products.name, products.sku, COALESCE(product_categories.name, 'Sans categorie') as category_name")
            ->selectRaw('COALESCE(balances.current_stock, 0) as current_stock')
            ->selectRaw('products.purchase_price')
            ->selectRaw('COALESCE(balances.current_stock, 0) * products.purchase_price as stock_value')
            ->orderByDesc('stock_value')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'sku' => $row->sku,
                'category_name' => $row->category_name,
                'current_stock' => (float) $row->current_stock,
                'purchase_price' => (float) $row->purchase_price,
                'stock_value' => (float) $row->stock_value,
            ])
            ->all();
    }

    private function comparisonWindow(string $dateFrom, string $dateTo): array
    {
        $start = Carbon::parse($dateFrom);
        $end = Carbon::parse($dateTo);
        $days = $start->diffInDays($end) + 1;
        $previousEnd = $start->copy()->subDay();
        $previousStart = $previousEnd->copy()->subDays($days - 1);

        return [
            'current_from' => $start->toDateString(),
            'current_to' => $end->toDateString(),
            'previous_from' => $previousStart->toDateString(),
            'previous_to' => $previousEnd->toDateString(),
        ];
    }

    private function comparisonMetric(float $current, float $previous): array
    {
        $deltaValue = $current - $previous;

        if (abs($previous) < 0.0001) {
            $deltaPercent = $current > 0 ? 100.0 : 0.0;
        } else {
            $deltaPercent = round(($deltaValue / $previous) * 100, 1);
        }

        return [
            'current' => $current,
            'previous' => $previous,
            'delta_value' => $deltaValue,
            'delta_percent' => $deltaPercent,
            'direction' => $deltaValue > 0.0001 ? 'up' : (abs($deltaValue) <= 0.0001 ? 'flat' : 'down'),
        ];
    }

    private function applyDateRange($query, string $column, ?string $dateFrom, ?string $dateTo): void
    {
        if ($dateFrom) {
            $query->whereDate($column, '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate($column, '<=', $dateTo);
        }
    }

    private function applyBranchScope($query, ?int $branchId, string $column = 'branch_id'): void
    {
        if ($branchId) {
            $query->where($column, $branchId);
        }
    }
}
