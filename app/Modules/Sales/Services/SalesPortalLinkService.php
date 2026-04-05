<?php

namespace App\Modules\Sales\Services;

use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesQuote;
use App\Modules\Treasury\Services\PaymentGatewayService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class SalesPortalLinkService
{
    private const DEFAULT_VALIDITY_DAYS = 30;

    public function __construct(
        private readonly PaymentGatewayService $paymentGatewayService,
    ) {
    }

    public function quotePortalData(SalesQuote $quote): array
    {
        $expiresAt = $this->quoteExpiryAt($quote);
        $viewUrl = URL::temporarySignedRoute('portal.quotes.show', $expiresAt, ['quote' => $quote->id]);
        $shareMessage = 'Bonjour'.($quote->customer?->name ? ' '.$quote->customer->name : '').", voici votre devis {$quote->quote_number} de ".$this->money((float) $quote->total).". Consultez-le ici : {$viewUrl}";

        return [
            'view_url' => $viewUrl,
            'accept_url' => URL::temporarySignedRoute('portal.quotes.accept', $expiresAt, ['quote' => $quote->id]),
            'expires_at' => $expiresAt,
            'can_accept' => in_array($quote->status, ['draft', 'sent'], true),
            'share_message' => $shareMessage,
            'whatsapp_url' => $this->whatsAppUrl(
                phone: $quote->customer?->phone,
                message: $shareMessage,
            ),
        ];
    }

    public function orderPortalData(SalesOrder $order): array
    {
        $expiresAt = $this->orderExpiryAt($order);
        $viewUrl = URL::temporarySignedRoute('portal.orders.show', $expiresAt, ['order' => $order->id]);
        $shareMessage = 'Bonjour'.($order->customer?->name ? ' '.$order->customer->name : '').", voici votre commande {$order->order_number} de ".$this->money((float) $order->total).". Consultez-la ici : {$viewUrl}";

        return [
            'view_url' => $viewUrl,
            'confirm_url' => URL::temporarySignedRoute('portal.orders.confirm', $expiresAt, ['order' => $order->id]),
            'expires_at' => $expiresAt,
            'can_confirm' => $order->status === 'draft',
            'share_message' => $shareMessage,
            'whatsapp_url' => $this->whatsAppUrl(
                phone: $order->customer?->phone,
                message: $shareMessage,
            ),
        ];
    }

    public function invoicePaymentPortalData(SalesInvoice $invoice): array
    {
        $expiresAt = $this->invoiceExpiryAt($invoice);
        $viewUrl = URL::temporarySignedRoute('portal.invoices.show', $expiresAt, ['invoice' => $invoice->id]);
        $paymentReferenceHint = $invoice->invoice_number;
        $paymentChannels = collect($this->paymentGatewayService->publicChannelsForCompany($invoice->company_id))
            ->map(function (array $channel) use ($invoice, $paymentReferenceHint) {
                $paymentMessage = $this->paymentRequestMessage($invoice, $channel, $paymentReferenceHint);
                $contactPhone = $this->normalizePhoneForDial($channel['collection_number'] ?? null);

                return [
                    ...$channel,
                    'payment_message' => $paymentMessage,
                    'copy_label' => $channel['requires_reference'] ? 'Copier le message de paiement' : 'Copier les instructions',
                    'target_copy_text' => $channel['collection_number'] ?: $channel['target'],
                    'target_copy_label' => $contactPhone ? 'Copier le numero' : 'Copier les coordonnees',
                    'actions' => $this->channelActions($contactPhone, $paymentMessage),
                    'prefill_reference' => $paymentReferenceHint,
                    'prefill_note' => trim(implode(' · ', array_filter([
                        'Canal terrain choisi : '.$channel['label'],
                        $channel['target'] ?: null,
                        $channel['instructions'] ?: null,
                    ]))),
                ];
            })
            ->values()
            ->all();
        $shareMessage = 'Bonjour'.($invoice->customer?->name ? ' '.$invoice->customer->name : '').", voici votre lien de reglement pour la facture {$invoice->invoice_number} d un montant restant de ".$this->money((float) $invoice->balance_due)." : {$viewUrl}";

        return [
            'view_url' => $viewUrl,
            'notify_url' => URL::temporarySignedRoute('portal.invoices.notify', $expiresAt, ['invoice' => $invoice->id]),
            'expires_at' => $expiresAt,
            'can_notify_payment' => $invoice->status === 'validated' && in_array($invoice->payment_status, ['unpaid', 'partial'], true),
            'payment_channels' => $paymentChannels,
            'payment_reference_hint' => $paymentReferenceHint,
            'share_message' => $shareMessage,
            'whatsapp_url' => $this->whatsAppUrl(
                phone: $invoice->customer?->phone,
                message: $shareMessage,
            ),
        ];
    }

    public function quoteExpiryAt(SalesQuote $quote): Carbon
    {
        return $this->normalizeExpiry($quote->valid_until);
    }

    public function orderExpiryAt(SalesOrder $order): Carbon
    {
        return $this->normalizeExpiry($order->commitment_date ?? $order->requested_delivery_date);
    }

    public function invoiceExpiryAt(SalesInvoice $invoice): Carbon
    {
        return $this->normalizeExpiry($invoice->due_date ?? $invoice->invoice_date);
    }

    private function normalizeExpiry(Carbon|string|null $candidate): Carbon
    {
        $expiry = $candidate ? Carbon::parse($candidate)->endOfDay() : now()->addDays(self::DEFAULT_VALIDITY_DAYS)->endOfDay();

        if ($expiry->lte(now())) {
            $expiry = now()->addDays(self::DEFAULT_VALIDITY_DAYS)->endOfDay();
        }

        return $expiry;
    }

    private function whatsAppUrl(?string $phone, string $message): ?string
    {
        $normalizedPhone = preg_replace('/\D+/', '', (string) $phone);

        if ($normalizedPhone === '') {
            return null;
        }

        if (str_starts_with($normalizedPhone, '00')) {
            $normalizedPhone = substr($normalizedPhone, 2);
        }

        return 'https://wa.me/'.$normalizedPhone.'?text='.rawurlencode($message);
    }

    private function paymentRequestMessage(SalesInvoice $invoice, array $channel, string $referenceHint): string
    {
        $segments = [
            'Reglement facture '.$invoice->invoice_number,
            'Montant restant '.$this->money((float) $invoice->balance_due),
            'Canal '.$channel['label'],
        ];

        if (! empty($channel['target'])) {
            $segments[] = 'Coordonnees '.$channel['target'];
        }

        if (! empty($channel['instructions'])) {
            $segments[] = $channel['instructions'];
        }

        if (! empty($channel['requires_reference'])) {
            $segments[] = 'Reference a rappeler '.$referenceHint;
        }

        return implode(' · ', $segments);
    }

    private function channelActions(?string $contactPhone, string $paymentMessage): array
    {
        if (! $contactPhone) {
            return [];
        }

        return array_values(array_filter([
            [
                'label' => 'Appeler le numero',
                'url' => 'tel:'.$contactPhone,
                'style' => 'button-secondary',
            ],
            [
                'label' => 'SMS pre-rempli',
                'url' => $this->smsUrl($contactPhone, $paymentMessage),
                'style' => 'button-secondary',
            ],
            [
                'label' => 'WhatsApp collecte',
                'url' => $this->whatsAppUrl($contactPhone, $paymentMessage),
                'style' => 'button-whatsapp',
            ],
        ], fn (?array $action) => filled($action['url'] ?? null)));
    }

    private function smsUrl(string $phone, string $message): string
    {
        return 'sms:'.$phone.'?body='.rawurlencode($message);
    }

    private function normalizePhoneForDial(?string $phone): ?string
    {
        $raw = trim((string) $phone);

        if ($raw === '') {
            return null;
        }

        $normalized = preg_replace('/(?!^\+)\D+/', '', $raw);

        if ($normalized === '' || preg_match('/^\+?\d{6,}$/', $normalized) !== 1) {
            return null;
        }

        return $normalized;
    }

    private function money(float $amount): string
    {
        return number_format($amount, 0, ',', ' ').' XOF';
    }
}
