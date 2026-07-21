<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Approvals\Services\ApprovalFlowService;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Services\PricingService;
use App\Modules\Core\Notifications\Services\OutboundNotificationService;
use App\Modules\Partners\Models\Partner;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesQuote;
use App\Modules\Sales\Services\SalesInvoiceService;
use App\Modules\Sales\Services\SalesPortalLinkService;
use App\Modules\Sales\Services\SalesQuoteService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use App\Support\Exports\CsvExportService;
use App\Support\Pdf\PdfDocumentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesQuoteController extends Controller
{
    public function __construct(
        private readonly SalesQuoteService $salesQuoteService,
        private readonly SalesInvoiceService $salesInvoiceService,
        private readonly SalesPortalLinkService $salesPortalLinkService,
        private readonly PricingService $pricingService,
        private readonly ApprovalFlowService $approvalFlowService,
        private readonly OutboundNotificationService $outboundNotificationService,
        private readonly ActivityLogger $activityLogger,
        private readonly CsvExportService $csvExportService,
        private readonly PdfDocumentService $pdfDocumentService,
    ) {}

    public function index(Request $request, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $filters = $this->filters($request);

        return view('quotes.index', [
            'quotes' => $this->filteredQuery($companyId, $filters)
                ->latest('quote_date')
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
            'filters' => $filters,
            'branches' => Branch::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'summary' => $this->summary($companyId),
            'today' => now()->startOfDay(),
        ]);
    }

    public function export(Request $request, CurrentWorkspace $workspace): StreamedResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $rows = $this->filteredQuery($companyId, $this->filters($request))
            ->orderBy('quote_date')
            ->orderBy('id')
            ->get()
            ->map(fn (SalesQuote $quote) => [
                $quote->quote_number,
                $quote->quote_date?->format('d/m/Y'),
                $quote->valid_until?->format('d/m/Y'),
                $quote->customer?->name,
                $quote->branch?->name,
                $quote->status,
                number_format((float) $quote->total, 2, '.', ''),
            ]);

        return $this->csvExportService->download('devis.csv', [
            'Numero', 'Date', 'Validite', 'Client', 'Agence', 'Statut', 'Total',
        ], $rows);
    }

    public function create(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId || ! $workspace->branchId(), 403);
        $defaultRows = old('items', array_fill(0, 6, ['product_id' => '', 'description' => '', 'qty' => '', 'unit_price' => '']));

        return view('quotes.create', [
            'customers' => Partner::query()->customers()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(),
            'products' => app(\App\Modules\Catalog\Services\ProductOptionService::class)->initial($companyId, 'saleable', collect($defaultRows)->pluck('product_id')->all()),
            'defaultRows' => $defaultRows,
            'priceRules' => $this->pricingService->rulesPayloadForCompany($companyId),
            'branch' => $workspace->branch(),
        ]);
    }

    public function store(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        $payload = $request->validate([
            'customer_id' => ['required', Rule::exists('partners', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'quote_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:quote_date'],
            'notes' => ['nullable', 'string'],
        ]);

        $itemsInput = collect($request->input('items', []))
            ->map(fn ($item) => is_array($item) ? $item : [])
            ->filter(fn (array $item) => filled($item['product_id'] ?? null))
            ->values()
            ->all();

        Validator::make(
            ['items' => $itemsInput],
            [
                'items' => ['required', 'array', 'min:1'],
                'items.*.product_id' => ['required', Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('sale_ok', true)->where('sale_blocked', false)->where('is_active', true))],
                'items.*.description' => ['nullable', 'string', 'max:255'],
                'items.*.qty' => ['required', 'numeric', 'gt:0'],
                'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            ],
            [
                'items.required' => 'Ajoute au moins une ligne au devis.',
                'items.min' => 'Ajoute au moins une ligne au devis.',
            ]
        )->validate();

        $customer = Partner::query()->customers()->where('company_id', $companyId)->findOrFail($payload['customer_id']);
        $quote = $this->salesQuoteService->create(
            $companyId,
            $branchId,
            $customer,
            $payload,
            $this->salesQuoteService->normalizeItems($companyId, $itemsInput, $customer, $request->user()),
            $request->user(),
        );

        $this->activityLogger->log('quotes.create', 'Creation devis client', $quote, [
            'quote_number' => $quote->quote_number,
            'total' => $quote->total,
        ]);

        return redirect()->route('quotes.show', $quote)->with('success', 'Devis cree avec succes.');
    }

    public function show(SalesQuote $quote, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $quote->company_id, 403);

        $quote = $quote->load(['customer', 'branch', 'company', 'items.product', 'creator', 'convertedInvoice', 'convertedOrder', 'latestPortalAction']);

        return view('quotes.show', [
            'quote' => $quote,
            'portal' => $this->salesPortalLinkService->quotePortalData($quote),
        ]);
    }

    public function print(SalesQuote $quote, CurrentWorkspace $workspace): Response
    {
        abort_if($workspace->companyId() !== $quote->company_id, 403);

        return $this->pdfDocumentService->inline('quotes.print', [
            'quote' => $quote->load(['customer', 'branch', 'company', 'items.product', 'creator', 'convertedInvoice', 'convertedOrder', 'latestPortalAction']),
        ], 'devis-'.$quote->quote_number.'.pdf');
    }

    public function send(SalesQuote $quote, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $quote->company_id, 403);

        $quote = $this->salesQuoteService->updateStatus($quote, 'sent');
        $this->activityLogger->log('quotes.send', 'Envoi devis client', $quote, ['quote_number' => $quote->quote_number]);

        return redirect()->route('quotes.show', $quote)->with('success', 'Devis marque comme envoye.');
    }

    public function accept(SalesQuote $quote, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $quote->company_id, 403);

        $quote = $this->salesQuoteService->updateStatus($quote, 'accepted');
        $this->activityLogger->log('quotes.accept', 'Acceptation devis client', $quote, ['quote_number' => $quote->quote_number]);

        return redirect()->route('quotes.show', $quote)->with('success', 'Devis marque comme accepte.');
    }

    public function cancel(SalesQuote $quote, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $quote->company_id, 403);

        $quote = $this->salesQuoteService->updateStatus($quote, 'cancelled');
        $this->activityLogger->log('quotes.cancel', 'Annulation devis client', $quote, ['quote_number' => $quote->quote_number]);

        return redirect()->route('quotes.show', $quote)->with('success', 'Devis annule.');
    }

    public function convertToOrder(SalesQuote $quote, Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $quote->company_id, 403);

        $payload = $request->validate([
            'order_date' => ['required', 'date'],
            'requested_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string'],
        ]);

        $conversion = $this->salesQuoteService->convertToOrder($quote, $payload, $request->user());
        $quote = $conversion['quote'];
        $order = $conversion['order'];

        $this->activityLogger->log('quotes.convert_order', 'Conversion devis en commande', $quote, [
            'quote_number' => $quote->quote_number,
            'order_number' => $order->order_number,
        ]);

        return redirect()->route('orders.show', $order)->with('success', 'Devis converti en commande client confirmee.');
    }

    public function convert(SalesQuote $quote, Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $quote->company_id, 403);

        $payload = $request->validate([
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'notes' => ['nullable', 'string'],
        ]);

        $conversion = $this->salesQuoteService->convertToInvoice($quote, $payload, $request->user());
        /** @var SalesInvoice $invoice */
        $invoice = $conversion['invoice'];
        $quote = $conversion['quote'];

        $approval = $this->approvalFlowService->autoAdvance(
            $invoice,
            'sales',
            $request->user(),
            fn (SalesInvoice $pendingInvoice, $user) => $this->salesInvoiceService->approve($pendingInvoice, $user),
        );

        /** @var SalesInvoice $invoice */
        $invoice = $approval['document'];
        $this->outboundNotificationService->dispatchApprovalRequest($invoice, 'sales', $approval['next_step']);

        $this->activityLogger->log('quotes.convert', 'Conversion devis en facture', $quote, [
            'quote_number' => $quote->quote_number,
            'invoice_number' => $invoice->invoice_number,
            'invoice_status' => $invoice->status,
        ]);

        $message = $approval['is_fully_approved']
            ? 'Devis converti en facture avec succes.'
            : 'Devis converti en facture. La facture suit maintenant le workflow d approbation.';

        return redirect()->route('sales.show', $invoice)->with('success', $message);
    }

    private function filteredQuery(int $companyId, array $filters): Builder
    {
        return SalesQuote::query()
            ->with(['customer', 'branch', 'creator', 'convertedInvoice', 'convertedOrder'])
            ->where('company_id', $companyId)
            ->when($filters['date_from'], fn (Builder $query, string $dateFrom) => $query->whereDate('quote_date', '>=', $dateFrom))
            ->when($filters['date_to'], fn (Builder $query, string $dateTo) => $query->whereDate('quote_date', '<=', $dateTo))
            ->when($filters['branch_id'], fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['search'], function (Builder $query, string $search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $nested) use ($like) {
                    $nested->where('quote_number', 'like', $like)
                        ->orWhere('notes', 'like', $like)
                        ->orWhereHas('customer', function (Builder $customerQuery) use ($like) {
                            $customerQuery->where('name', 'like', $like)
                                ->orWhere('code', 'like', $like)
                                ->orWhere('phone', 'like', $like);
                        })
                        ->orWhereHas('branch', function (Builder $branchQuery) use ($like) {
                            $branchQuery->where('name', 'like', $like)
                                ->orWhere('code', 'like', $like);
                        });
                });
            });
    }

    private function filters(Request $request): array
    {
        $view = $request->string('view')->trim()->value() === 'kanban' ? 'kanban' : 'list';
        $status = $request->string('status')->trim()->value() ?: null;
        if (! in_array($status, ['draft', 'sent', 'accepted', 'cancelled', 'converted'], true)) {
            $status = null;
        }

        return [
            'view' => $view,
            'search' => $request->string('search')->trim()->value() ?: null,
            'date_from' => $request->string('date_from')->value() ?: null,
            'date_to' => $request->string('date_to')->value() ?: null,
            'branch_id' => $request->integer('branch_id') ?: null,
            'status' => $status,
        ];
    }

    private function summary(int $companyId): array
    {
        return [
            'draft' => (int) SalesQuote::query()->where('company_id', $companyId)->where('status', 'draft')->count(),
            'sent' => (int) SalesQuote::query()->where('company_id', $companyId)->where('status', 'sent')->count(),
            'accepted' => (int) SalesQuote::query()->where('company_id', $companyId)->where('status', 'accepted')->count(),
            'converted' => (int) SalesQuote::query()->where('company_id', $companyId)->where('status', 'converted')->count(),
        ];
    }
}
