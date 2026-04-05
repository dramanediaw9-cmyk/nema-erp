<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Audit\Models\ActivityLog;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesPortalAction;
use App\Modules\Sales\Models\SalesQuote;
use App\Modules\Sales\Services\SalesOrderService;
use App\Modules\Sales\Services\SalesPortalLinkService;
use App\Modules\Sales\Services\SalesQuoteService;
use App\Support\PaymentMethodCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SalesPortalController extends Controller
{
    public function __construct(
        private readonly SalesQuoteService $salesQuoteService,
        private readonly SalesOrderService $salesOrderService,
        private readonly SalesPortalLinkService $salesPortalLinkService,
    ) {
    }

    public function showQuote(SalesQuote $quote): View
    {
        $quote->load(['customer', 'branch', 'company', 'items.product', 'creator', 'convertedInvoice', 'convertedOrder', 'latestPortalAction']);

        return view('portal.sales.quote', [
            'quote' => $quote,
            'portal' => $this->salesPortalLinkService->quotePortalData($quote),
            'depositMethods' => $this->depositMethodOptions(),
        ]);
    }

    public function acceptQuote(Request $request, SalesQuote $quote): RedirectResponse
    {
        if ($quote->status === 'accepted') {
            return back()->with('success', 'Ce devis a deja ete confirme.');
        }

        if (in_array($quote->status, ['cancelled', 'converted'], true)) {
            return back()->with('warning', 'Ce devis ne peut plus etre confirme depuis le portail.');
        }

        $payload = $this->validatedPortalPayload($request, (float) $quote->total);
        $quote = $this->salesQuoteService->updateStatus($quote, 'accepted');
        $portalAction = $this->recordPortalAction($quote, 'quote_acceptance', $payload, $request);

        $this->logPortalActivity(
            action: 'quotes.portal_accept',
            description: 'Acceptation devis via portail client',
            subject: $quote,
            companyId: $quote->company_id,
            branchId: $quote->branch_id,
            properties: [
                'quote_number' => $quote->quote_number,
                'channel' => 'portal_client',
                'portal_action_id' => $portalAction->id,
                'signer_name' => $portalAction->signer_name,
                'deposit_amount' => $portalAction->deposit_amount,
                'deposit_method' => $portalAction->deposit_method,
                'has_signature_image' => filled($portalAction->signature_image_data_url),
            ],
            request: $request,
        );

        return back()->with('success', 'Le devis a ete confirme et signe avec succes.');
    }

    public function showOrder(SalesOrder $order): View
    {
        $order->load(['customer', 'branch', 'company', 'items.product', 'creator', 'convertedInvoice', 'originQuote', 'deliveryNotes', 'latestPortalAction']);

        return view('portal.sales.order', [
            'order' => $order,
            'portal' => $this->salesPortalLinkService->orderPortalData($order),
            'depositMethods' => $this->depositMethodOptions(),
        ]);
    }

    public function confirmOrder(Request $request, SalesOrder $order): RedirectResponse
    {
        if ($order->status === 'confirmed') {
            return back()->with('success', 'Cette commande a deja ete confirmee.');
        }

        if (in_array($order->status, ['partial_delivered', 'delivered', 'converted', 'cancelled'], true)) {
            return back()->with('warning', 'Cette commande ne peut plus etre confirmee depuis le portail.');
        }

        $payload = $this->validatedPortalPayload($request, (float) $order->total);
        $order = $this->salesOrderService->updateStatus($order, 'confirmed');
        $portalAction = $this->recordPortalAction($order, 'order_confirmation', $payload, $request);

        $this->logPortalActivity(
            action: 'orders.portal_confirm',
            description: 'Confirmation commande via portail client',
            subject: $order,
            companyId: $order->company_id,
            branchId: $order->branch_id,
            properties: [
                'order_number' => $order->order_number,
                'channel' => 'portal_client',
                'portal_action_id' => $portalAction->id,
                'signer_name' => $portalAction->signer_name,
                'deposit_amount' => $portalAction->deposit_amount,
                'deposit_method' => $portalAction->deposit_method,
                'has_signature_image' => filled($portalAction->signature_image_data_url),
            ],
            request: $request,
        );

        return back()->with('success', 'La commande a ete confirmee et signee avec succes.');
    }

    public function showInvoicePayment(SalesInvoice $invoice): View
    {
        $invoice->load(['customer', 'branch', 'company', 'items.product', 'creator', 'approver', 'latestPortalAction']);
        $portal = $this->salesPortalLinkService->invoicePaymentPortalData($invoice);
        $depositMethods = collect($portal['payment_channels'] ?? [])
            ->pluck('label', 'method')
            ->all();

        return view('portal.sales.invoice-payment', [
            'invoice' => $invoice,
            'portal' => $portal,
            'depositMethods' => $depositMethods !== [] ? $depositMethods : $this->depositMethodOptions(),
        ]);
    }

    public function notifyInvoicePayment(Request $request, SalesInvoice $invoice): RedirectResponse
    {
        if ($invoice->status !== 'validated') {
            return back()->with('warning', 'La facture doit etre completement approuvee avant toute annonce de reglement.');
        }

        if ($invoice->payment_status === 'paid') {
            return back()->with('success', 'Cette facture est deja totalement reglee.');
        }

        $payload = $this->validatedPaymentNoticePayload($request, (float) $invoice->balance_due);
        $portalAction = $this->recordPortalAction($invoice, 'invoice_payment_notice', $payload, $request);

        $this->logPortalActivity(
            action: 'sales.portal_payment_notice',
            description: 'Avis de reglement client via portail',
            subject: $invoice,
            companyId: $invoice->company_id,
            branchId: $invoice->branch_id,
            properties: [
                'invoice_number' => $invoice->invoice_number,
                'channel' => 'portal_client',
                'portal_action_id' => $portalAction->id,
                'signer_name' => $portalAction->signer_name,
                'amount' => $portalAction->deposit_amount,
                'method' => $portalAction->deposit_method,
                'reference' => $portalAction->deposit_reference,
                'has_signature_image' => filled($portalAction->signature_image_data_url),
            ],
            request: $request,
        );

        return back()->with('success', 'L avis de reglement a ete transmis a l equipe et sera rapproche dans l ERP.');
    }

    private function validatedPortalPayload(Request $request, float $documentTotal): array
    {
        $data = $request->validate([
            'signer_name' => ['required', 'string', 'max:120'],
            'signer_phone' => ['nullable', 'string', 'max:60'],
            'signer_title' => ['nullable', 'string', 'max:120'],
            'signer_company' => ['nullable', 'string', 'max:120'],
            'signer_note' => ['nullable', 'string'],
            'signature_data_url' => ['nullable', 'string', 'max:200000', 'starts_with:data:image/png;base64,'],
            'accepted_terms' => ['accepted'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'deposit_method' => ['nullable', Rule::in(array_keys($this->depositMethodOptions()))],
            'deposit_reference' => ['nullable', 'string', 'max:120'],
            'deposit_note' => ['nullable', 'string'],
            'deposit_expected_at' => ['nullable', 'date'],
        ], [
            'accepted_terms.accepted' => 'La confirmation client doit etre validee avant signature.',
            'signature_data_url.starts_with' => 'La signature graphique doit etre enregistree au format image attendu.',
        ]);

        return $this->normalizeDepositPayload($data, $documentTotal, false);
    }

    private function validatedPaymentNoticePayload(Request $request, float $balanceDue): array
    {
        $data = $request->validate([
            'signer_name' => ['required', 'string', 'max:120'],
            'signer_phone' => ['nullable', 'string', 'max:60'],
            'signer_title' => ['nullable', 'string', 'max:120'],
            'signer_company' => ['nullable', 'string', 'max:120'],
            'signer_note' => ['nullable', 'string'],
            'signature_data_url' => ['nullable', 'string', 'max:200000', 'starts_with:data:image/png;base64,'],
            'accepted_terms' => ['accepted'],
            'deposit_amount' => ['required', 'numeric', 'gt:0'],
            'deposit_method' => ['required', Rule::in(array_keys($this->depositMethodOptions()))],
            'deposit_reference' => ['nullable', 'string', 'max:120'],
            'deposit_note' => ['nullable', 'string'],
            'deposit_expected_at' => ['nullable', 'date'],
        ], [
            'accepted_terms.accepted' => 'Valide la declaration de reglement avant envoi.',
            'deposit_amount.required' => 'Renseigne le montant annonce pour ce reglement.',
            'deposit_method.required' => 'Choisis le moyen de paiement annonce.',
            'signature_data_url.starts_with' => 'La signature graphique doit etre enregistree au format image attendu.',
        ]);

        return $this->normalizeDepositPayload($data, $balanceDue, true);
    }

    private function normalizeDepositPayload(array $data, float $maxAmount, bool $requiredAmount): array
    {
        $depositAmount = round((float) ($data['deposit_amount'] ?? 0), 2);
        $hasDepositMeta = filled($data['deposit_method'] ?? null)
            || filled($data['deposit_reference'] ?? null)
            || filled($data['deposit_note'] ?? null)
            || filled($data['deposit_expected_at'] ?? null);

        if ($depositAmount > round($maxAmount, 2)) {
            throw ValidationException::withMessages([
                'deposit_amount' => 'Le montant annonce ne peut pas depasser le solde autorise sur ce document.',
            ]);
        }

        if ($depositAmount > 0 && blank($data['deposit_method'] ?? null)) {
            throw ValidationException::withMessages([
                'deposit_method' => 'Choisis le moyen de paiement annonce.',
            ]);
        }

        if ($hasDepositMeta && $depositAmount <= 0) {
            throw ValidationException::withMessages([
                'deposit_amount' => 'Renseigne un montant avant de completer le reste des informations de paiement.',
            ]);
        }

        if (! $requiredAmount && $depositAmount <= 0) {
            $data['deposit_amount'] = null;
            $data['deposit_method'] = null;
            $data['deposit_reference'] = null;
            $data['deposit_note'] = null;
            $data['deposit_expected_at'] = null;
        } else {
            $data['deposit_amount'] = $depositAmount;
        }

        $data['signer_name'] = trim((string) $data['signer_name']);
        $data['signer_phone'] = trim((string) ($data['signer_phone'] ?? '')) ?: null;
        $data['signer_title'] = trim((string) ($data['signer_title'] ?? '')) ?: null;
        $data['signer_company'] = trim((string) ($data['signer_company'] ?? '')) ?: null;
        $data['signer_note'] = trim((string) ($data['signer_note'] ?? '')) ?: null;
        $data['signature_data_url'] = filled($data['signature_data_url'] ?? null) ? (string) $data['signature_data_url'] : null;

        return $data;
    }

    private function recordPortalAction(SalesQuote|SalesOrder|SalesInvoice $subject, string $actionType, array $payload, Request $request): SalesPortalAction
    {
        $signedAt = now();
        $signatureFingerprint = $payload['signature_data_url'] ? hash('sha256', $payload['signature_data_url']) : '';

        return SalesPortalAction::query()->create([
            'company_id' => $subject->company_id,
            'branch_id' => $subject->branch_id,
            'actionable_type' => $subject->getMorphClass(),
            'actionable_id' => $subject->getKey(),
            'action_type' => $actionType,
            'signer_name' => $payload['signer_name'],
            'signer_phone' => $payload['signer_phone'],
            'signer_title' => $payload['signer_title'],
            'signer_company' => $payload['signer_company'],
            'signer_note' => $payload['signer_note'],
            'accepted_terms' => true,
            'signature_hash' => hash('sha256', implode('|', [
                $actionType,
                $subject->getMorphClass(),
                $subject->getKey(),
                Str::upper($payload['signer_name']),
                $payload['signer_phone'] ?? '',
                $signatureFingerprint,
                $signedAt->toIso8601String(),
                (string) $request->ip(),
                substr((string) $request->userAgent(), 0, 180),
            ])),
            'signature_image_data_url' => $payload['signature_data_url'] ?? null,
            'signed_at' => $signedAt,
            'deposit_amount' => $payload['deposit_amount'],
            'deposit_method' => $payload['deposit_method'],
            'deposit_reference' => $payload['deposit_reference'] ?? null,
            'deposit_note' => $payload['deposit_note'] ?? null,
            'deposit_expected_at' => $payload['deposit_expected_at'] ?? null,
            'properties' => [
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'has_signature_image' => filled($payload['signature_data_url'] ?? null),
            ],
        ]);
    }

    private function depositMethodOptions(): array
    {
        return collect(PaymentMethodCatalog::options())
            ->only(['bank_transfer', 'wave', 'orange_money', 'moov_money', 'cash', 'cheque', 'other'])
            ->all();
    }

    private function logPortalActivity(string $action, string $description, SalesQuote|SalesOrder|SalesInvoice $subject, int $companyId, int $branchId, array $properties, Request $request): void
    {
        ActivityLog::query()->create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'user_id' => null,
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'properties' => $properties,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);
    }
}

