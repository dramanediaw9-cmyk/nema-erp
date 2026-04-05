<?php

namespace App\Modules\Purchases\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SupplierPerformanceService
{
    public function ranking(int $companyId, ?int $branchId = null, ?string $dateFrom = null, ?string $dateTo = null, int $limit = 8): array
    {
        return collect($this->summaries($companyId, $branchId, $dateFrom, $dateTo))
            ->sort(function (array $left, array $right): int {
                if (abs((float) $left['score'] - (float) $right['score']) > 0.0001) {
                    return (float) $left['score'] < (float) $right['score'] ? 1 : -1;
                }

                if (abs((float) $left['spend_total'] - (float) $right['spend_total']) > 0.0001) {
                    return (float) $left['spend_total'] < (float) $right['spend_total'] ? 1 : -1;
                }

                return strcmp((string) $left['supplier_name'], (string) $right['supplier_name']);
            })
            ->take($limit)
            ->values()
            ->all();
    }

    public function summaryForSupplier(int $companyId, int $supplierId, ?int $branchId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        return $this->summaries($companyId, $branchId, $dateFrom, $dateTo, [$supplierId])[$supplierId]
            ?? $this->emptySummary($supplierId, null, null);
    }

    public function scoreMap(int $companyId, array $supplierIds, ?int $branchId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        if ($supplierIds === []) {
            return [];
        }

        return collect($this->summaries($companyId, $branchId, $dateFrom, $dateTo, $supplierIds))
            ->mapWithKeys(fn (array $summary, int $supplierId) => [$supplierId => [
                'score' => (float) $summary['score'],
                'score_label' => $summary['score_label'],
            ]])
            ->all();
    }

    private function summaries(int $companyId, ?int $branchId = null, ?string $dateFrom = null, ?string $dateTo = null, ?array $supplierIds = null): array
    {
        $supplierIds = collect($supplierIds ?? [])->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();

        $receiptStats = DB::table('goods_receipts')
            ->selectRaw('purchase_order_id, MIN(receipt_date) as first_receipt_date, MAX(receipt_date) as last_receipt_date, COUNT(id) as receipt_count')
            ->where('company_id', $companyId)
            ->groupBy('purchase_order_id');

        $orderRows = DB::table('purchase_orders')
            ->leftJoinSub($receiptStats, 'receipt_stats', fn ($join) => $join->on('purchase_orders.id', '=', 'receipt_stats.purchase_order_id'))
            ->where('purchase_orders.company_id', $companyId)
            ->when($branchId, fn ($query) => $query->where('purchase_orders.branch_id', $branchId))
            ->when($dateFrom, fn ($query) => $query->whereDate('purchase_orders.order_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('purchase_orders.order_date', '<=', $dateTo))
            ->when($supplierIds !== [], fn ($query) => $query->whereIn('purchase_orders.supplier_id', $supplierIds))
            ->get([
                'purchase_orders.id',
                'purchase_orders.supplier_id',
                'purchase_orders.order_date',
                'purchase_orders.expected_receipt_date',
                'receipt_stats.first_receipt_date',
                'receipt_stats.last_receipt_date',
            ]);

        $orderStats = $orderRows
            ->groupBy('supplier_id')
            ->map(function (Collection $rows) {
                $delayValues = [];
                $leadTimeValues = [];
                $receivedOrdersCount = 0;
                $expectedOrdersCount = 0;
                $onTimeOrdersCount = 0;
                $lastReceiptDate = null;

                foreach ($rows as $row) {
                    $firstReceiptDate = $row->first_receipt_date ? Carbon::parse($row->first_receipt_date)->startOfDay() : null;
                    $expectedReceiptDate = $row->expected_receipt_date ? Carbon::parse($row->expected_receipt_date)->startOfDay() : null;
                    $orderDate = $row->order_date ? Carbon::parse($row->order_date)->startOfDay() : null;

                    if ($firstReceiptDate !== null) {
                        $receivedOrdersCount++;
                        $leadTimeValues[] = max($orderDate?->diffInDays($firstReceiptDate, false) ?? 0, 0);
                    }

                    if ($expectedReceiptDate !== null) {
                        $expectedOrdersCount++;
                    }

                    if ($firstReceiptDate !== null && $expectedReceiptDate !== null) {
                        if ($firstReceiptDate->lessThanOrEqualTo($expectedReceiptDate)) {
                            $onTimeOrdersCount++;
                        }

                        $delayValues[] = max($expectedReceiptDate->diffInDays($firstReceiptDate, false), 0);
                    }

                    if ($row->last_receipt_date !== null && ($lastReceiptDate === null || $row->last_receipt_date > $lastReceiptDate)) {
                        $lastReceiptDate = $row->last_receipt_date;
                    }
                }

                return (object) [
                    'orders_count' => $rows->count(),
                    'received_orders_count' => $receivedOrdersCount,
                    'expected_orders_count' => $expectedOrdersCount,
                    'on_time_orders_count' => $onTimeOrdersCount,
                    'avg_delay_days' => $delayValues !== [] ? array_sum($delayValues) / count($delayValues) : null,
                    'avg_lead_time_days' => $leadTimeValues !== [] ? array_sum($leadTimeValues) / count($leadTimeValues) : null,
                    'last_receipt_date' => $lastReceiptDate,
                ];
            });

        $billStats = DB::table('purchase_bills')
            ->where('purchase_bills.company_id', $companyId)
            ->where('purchase_bills.status', 'validated')
            ->when($branchId, fn ($query) => $query->where('purchase_bills.branch_id', $branchId))
            ->when($dateFrom, fn ($query) => $query->whereDate('purchase_bills.bill_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('purchase_bills.bill_date', '<=', $dateTo))
            ->when($supplierIds !== [], fn ($query) => $query->whereIn('purchase_bills.supplier_id', $supplierIds))
            ->selectRaw('purchase_bills.supplier_id')
            ->selectRaw('COUNT(purchase_bills.id) as bills_count')
            ->selectRaw('COALESCE(SUM(purchase_bills.total), 0) as spend_total')
            ->selectRaw("COALESCE(SUM(CASE WHEN purchase_bills.payment_status IN ('unpaid', 'partial') THEN purchase_bills.balance_due ELSE 0 END), 0) as open_balance")
            ->groupBy('purchase_bills.supplier_id')
            ->get()
            ->keyBy('supplier_id');

        $suppliers = DB::table('partners')
            ->where('company_id', $companyId)
            ->where(function ($query) {
                $query->where('type', 'supplier')->orWhere('type', 'both');
            })
            ->when($supplierIds !== [], fn ($query) => $query->whereIn('id', $supplierIds))
            ->get(['id', 'name', 'code', 'phone', 'is_active'])
            ->keyBy('id');

        $allSupplierIds = collect($supplierIds)
            ->merge($suppliers->keys())
            ->merge($orderStats->keys())
            ->merge($billStats->keys())
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        return $allSupplierIds
            ->mapWithKeys(function (int $supplierId) use ($suppliers, $orderStats, $billStats) {
                $supplier = $suppliers->get($supplierId);
                $orders = $orderStats->get($supplierId);
                $bills = $billStats->get($supplierId);

                $summary = $this->emptySummary(
                    supplierId: $supplierId,
                    supplierName: $supplier?->name,
                    supplierCode: $supplier?->code,
                );

                $ordersCount = (int) ($orders?->orders_count ?? 0);
                $receivedOrdersCount = (int) ($orders?->received_orders_count ?? 0);
                $expectedOrdersCount = (int) ($orders?->expected_orders_count ?? 0);
                $onTimeOrdersCount = (int) ($orders?->on_time_orders_count ?? 0);
                $avgDelayDays = $orders?->avg_delay_days;
                $avgLeadTimeDays = $orders?->avg_lead_time_days;
                $lastReceiptDate = $orders?->last_receipt_date;
                $billsCount = (int) ($bills?->bills_count ?? 0);
                $spendTotal = round((float) ($bills?->spend_total ?? 0), 2);
                $openBalance = round((float) ($bills?->open_balance ?? 0), 2);

                $summary['supplier_phone'] = $supplier?->phone;
                $summary['is_active'] = (bool) ($supplier?->is_active ?? true);
                $summary['orders_count'] = $ordersCount;
                $summary['received_orders_count'] = $receivedOrdersCount;
                $summary['expected_orders_count'] = $expectedOrdersCount;
                $summary['on_time_orders_count'] = $onTimeOrdersCount;
                $summary['avg_delay_days'] = $avgDelayDays !== null ? round((float) $avgDelayDays, 1) : null;
                $summary['avg_lead_time_days'] = $avgLeadTimeDays !== null ? round((float) $avgLeadTimeDays, 1) : null;
                $summary['last_receipt_date'] = $lastReceiptDate;
                $summary['bills_count'] = $billsCount;
                $summary['spend_total'] = $spendTotal;
                $summary['open_balance'] = $openBalance;
                $summary['on_time_rate'] = $summary['expected_orders_count'] > 0
                    ? round(($summary['on_time_orders_count'] / $summary['expected_orders_count']) * 100, 1)
                    : null;
                $summary['receipt_completion_rate'] = $summary['orders_count'] > 0
                    ? round(($summary['received_orders_count'] / $summary['orders_count']) * 100, 1)
                    : null;
                $summary['open_balance_ratio'] = $summary['spend_total'] > 0
                    ? round(($summary['open_balance'] / $summary['spend_total']) * 100, 1)
                    : null;

                $summary['score'] = $this->score($summary);
                $summary['score_label'] = $this->scoreLabel($summary['score']);

                return [$supplierId => $summary];
            })
            ->all();
    }

    private function emptySummary(int $supplierId, ?string $supplierName, ?string $supplierCode): array
    {
        return [
            'supplier_id' => $supplierId,
            'supplier_name' => $supplierName ?: 'Fournisseur #'.$supplierId,
            'supplier_code' => $supplierCode,
            'supplier_phone' => null,
            'is_active' => true,
            'orders_count' => 0,
            'received_orders_count' => 0,
            'expected_orders_count' => 0,
            'on_time_orders_count' => 0,
            'bills_count' => 0,
            'spend_total' => 0.0,
            'open_balance' => 0.0,
            'on_time_rate' => null,
            'receipt_completion_rate' => null,
            'avg_delay_days' => null,
            'avg_lead_time_days' => null,
            'open_balance_ratio' => null,
            'last_receipt_date' => null,
            'score' => 0.0,
            'score_label' => 'Sans historique',
        ];
    }

    private function score(array $summary): float
    {
        $onTimeScore = $summary['on_time_rate'] !== null ? ((float) $summary['on_time_rate'] / 100) * 45 : 18;
        $receiptScore = $summary['receipt_completion_rate'] !== null ? ((float) $summary['receipt_completion_rate'] / 100) * 25 : 10;
        $delayScore = $summary['avg_delay_days'] !== null ? max(0, 15 - min((float) $summary['avg_delay_days'], 15)) : 7;
        $activityScore = min(10, (float) $summary['orders_count'] * 2);
        $financeScore = $summary['open_balance_ratio'] !== null ? max(0, 10 - min((float) $summary['open_balance_ratio'] / 8, 10)) : 5;

        return round(min(100, $onTimeScore + $receiptScore + $delayScore + $activityScore + $financeScore), 1);
    }

    private function scoreLabel(float $score): string
    {
        return match (true) {
            $score >= 85 => 'Excellent',
            $score >= 70 => 'Solide',
            $score >= 55 => 'A surveiller',
            $score > 0 => 'Risque',
            default => 'Sans historique',
        };
    }
}

