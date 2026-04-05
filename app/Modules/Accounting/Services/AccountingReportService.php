<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AccountingReportService
{
    public function trialBalance(int $companyId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $balances = Account::query()
            ->where('accounts.company_id', $companyId)
            ->leftJoin('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
            ->leftJoin('journal_entries', function ($join) use ($companyId, $dateFrom, $dateTo) {
                $join->on('journal_lines.journal_entry_id', '=', 'journal_entries.id')
                    ->where('journal_entries.company_id', '=', $companyId);

                if ($dateFrom) {
                    $join->whereDate('journal_entries.entry_date', '>=', $dateFrom);
                }

                if ($dateTo) {
                    $join->whereDate('journal_entries.entry_date', '<=', $dateTo);
                }
            })
            ->select(
                'accounts.id',
                'accounts.code',
                'accounts.name',
                'accounts.type',
                'accounts.is_active'
            )
            ->selectRaw('COALESCE(SUM(CASE WHEN journal_entries.id IS NOT NULL THEN journal_lines.debit ELSE 0 END), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(CASE WHEN journal_entries.id IS NOT NULL THEN journal_lines.credit ELSE 0 END), 0) as total_credit')
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type', 'accounts.is_active')
            ->orderBy('accounts.code')
            ->get()
            ->map(function ($row) {
                $debit = (float) $row->total_debit;
                $credit = (float) $row->total_credit;
                $balance = in_array($row->type, ['asset', 'expense'], true)
                    ? $debit - $credit
                    : $credit - $debit;

                return [
                    'id' => $row->id,
                    'code' => $row->code,
                    'name' => $row->name,
                    'type' => $row->type,
                    'is_active' => (bool) $row->is_active,
                    'total_debit' => $debit,
                    'total_credit' => $credit,
                    'balance' => round($balance, 2),
                ];
            });

        $summary = [
            'debit' => round($balances->sum('total_debit'), 2),
            'credit' => round($balances->sum('total_credit'), 2),
            'result' => round(
                $balances->where('type', 'income')->sum('balance') - $balances->where('type', 'expense')->sum('balance'),
                2,
            ),
        ];

        return [$balances, $summary];
    }

    public function generalLedger(int $companyId, ?string $dateFrom = null, ?string $dateTo = null, ?int $accountId = null): array
    {
        $query = $this->generalLedgerQuery($companyId, $dateFrom, $dateTo, $accountId);

        $summary = [
            'debit' => (float) (clone $query)->sum('journal_lines.debit'),
            'credit' => (float) (clone $query)->sum('journal_lines.credit'),
        ];

        return [
            'lines' => $query->paginate(30)->withQueryString(),
            'summary' => $summary,
        ];
    }

    public function generalLedgerCollection(int $companyId, ?string $dateFrom = null, ?string $dateTo = null, ?int $accountId = null): array
    {
        $query = $this->generalLedgerQuery($companyId, $dateFrom, $dateTo, $accountId);

        $summary = [
            'debit' => (float) (clone $query)->sum('journal_lines.debit'),
            'credit' => (float) (clone $query)->sum('journal_lines.credit'),
        ];

        return [
            'lines' => $query->get(),
            'summary' => $summary,
        ];
    }

    public function incomeStatement(int $companyId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $income = $this->statementSection($companyId, 'income', $dateFrom, $dateTo);
        $expenses = $this->statementSection($companyId, 'expense', $dateFrom, $dateTo);

        return [
            'income' => $income,
            'expenses' => $expenses,
            'income_total' => round($income->sum('balance'), 2),
            'expense_total' => round($expenses->sum('balance'), 2),
            'net_result' => round($income->sum('balance') - $expenses->sum('balance'), 2),
        ];
    }

    public function balanceSheet(int $companyId, ?string $dateTo = null): array
    {
        $assets = $this->statementSection($companyId, 'asset', null, $dateTo);
        $liabilities = $this->statementSection($companyId, 'liability', null, $dateTo);
        $equity = $this->statementSection($companyId, 'equity', null, $dateTo);
        $profitLoss = $this->incomeStatement($companyId, null, $dateTo);

        $currentResult = [
            'code' => 'RESULTAT',
            'name' => 'Resultat cumule non affecte',
            'balance' => $profitLoss['net_result'],
        ];

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'current_result' => $currentResult,
            'asset_total' => round($assets->sum('balance'), 2),
            'liability_total' => round($liabilities->sum('balance'), 2),
            'equity_total' => round($equity->sum('balance') + $currentResult['balance'], 2),
        ];
    }

    private function generalLedgerQuery(int $companyId, ?string $dateFrom = null, ?string $dateTo = null, ?int $accountId = null): Builder
    {
        return JournalLine::query()
            ->select('journal_lines.*')
            ->with(['journalEntry.branch', 'account', 'partner'])
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.company_id', $companyId)
            ->when($dateFrom, fn (Builder $q) => $q->whereDate('journal_entries.entry_date', '>=', $dateFrom))
            ->when($dateTo, fn (Builder $q) => $q->whereDate('journal_entries.entry_date', '<=', $dateTo))
            ->when($accountId, fn (Builder $q) => $q->where('journal_lines.account_id', $accountId))
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entries.id')
            ->orderBy('journal_lines.id');
    }

    private function statementSection(int $companyId, string $type, ?string $dateFrom = null, ?string $dateTo = null): Collection
    {
        return Account::query()
            ->where('accounts.company_id', $companyId)
            ->where('accounts.type', $type)
            ->leftJoin('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
            ->leftJoin('journal_entries', function ($join) use ($companyId, $dateFrom, $dateTo) {
                $join->on('journal_lines.journal_entry_id', '=', 'journal_entries.id')
                    ->where('journal_entries.company_id', '=', $companyId);

                if ($dateFrom) {
                    $join->whereDate('journal_entries.entry_date', '>=', $dateFrom);
                }

                if ($dateTo) {
                    $join->whereDate('journal_entries.entry_date', '<=', $dateTo);
                }
            })
            ->select('accounts.code', 'accounts.name', 'accounts.type')
            ->selectRaw('COALESCE(SUM(CASE WHEN journal_entries.id IS NOT NULL THEN journal_lines.debit ELSE 0 END), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(CASE WHEN journal_entries.id IS NOT NULL THEN journal_lines.credit ELSE 0 END), 0) as total_credit')
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.code')
            ->get()
            ->map(function ($row) use ($type) {
                $debit = (float) $row->total_debit;
                $credit = (float) $row->total_credit;
                $balance = in_array($type, ['asset', 'expense'], true)
                    ? $debit - $credit
                    : $credit - $debit;

                return [
                    'code' => $row->code,
                    'name' => $row->name,
                    'balance' => round($balance, 2),
                ];
            })
            ->filter(fn (array $row) => abs($row['balance']) > 0.0001)
            ->values();
    }
}
