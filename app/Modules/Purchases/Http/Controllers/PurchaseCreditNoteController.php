<?php

namespace App\Modules\Purchases\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Purchases\Models\PurchaseCreditNote;
use App\Modules\Purchases\Services\PurchaseCreditNoteService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use App\Support\Pdf\PdfDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PurchaseCreditNoteController extends Controller
{
    public function __construct(
        private readonly PurchaseCreditNoteService $purchaseCreditNoteService,
        private readonly ActivityLogger $activityLogger,
        private readonly PdfDocumentService $pdfDocumentService,
    ) {}

    public function index(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        return view('purchase-credit-notes.index', [
            'creditNotes' => PurchaseCreditNote::query()
                ->with(['bill', 'supplier', 'branch', 'creator'])
                ->where('company_id', $companyId)
                ->latest('credit_note_date')
                ->latest('id')
                ->paginate(15),
        ]);
    }

    public function create(PurchaseBill $purchase, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $purchase->company_id, 403);

        return view('purchase-credit-notes.create', [
            'bill' => $purchase->load(['supplier', 'branch', 'warehouse', 'items.product', 'creditNotes.items']),
            'creditableLines' => $this->purchaseCreditNoteService->creditableLines($purchase),
        ]);
    }

    public function store(PurchaseBill $purchase, Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $purchase->company_id, 403);

        $payload = $request->validate([
            'credit_note_date' => ['required', 'date'],
            'destock_items' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_bill_item_id' => ['required', Rule::exists('purchase_bill_items', 'id')],
            'items.*.qty' => ['nullable', 'numeric', 'min:0'],
        ]);

        $creditNote = $this->purchaseCreditNoteService->create(
            bill: $purchase,
            payload: [
                'credit_note_date' => $payload['credit_note_date'],
                'destock_items' => $request->boolean('destock_items'),
                'notes' => $payload['notes'] ?? null,
            ],
            rows: $payload['items'],
            user: $request->user(),
        );

        $this->activityLogger->log('purchase_credit_notes.create', 'Creation avoir fournisseur', $creditNote, [
            'credit_note_number' => $creditNote->credit_note_number,
            'bill_number' => $creditNote->bill?->bill_number,
            'destock_items' => $creditNote->destock_items,
        ]);

        return redirect()->route('purchase-credit-notes.show', $creditNote)->with('success', 'Avoir fournisseur cree avec succes.');
    }

    public function show(PurchaseCreditNote $creditNote, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $creditNote->company_id, 403);

        return view('purchase-credit-notes.show', [
            'creditNote' => $creditNote->load(['bill', 'supplier', 'branch', 'warehouse', 'creator', 'items.product', 'items.purchaseBillItem']),
            'journalEntries' => JournalEntry::query()
                ->where('company_id', $creditNote->company_id)
                ->where('source_type', PurchaseCreditNote::class)
                ->where('source_id', $creditNote->id)
                ->orderBy('entry_date')
                ->get(),
            'stockMovements' => StockMovement::query()
                ->with(['product', 'warehouse', 'creator'])
                ->where('company_id', $creditNote->company_id)
                ->where('reference_type', PurchaseCreditNote::class)
                ->where('reference_id', $creditNote->id)
                ->orderBy('movement_date')
                ->get(),
        ]);
    }

    public function print(PurchaseCreditNote $creditNote, CurrentWorkspace $workspace): Response
    {
        abort_if($workspace->companyId() !== $creditNote->company_id, 403);

        return $this->pdfDocumentService->inline('purchase-credit-notes.print', [
            'creditNote' => $creditNote->load(['bill', 'supplier', 'branch', 'warehouse', 'company', 'creator', 'items.product']),
        ], 'avoir-fournisseur-'.$creditNote->credit_note_number.'.pdf');
    }
}
