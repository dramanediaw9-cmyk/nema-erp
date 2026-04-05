<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Support\Carbon;

class PeriodChecklistService
{
    public function currentPeriodSummary(?int $companyId): ?array
    {
        if (! $companyId) {
            return null;
        }

        $today = now()->toDateString();

        $period = AccountingPeriod::query()
            ->where('company_id', $companyId)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->first();

        if (! $period) {
            return null;
        }

        return $this->summaryForPeriod($period);
    }

    public function summaryForPeriod(AccountingPeriod $period): array
    {
        return $this->summaryForRange(
            companyId: $period->company_id,
            startDate: $period->start_date?->toDateString() ?? now()->startOfMonth()->toDateString(),
            endDate: $period->end_date?->toDateString() ?? now()->endOfMonth()->toDateString(),
            period: $period,
        );
    }

    public function summaryForRange(int $companyId, string $startDate, string $endDate, ?AccountingPeriod $period = null): array
    {
        $pendingSales = SalesInvoice::query()
            ->where('company_id', $companyId)
            ->where('status', 'pending_approval')
            ->whereDate('invoice_date', '>=', $startDate)
            ->whereDate('invoice_date', '<=', $endDate);

        $pendingPurchases = PurchaseBill::query()
            ->where('company_id', $companyId)
            ->where('status', 'pending_approval')
            ->whereDate('bill_date', '>=', $startDate)
            ->whereDate('bill_date', '<=', $endDate);

        $pendingExpenses = Expense::query()
            ->where('company_id', $companyId)
            ->where('status', 'pending_approval')
            ->whereDate('expense_date', '>=', $startDate)
            ->whereDate('expense_date', '<=', $endDate);

        $openSales = SalesInvoice::query()
            ->where('company_id', $companyId)
            ->where('status', 'validated')
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->whereDate('invoice_date', '>=', $startDate)
            ->whereDate('invoice_date', '<=', $endDate);

        $openPurchases = PurchaseBill::query()
            ->where('company_id', $companyId)
            ->where('status', 'validated')
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->whereDate('bill_date', '>=', $startDate)
            ->whereDate('bill_date', '<=', $endDate);

        $openExpenses = Expense::query()
            ->where('company_id', $companyId)
            ->where('status', 'validated')
            ->where('payment_status', 'unpaid')
            ->whereDate('expense_date', '>=', $startDate)
            ->whereDate('expense_date', '<=', $endDate);

        $entriesCount = JournalEntry::query()
            ->where('company_id', $companyId)
            ->whereDate('entry_date', '>=', $startDate)
            ->whereDate('entry_date', '<=', $endDate)
            ->count();

        $pendingSalesCount = $pendingSales->count();
        $pendingPurchasesCount = $pendingPurchases->count();
        $pendingExpensesCount = $pendingExpenses->count();
        $openSalesCount = $openSales->count();
        $openPurchasesCount = $openPurchases->count();
        $openExpensesCount = $openExpenses->count();

        $blockers = $pendingSalesCount + $pendingPurchasesCount + $pendingExpensesCount;
        $warnings = $openSalesCount + $openPurchasesCount + $openExpensesCount;
        $canClose = $blockers === 0;

        $status = $blockers > 0
            ? 'blocked'
            : ($warnings > 0 ? 'warning' : 'ready');

        $checklist = [
            [
                'title' => 'Ventes en attente d approbation',
                'count' => $pendingSalesCount,
                'level' => 'blocker',
                'state' => $pendingSalesCount > 0 ? 'blocked' : 'ok',
                'message' => $pendingSalesCount > 0
                    ? 'Les ventes non approuvees bloquent la cloture.'
                    : 'Aucune vente en attente sur la periode.',
            ],
            [
                'title' => 'Achats en attente d approbation',
                'count' => $pendingPurchasesCount,
                'level' => 'blocker',
                'state' => $pendingPurchasesCount > 0 ? 'blocked' : 'ok',
                'message' => $pendingPurchasesCount > 0
                    ? 'Les achats non approuves bloquent la cloture.'
                    : 'Aucun achat en attente sur la periode.',
            ],
            [
                'title' => 'Depenses en attente d approbation',
                'count' => $pendingExpensesCount,
                'level' => 'blocker',
                'state' => $pendingExpensesCount > 0 ? 'blocked' : 'ok',
                'message' => $pendingExpensesCount > 0
                    ? 'Les depenses non approuvees bloquent la cloture.'
                    : 'Aucune depense en attente sur la periode.',
            ],
            [
                'title' => 'Creances clients ouvertes',
                'count' => $openSalesCount,
                'level' => 'warning',
                'state' => $openSalesCount > 0 ? 'warning' : 'ok',
                'message' => $openSalesCount > 0
                    ? number_format((float) $openSales->sum('balance_due'), 0, ',', ' ').' XOF restent a encaisser.'
                    : 'Aucune creance client ouverte sur la periode.',
            ],
            [
                'title' => 'Dettes fournisseurs ouvertes',
                'count' => $openPurchasesCount,
                'level' => 'warning',
                'state' => $openPurchasesCount > 0 ? 'warning' : 'ok',
                'message' => $openPurchasesCount > 0
                    ? number_format((float) $openPurchases->sum('balance_due'), 0, ',', ' ').' XOF restent a regler.'
                    : 'Aucune dette fournisseur ouverte sur la periode.',
            ],
            [
                'title' => 'Depenses approuvees non reglees',
                'count' => $openExpensesCount,
                'level' => 'warning',
                'state' => $openExpensesCount > 0 ? 'warning' : 'ok',
                'message' => $openExpensesCount > 0
                    ? number_format((float) $openExpenses->sum('total'), 0, ',', ' ').' XOF de depenses restent a solder.'
                    : 'Toutes les depenses approuvees sont reglees.',
            ],
        ];

        return [
            'period' => $period,
            'start_date' => Carbon::parse($startDate),
            'end_date' => Carbon::parse($endDate),
            'status' => $status,
            'can_close' => $canClose,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'pending_sales_count' => $pendingSalesCount,
            'pending_purchases_count' => $pendingPurchasesCount,
            'pending_expenses_count' => $pendingExpensesCount,
            'open_sales_count' => $openSalesCount,
            'open_sales_amount' => (float) $openSales->sum('balance_due'),
            'open_purchases_count' => $openPurchasesCount,
            'open_purchases_amount' => (float) $openPurchases->sum('balance_due'),
            'open_expenses_count' => $openExpensesCount,
            'open_expenses_amount' => (float) $openExpenses->sum('total'),
            'journal_entries_count' => $entriesCount,
            'checklist' => $checklist,
        ];
    }
}
