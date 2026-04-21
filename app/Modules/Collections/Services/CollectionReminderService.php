<?php

namespace App\Modules\Collections\Services;

use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Treasury\Services\PaymentGatewayService;
use Illuminate\Support\Carbon;

class CollectionReminderService
{
    public function __construct(
        private readonly PaymentGatewayService $paymentGatewayService,
    ) {
    }

    public function forInvoice(SalesInvoice $invoice): array
    {
        return $this->buildReminder([
            'company_id' => (int) $invoice->company_id,
            'invoice_number' => (string) $invoice->invoice_number,
            'customer_name' => (string) ($invoice->customer?->name ?? ''),
            'customer_phone' => (string) ($invoice->customer?->phone ?? ''),
            'balance_due' => (float) $invoice->balance_due,
            'due_date' => $invoice->due_date,
        ]);
    }

    public function forPortfolio(iterable $items): array
    {
        $reminders = [];

        foreach ($items as $invoice) {
            if (! $invoice instanceof SalesInvoice) {
                continue;
            }

            $reminders[$invoice->id] = $this->buildReminder([
                'company_id' => (int) $invoice->company_id,
                'invoice_number' => (string) $invoice->invoice_number,
                'customer_name' => (string) ($invoice->customer_name ?? $invoice->customer?->name ?? ''),
                'customer_phone' => (string) ($invoice->customer_phone ?? $invoice->customer?->phone ?? ''),
                'balance_due' => (float) $invoice->balance_due,
                'due_date' => $invoice->due_date,
            ]);
        }

        return $reminders;
    }

    private function buildReminder(array $context): array
    {
        $companyId = (int) ($context['company_id'] ?? 0);
        $invoiceNumber = trim((string) ($context['invoice_number'] ?? ''));
        $customerName = trim((string) ($context['customer_name'] ?? ''));
        $customerPhone = trim((string) ($context['customer_phone'] ?? ''));
        $balanceDue = (float) ($context['balance_due'] ?? 0);
        $dueDate = $this->parseDate($context['due_date'] ?? null);
        $daysOverdue = $dueDate && $dueDate->isPast() ? $dueDate->diffInDays(now()) : 0;
        $normalizedPhone = $this->normalizePhoneForWhatsApp($customerPhone);
        $paymentChannels = collect($this->paymentGatewayService->publicChannelsForCompany($companyId))
            ->map(function (array $channel) use ($invoiceNumber) {
                $parts = array_filter([
                    $channel['label'] ?? null,
                    $channel['target'] ?? null,
                ]);

                if (! empty($channel['requires_reference']) && $invoiceNumber !== '') {
                    $parts[] = 'Ref '.$invoiceNumber;
                }

                return [
                    ...$channel,
                    'summary' => implode(' · ', $parts),
                ];
            })
            ->values()
            ->all();
        $message = $this->buildMessage(
            customerName: $customerName,
            invoiceNumber: $invoiceNumber,
            balanceDue: $balanceDue,
            dueDate: $dueDate,
            daysOverdue: $daysOverdue,
            paymentChannels: $paymentChannels,
        );

        return [
            'customer_phone' => $customerPhone !== '' ? $customerPhone : null,
            'normalized_phone' => $normalizedPhone,
            'message' => $message,
            'whatsapp_url' => $normalizedPhone ? 'https://wa.me/'.$normalizedPhone.'?text='.rawurlencode($message) : null,
            'payment_channels' => $paymentChannels,
            'payment_reference_hint' => $invoiceNumber !== '' ? $invoiceNumber : null,
            'days_overdue' => $daysOverdue,
        ];
    }

    private function buildMessage(
        string $customerName,
        string $invoiceNumber,
        float $balanceDue,
        ?Carbon $dueDate,
        int $daysOverdue,
        array $paymentChannels,
    ): string {
        $segments = [
            'Bonjour'.($customerName !== '' ? ' '.$customerName : ''),
            'rappel amical : la facture '.($invoiceNumber !== '' ? $invoiceNumber : 'client').' reste ouverte pour '.$this->money($balanceDue).'.',
        ];

        if ($dueDate) {
            $segments[] = $daysOverdue > 0
                ? 'Elle est echee depuis '.$daysOverdue.' jour(s).'
                : 'Son echeance est fixee au '.$dueDate->format('d/m/Y').'.';
        }

        if ($paymentChannels !== []) {
            $segments[] = 'Vous pouvez regler via '.collect($paymentChannels)
                ->map(fn (array $channel) => $channel['summary'] ?: ($channel['label'] ?? 'Canal terrain'))
                ->implode(' ; ').'.';
        }

        $segments[] = 'Merci de nous partager la reference de transaction apres paiement.';

        return implode(' ', array_filter($segments));
    }

    private function normalizePhoneForWhatsApp(?string $phone): ?string
    {
        $normalized = preg_replace('/\D+/', '', (string) $phone);

        if ($normalized === '') {
            return null;
        }

        if (str_starts_with($normalized, '00')) {
            $normalized = substr($normalized, 2);
        }

        return preg_match('/^\d{8,15}$/', $normalized) === 1 ? $normalized : null;
    }

    private function parseDate(Carbon|string|null $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        return $value instanceof Carbon ? $value : Carbon::parse($value);
    }

    private function money(float $amount): string
    {
        return number_format($amount, 0, ',', ' ').' XOF';
    }
}
