<?php

namespace App\Modules\Sales\Services;

use App\Modules\Inventory\Services\StockService;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesOrderItem;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class OrderCoverageService
{
    public function __construct(
        private readonly StockService $stockService,
    ) {}

    public function snapshotForOrder(SalesOrder $order): array
    {
        $order->loadMissing(['items.product']);

        $lineCoverage = $order->items
            ->mapWithKeys(fn (SalesOrderItem $item) => [$item->id => $this->lineCoverageSnapshot($order, $item)])
            ->all();

        $summary = $this->coverageSummary($lineCoverage);
        $remainingQty = round((float) $order->items->sum(fn (SalesOrderItem $item) => $item->remainingQty()), 3);
        $targetDate = $order->commitment_date ?? $order->requested_delivery_date;
        $isOverdue = $remainingQty > 0.0001 && $targetDate && $targetDate->startOfDay()->lt(now()->startOfDay());
        $atRiskShortageQty = round((float) collect($lineCoverage)
            ->filter(fn (array $coverage) => in_array($coverage['status'], ['at_risk', 'uncovered'], true))
            ->sum(fn (array $coverage) => (float) ($coverage['shortage_qty'] ?? 0)), 3);

        [$promiseLabel, $promiseTone, $coverageState] = match (true) {
            $remainingQty <= 0.0001 => ['Soldee', 'badge-success', 'completed'],
            $summary['at_risk'] > 0 => ['Commande a risque', 'badge-danger', 'at_risk'],
            $summary['covered_incoming'] > 0 => ['Couvert par appro', 'badge-warning', 'incoming'],
            $summary['covered_now'] > 0 => ['Couvert maintenant', 'badge-success', 'covered_now'],
            default => ['Sans contrainte stock', 'badge-muted', 'service'],
        };

        return [
            'line_coverage' => $lineCoverage,
            'summary' => $summary,
            'remaining_qty' => $remainingQty,
            'target_date' => $targetDate,
            'is_overdue' => $isOverdue,
            'coverage_state' => $coverageState,
            'promise_label' => $promiseLabel,
            'promise_tone' => $promiseTone,
            'at_risk_shortage_qty' => $atRiskShortageQty,
        ];
    }

    public function lineCoverageSnapshot(SalesOrder $order, SalesOrderItem $item): array
    {
        $product = $item->product;
        $remainingQty = round($item->remainingQty(), 3);

        if (! $product || $product->type !== 'stockable') {
            return [
                'status' => 'service',
                'label' => 'Sans contrainte stock',
                'tone' => 'badge-muted',
                'required_qty' => $remainingQty,
                'available_now' => null,
                'incoming_qty' => 0.0,
                'coverable_qty' => null,
                'shortage_qty' => 0.0,
                'next_incoming_date' => null,
                'detail' => 'Article non stockable ou service.',
            ];
        }

        if ($remainingQty <= 0.0001) {
            return [
                'status' => 'completed',
                'label' => 'Entierement livre',
                'tone' => 'badge-success',
                'required_qty' => 0.0,
                'available_now' => 0.0,
                'incoming_qty' => 0.0,
                'coverable_qty' => 0.0,
                'shortage_qty' => 0.0,
                'next_incoming_date' => null,
                'detail' => 'Aucune quantite restante a couvrir.',
            ];
        }

        $excludeOrderId = in_array($order->status, ['confirmed', 'partial_delivered'], true) ? $order->id : null;
        $coverage = $this->stockService->forecastCoverage(
            $product,
            $order->company_id,
            $order->branch_id,
            $remainingQty,
            $order->warehouse_id,
            $excludeOrderId,
        );

        $isReservedFlow = in_array($order->status, ['confirmed', 'partial_delivered'], true);

        if ($coverage['required_qty'] <= $coverage['available_now']) {
            return [
                ...$coverage,
                'status' => $isReservedFlow ? 'reserved' : 'available',
                'label' => $isReservedFlow ? 'Reserve sur stock' : 'Disponible immediatement',
                'tone' => 'badge-success',
                'detail' => $isReservedFlow
                    ? 'Le depot peut couvrir le reliquat sans nouvel achat.'
                    : 'Le stock disponible apres reservations couvre deja cette ligne.',
            ];
        }

        if ($coverage['required_qty'] <= $coverage['coverable_qty']) {
            $dateLabel = $coverage['next_incoming_date'] ? $coverage['next_incoming_date']->format('d/m/Y') : 'date a confirmer';

            return [
                ...$coverage,
                'status' => $isReservedFlow ? 'secured_incoming' : 'incoming',
                'label' => $isReservedFlow ? 'Couvert avec appro attendu' : 'Couvert par approvisionnement',
                'tone' => 'badge-warning',
                'detail' => 'Complement attendu le '.$dateLabel.'.',
            ];
        }

        return [
            ...$coverage,
            'status' => $isReservedFlow ? 'at_risk' : 'uncovered',
            'label' => $isReservedFlow ? 'Reservation a risque' : 'Rupture a couvrir',
            'tone' => 'badge-danger',
            'detail' => 'Il manque '.number_format((float) $coverage['shortage_qty'], 3, ',', ' ').' apres le stock et les achats attendus.',
        ];
    }

    public function coverageSummary(array $lineCoverage): array
    {
        $summary = [
            'covered_now' => 0,
            'covered_incoming' => 0,
            'at_risk' => 0,
            'service' => 0,
        ];

        foreach ($lineCoverage as $coverage) {
            switch ($coverage['status']) {
                case 'available':
                case 'reserved':
                case 'completed':
                    $summary['covered_now']++;
                    break;
                case 'incoming':
                case 'secured_incoming':
                    $summary['covered_incoming']++;
                    break;
                case 'service':
                    $summary['service']++;
                    break;
                default:
                    $summary['at_risk']++;
                    break;
            }
        }

        $summary['total'] = count($lineCoverage);

        return $summary;
    }

    public function atRiskCoverageRows(SalesOrder $order, array $lineCoverage): Collection
    {
        return $order->items
            ->map(function (SalesOrderItem $item) use ($lineCoverage) {
                $coverage = $lineCoverage[$item->id] ?? null;

                if (! $coverage || ! in_array($coverage['status'], ['at_risk', 'uncovered'], true)) {
                    return null;
                }

                if ((float) ($coverage['shortage_qty'] ?? 0) <= 0.0001) {
                    return null;
                }

                return [
                    'item' => $item,
                    'coverage' => $coverage,
                ];
            })
            ->filter()
            ->values();
    }

    public function filterOrders(Collection|EloquentCollection $orders, ?string $coverageState = null, ?string $deliveryFocus = null): Collection
    {
        return collect($orders)
            ->filter(function (SalesOrder $order) use ($coverageState, $deliveryFocus): bool {
                $snapshot = $this->snapshotForOrder($order);

                if ($coverageState && $snapshot['coverage_state'] !== $coverageState) {
                    return false;
                }

                return match ($deliveryFocus) {
                    'remaining' => $snapshot['remaining_qty'] > 0.0001,
                    'overdue' => $snapshot['is_overdue'],
                    default => true,
                };
            })
            ->values();
    }

    public function wholesalePortfolioSummary(int $companyId, ?int $branchId = null): array
    {
        $orders = SalesOrder::query()
            ->with(['items.product'])
            ->where('company_id', $companyId)
            ->when($branchId, fn ($query, $selectedBranchId) => $query->where('branch_id', $selectedBranchId))
            ->whereIn('status', ['confirmed', 'partial_delivered'])
            ->orderBy('order_date')
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            return [
                'orders_at_risk_count' => 0,
                'order_lines_at_risk_count' => 0,
                'at_risk_shortage_qty' => 0.0,
                'orders_incoming_cover_count' => 0,
                'order_lines_incoming_cover_count' => 0,
                'overdue_backlog_orders_count' => 0,
                'overdue_backlog_remaining_qty' => 0.0,
                'oldest_overdue_target_date' => null,
                'highlights' => [],
                'at_risk_highlights' => [],
                'overdue_highlights' => [],
            ];
        }

        $snapshots = $orders
            ->map(fn (SalesOrder $order) => [
                'order' => $order,
                'snapshot' => $this->snapshotForOrder($order),
            ]);

        $atRiskOrders = $snapshots->filter(fn (array $row) => $row['snapshot']['coverage_state'] === 'at_risk');
        $incomingOrders = $snapshots->filter(fn (array $row) => $row['snapshot']['coverage_state'] === 'incoming');
        $overdueBacklogOrders = $snapshots->filter(fn (array $row) => $row['snapshot']['is_overdue']);
        $atRiskHighlights = $atRiskOrders
            ->take(5)
            ->map(fn (array $row) => $row['order']->order_number)
            ->values()
            ->all();
        $overdueHighlights = $overdueBacklogOrders
            ->sortBy(fn (array $row) => $row['snapshot']['target_date']?->timestamp ?? PHP_INT_MAX)
            ->take(5)
            ->map(fn (array $row) => $row['order']->order_number)
            ->values()
            ->all();

        return [
            'orders_at_risk_count' => $atRiskOrders->count(),
            'order_lines_at_risk_count' => (int) $atRiskOrders->sum(fn (array $row) => (int) ($row['snapshot']['summary']['at_risk'] ?? 0)),
            'at_risk_shortage_qty' => round((float) $atRiskOrders->sum(fn (array $row) => (float) ($row['snapshot']['at_risk_shortage_qty'] ?? 0)), 3),
            'orders_incoming_cover_count' => $incomingOrders->count(),
            'order_lines_incoming_cover_count' => (int) $incomingOrders->sum(fn (array $row) => (int) ($row['snapshot']['summary']['covered_incoming'] ?? 0)),
            'overdue_backlog_orders_count' => $overdueBacklogOrders->count(),
            'overdue_backlog_remaining_qty' => round((float) $overdueBacklogOrders->sum(fn (array $row) => (float) ($row['snapshot']['remaining_qty'] ?? 0)), 3),
            'oldest_overdue_target_date' => $overdueBacklogOrders
                ->map(fn (array $row) => $row['snapshot']['target_date'])
                ->filter()
                ->sortBy(fn (Carbon $date) => $date->timestamp)
                ->first(),
            'highlights' => $atRiskHighlights,
            'at_risk_highlights' => $atRiskHighlights,
            'overdue_highlights' => $overdueHighlights,
        ];
    }
}
