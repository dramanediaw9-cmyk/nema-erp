<?php

namespace App\Modules\Treasury\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Models\Payment;
use App\Modules\Treasury\Models\TreasuryReconciliation;
use App\Modules\Treasury\Services\TreasuryReconciliationService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TreasuryReconciliationController extends Controller
{
    public function __construct(
        private readonly TreasuryReconciliationService $reconciliationService,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function index(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $reconciliations = TreasuryReconciliation::query()
            ->with(['cashAccount', 'creator', 'branch'])
            ->where('company_id', $companyId)
            ->latest('statement_date')
            ->latest('id')
            ->paginate(15);

        $all = TreasuryReconciliation::query()->where('company_id', $companyId)->get();

        return view('treasury-reconciliations.index', [
            'reconciliations' => $reconciliations,
            'summary' => [
                'count' => $all->count(),
                'balanced_count' => $all->where('status', 'balanced')->count(),
                'gap_count' => $all->where('status', 'with_gap')->count(),
                'payments_count' => (int) $all->sum('payments_count'),
            ],
        ]);
    }

    public function create(Request $request, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $cashAccounts = CashAccount::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('type', ['bank', 'mobile_money'])
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'opening_balance']);

        $selectedCashAccount = null;
        $candidates = collect();
        $bookBalance = null;
        $candidateSignedTotal = null;
        $candidateInsights = [
            'documented_count' => 0,
            'documented_amount' => 0.0,
            'documented_stale_count' => 0,
            'missing_proof_count' => 0,
            'missing_proof_amount' => 0.0,
        ];
        $candidateIndicators = [];
        $statementDate = $request->string('statement_date')->value() ?: now()->toDateString();

        if ($request->filled('cash_account_id')) {
            $selectedCashAccount = CashAccount::query()
                ->where('company_id', $companyId)
                ->whereIn('type', ['bank', 'mobile_money'])
                ->findOrFail($request->integer('cash_account_id'));

            $candidates = $this->reconciliationService
                ->candidatePaymentsQuery($companyId, $selectedCashAccount, $statementDate)
                ->get();
            $candidates = $this->reconciliationService->sortCandidatePayments($candidates);

            $bookBalance = $this->reconciliationService->bookBalance($companyId, $selectedCashAccount, $statementDate);
            $candidateSignedTotal = $this->reconciliationService->signedAmount($candidates);
            $candidateInsights = $this->reconciliationService->candidateInsights($candidates);
            $candidateIndicators = $candidates
                ->mapWithKeys(fn (Payment $payment): array => [
                    $payment->id => [
                        'is_documented_deposit' => $this->reconciliationService->paymentReadyForExternalReconciliation($payment),
                        'needs_proof_attention' => $this->reconciliationService->paymentNeedsDepositProofAttention($payment),
                        'has_reference' => filled(trim((string) $payment->reference)),
                        'has_attachment' => ((int) ($payment->attachments_count ?? 0) > 0),
                    ],
                ])
                ->all();
        }

        return view('treasury-reconciliations.create', [
            'cashAccounts' => $cashAccounts,
            'selectedCashAccount' => $selectedCashAccount,
            'statementDate' => $statementDate,
            'candidates' => $candidates,
            'bookBalance' => $bookBalance,
            'candidateSignedTotal' => $candidateSignedTotal,
            'candidateInsights' => $candidateInsights,
            'candidateIndicators' => $candidateIndicators,
        ]);
    }

    public function store(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $data = $request->validate([
            'cash_account_id' => ['required', Rule::exists('cash_accounts', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'statement_date' => ['required', 'date'],
            'statement_reference' => ['nullable', 'string', 'max:255'],
            'statement_balance' => ['required', 'numeric'],
            'payment_ids' => ['required', 'array', 'min:1'],
            'payment_ids.*' => ['integer', Rule::exists('payments', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'notes' => ['nullable', 'string'],
        ]);

        $cashAccount = CashAccount::query()
            ->where('company_id', $companyId)
            ->whereIn('type', ['bank', 'mobile_money'])
            ->findOrFail($data['cash_account_id']);

        $reconciliation = $this->reconciliationService->create(
            companyId: $companyId,
            branchId: $workspace->branchId(),
            cashAccount: $cashAccount,
            payload: $data,
            user: $request->user(),
        );

        $this->activityLogger->log('treasury.reconciliations.create', 'Creation rapprochement de tresorerie', $reconciliation, [
            'reconciliation_number' => $reconciliation->reconciliation_number,
            'statement_balance' => $reconciliation->statement_balance,
            'difference' => $reconciliation->difference,
            'payments_count' => $reconciliation->payments_count,
        ]);

        return redirect()->route('treasury-reconciliations.show', $reconciliation)
            ->with('success', 'Rapprochement enregistre avec succes.');
    }

    public function show(TreasuryReconciliation $treasuryReconciliation, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $treasuryReconciliation->company_id, 403);

        $treasuryReconciliation->load([
            'cashAccount',
            'branch',
            'creator',
            'items.payment.partner',
            'items.payment.allocations.allocatable',
        ]);

        return view('treasury-reconciliations.show', [
            'reconciliation' => $treasuryReconciliation,
        ]);
    }
}
