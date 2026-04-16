<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Sales\Models\SalesCreditNote;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Services\SalesCreditNoteService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use App\Support\Pdf\PdfDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SalesCreditNoteController extends Controller
{
    public function __construct(
        private readonly SalesCreditNoteService $salesCreditNoteService,
        private readonly ActivityLogger $activityLogger,
        private readonly PdfDocumentService $pdfDocumentService,
    ) {
    }

    public function index(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        return view('credit-notes.index', [
            'creditNotes' => SalesCreditNote::query()->with(['invoice', 'customer', 'branch', 'creator'])->where('company_id', $companyId)->latest('credit_note_date')->latest('id')->paginate(15),
        ]);
    }

    public function create(SalesInvoice $sale, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $sale->company_id, 403);

        return view('credit-notes.create', [
            'invoice' => $sale->load(['customer', 'branch', 'warehouse', 'items.product', 'creditNotes.items']),
            'creditableLines' => $this->salesCreditNoteService->creditableLines($sale),
        ]);
    }

    public function store(SalesInvoice $sale, Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $sale->company_id, 403);

        $payload = $request->validate([
            'credit_note_date' => ['required', 'date'],
            'restock_items' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sales_invoice_item_id' => ['required', Rule::exists('sales_invoice_items', 'id')],
            'items.*.qty' => ['nullable', 'numeric', 'min:0'],
        ]);

        $creditNote = $this->salesCreditNoteService->create(
            invoice: $sale,
            payload: [
                'credit_note_date' => $payload['credit_note_date'],
                'restock_items' => $request->boolean('restock_items'),
                'notes' => $payload['notes'] ?? null,
            ],
            rows: $payload['items'],
            user: $request->user(),
        );

        $this->activityLogger->log('credit_notes.create', 'Creation avoir client', $creditNote, [
            'credit_note_number' => $creditNote->credit_note_number,
            'invoice_number' => $creditNote->invoice?->invoice_number,
            'restock_items' => $creditNote->restock_items,
        ]);

        return redirect()->route('credit-notes.show', $creditNote)->with('success', 'Avoir client cree avec succes.');
    }

    public function show(SalesCreditNote $creditNote, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $creditNote->company_id, 403);

        return view('credit-notes.show', [
            'creditNote' => $creditNote->load(['invoice', 'customer', 'branch', 'warehouse', 'creator', 'items.product', 'items.salesInvoiceItem']),
            'journalEntries' => JournalEntry::query()->where('company_id', $creditNote->company_id)->where('source_type', SalesCreditNote::class)->where('source_id', $creditNote->id)->orderBy('entry_date')->get(),
            'stockMovements' => StockMovement::query()->with(['product', 'warehouse', 'creator'])->where('company_id', $creditNote->company_id)->where('reference_type', SalesCreditNote::class)->where('reference_id', $creditNote->id)->orderBy('movement_date')->get(),
        ]);
    }

    public function print(SalesCreditNote $creditNote, CurrentWorkspace $workspace): \Symfony\Component\HttpFoundation\Response
    {
        abort_if($workspace->companyId() !== $creditNote->company_id, 403);

        return $this->pdfDocumentService->inline('credit-notes.print', [
            'creditNote' => $creditNote->load(['invoice', 'customer', 'branch', 'warehouse', 'company', 'creator', 'items.product']),
        ], 'avoir-client-'.$creditNote->credit_note_number.'.pdf');
    }
}
