<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\AccountingService;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Treasury\Models\Payment;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JournalEntryController extends Controller
{
    public function __construct(
        private readonly AccountingService $accountingService,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    public function index(Request $request, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $filters = $this->filters($request);

        $query = JournalEntry::query()
            ->with(['branch', 'creator', 'reversalOf', 'reversalEntry'])
            ->where('company_id', $companyId)
            ->when($filters['date_from'], fn (Builder $query, string $dateFrom) => $query->whereDate('entry_date', '>=', $dateFrom))
            ->when($filters['date_to'], fn (Builder $query, string $dateTo) => $query->whereDate('entry_date', '<=', $dateTo))
            ->when($filters['journal_code'], fn (Builder $query, string $journalCode) => $query->where('journal_code', $journalCode))
            ->when($filters['source_type'], fn (Builder $query, string $sourceType) => $query->where('source_type', $sourceType))
            ->when($filters['search'], function (Builder $query, string $search): void {
                $like = '%'.$search.'%';

                $query->where(function (Builder $nested) use ($like): void {
                    $nested->where('journal_number', 'like', $like)
                        ->orWhere('reference', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('reversal_reason', 'like', $like);
                });
            })
            ->latest('entry_date')
            ->latest('id');

        return view('accounting.journal-entries.index', [
            'entries' => $query->paginate(25)->withQueryString(),
            'filters' => $filters,
            'journalCodes' => JournalEntry::query()
                ->where('company_id', $companyId)
                ->select('journal_code')
                ->distinct()
                ->orderBy('journal_code')
                ->pluck('journal_code'),
            'sourceOptions' => $this->sourceOptions(),
        ]);
    }

    public function show(JournalEntry $journalEntry, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $journalEntry->company_id, 403);

        $entry = $journalEntry->load([
            'branch',
            'creator',
            'lines.account',
            'lines.partner',
            'source',
            'reversalOf',
            'reversalEntry.creator',
        ]);

        return view('accounting.journal-entries.show', [
            'entry' => $entry,
            'sourceContext' => $this->sourceContext($entry),
        ]);
    }

    public function reverse(Request $request, JournalEntry $journalEntry, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $journalEntry->company_id, 403);

        $payload = $request->validate([
            'reversal_date' => ['required', 'date'],
            'reversal_reason' => ['required', 'string', 'max:500'],
        ]);

        $reversal = $this->accountingService->reverseJournalEntry(
            $journalEntry,
            $request->user(),
            $payload['reversal_date'],
            $payload['reversal_reason'],
        );

        $this->activityLogger->log('accounting.journal_entries.reverse', 'Contrepassation ecriture comptable', $journalEntry, [
            'journal_number' => $journalEntry->journal_number,
            'reversal_journal_number' => $reversal->journal_number,
            'reversal_date' => $payload['reversal_date'],
            'reversal_reason' => $payload['reversal_reason'],
        ]);

        return redirect()
            ->route('accounting.journal-entries.show', $reversal)
            ->with('success', 'Ecriture comptable contrepassee avec succes.');
    }

    private function filters(Request $request): array
    {
        $journalCode = $request->string('journal_code')->trim()->value() ?: null;
        $sourceKey = $request->string('source_type')->trim()->value() ?: null;
        $sourceType = $this->sourceTypeMap()[$sourceKey] ?? null;

        return [
            'search' => $request->string('search')->trim()->value() ?: null,
            'date_from' => $request->string('date_from')->value() ?: null,
            'date_to' => $request->string('date_to')->value() ?: null,
            'journal_code' => $journalCode,
            'source_key' => $sourceKey,
            'source_type' => $sourceType,
        ];
    }

    private function sourceOptions(): array
    {
        return [
            'sales' => 'Ventes',
            'purchases' => 'Achats',
            'expenses' => 'Depenses',
            'payments' => 'Paiements',
            'reversals' => 'Contrepassations',
        ];
    }

    private function sourceTypeMap(): array
    {
        return [
            'sales' => SalesInvoice::class,
            'purchases' => PurchaseBill::class,
            'expenses' => Expense::class,
            'payments' => Payment::class,
            'reversals' => JournalEntry::class,
        ];
    }

    private function sourceContext(JournalEntry $entry): ?array
    {
        $source = $entry->source;

        return match (true) {
            $source instanceof SalesInvoice => [
                'label' => 'Facture client',
                'number' => $source->invoice_number,
                'url' => route('sales.show', $source),
            ],
            $source instanceof PurchaseBill => [
                'label' => 'Facture fournisseur',
                'number' => $source->bill_number,
                'url' => route('purchases.show', $source),
            ],
            $source instanceof Expense => [
                'label' => 'Depense',
                'number' => $source->expense_number,
                'url' => route('expenses.show', $source),
            ],
            $source instanceof Payment => $this->paymentSourceContext($source),
            $source instanceof JournalEntry => [
                'label' => 'Ecriture annulee',
                'number' => $source->journal_number,
                'url' => route('accounting.journal-entries.show', $source),
                'hint' => 'Consulter l ecriture d origine et son detail.',
            ],
            default => null,
        };
    }

    private function paymentSourceContext(Payment $payment): array
    {
        $payment->loadMissing('allocations.allocatable');

        $linkedDocument = optional($payment->allocations->first())->allocatable;

        if ($linkedDocument instanceof SalesInvoice) {
            return [
                'label' => 'Paiement client',
                'number' => $payment->payment_number,
                'url' => route('payments.show', $payment),
                'hint' => 'Lie a la facture '.$linkedDocument->invoice_number,
            ];
        }

        if ($linkedDocument instanceof PurchaseBill) {
            return [
                'label' => 'Reglement fournisseur',
                'number' => $payment->payment_number,
                'url' => route('payments.show', $payment),
                'hint' => 'Lie a la facture '.$linkedDocument->bill_number,
            ];
        }

        return [
            'label' => 'Paiement',
            'number' => $payment->payment_number,
            'url' => route('payments.show', $payment),
            'hint' => 'Ouvrir le detail du paiement et ses allocations',
        ];
    }
}
