<?php

namespace App\Modules\Treasury\Services;

use App\Modules\Core\Company\Models\Setting;
use App\Support\PaymentMethodCatalog;

class PaymentGatewayService
{
    public function configurationForCompany(int $companyId): array
    {
        $setting = Setting::query()
            ->where('company_id', $companyId)
            ->where('key', 'payment_gateways')
            ->first();

        $stored = is_array($setting?->value) ? $setting->value : [];
        $defaults = $this->defaults();

        return collect($defaults)
            ->map(function (array $default, string $method) use ($stored, $companyId) {
                $channel = is_array($stored[$method] ?? null) ? $stored[$method] : [];

                return [
                    'label' => trim((string) ($channel['label'] ?? $default['label'])),
                    'enabled' => (bool) ($channel['enabled'] ?? $default['enabled']),
                    'account_name' => trim((string) ($channel['account_name'] ?? $default['account_name'])),
                    'collection_number' => trim((string) ($channel['collection_number'] ?? $default['collection_number'])),
                    'instructions' => trim((string) ($channel['instructions'] ?? $default['instructions'])),
                    'cash_account_id' => (int) ($channel['cash_account_id'] ?? 0) ?: null,
                    'auto_record' => (bool) ($channel['auto_record'] ?? false),
                    'callback_secret' => trim((string) ($channel['callback_secret'] ?? '')),
                    'callback_url' => route('payment-gateways.callbacks.store', ['company' => $companyId, 'method' => $method]),
                    'callback_ready' => (bool) ($channel['enabled'] ?? $default['enabled'])
                        && trim((string) ($channel['callback_secret'] ?? '')) !== '',
                ];
            })
            ->all();
    }

    public function publicChannelsForCompany(int $companyId): array
    {
        return collect($this->configurationForCompany($companyId))
            ->filter(fn (array $channel) => $channel['enabled'])
            ->map(function (array $channel, string $method) {
                $target = trim(implode(' · ', array_filter([
                    $channel['account_name'] ?: null,
                    $channel['collection_number'] ?: null,
                ])));

                return [
                    'method' => $method,
                    'label' => $channel['label'] ?: $this->labelForMethod($method),
                    'account_name' => $channel['account_name'] ?: null,
                    'collection_number' => $channel['collection_number'] ?: null,
                    'instructions' => $channel['instructions'] ?: null,
                    'target' => $target !== '' ? $target : null,
                    'requires_reference' => in_array($method, ['wave', 'orange_money', 'moov_money', 'bank_transfer', 'cheque'], true),
                ];
            })
            ->values()
            ->all();
    }

    public function channelForCompany(int $companyId, string $method): ?array
    {
        return $this->configurationForCompany($companyId)[$method] ?? null;
    }

    public function labelForMethod(string $method): string
    {
        return PaymentMethodCatalog::label($method) ?? ucfirst(str_replace('_', ' ', $method));
    }

    public function updateConfiguration(int $companyId, ?int $tenantId, array $configuration): Setting
    {
        $setting = Setting::query()->firstOrNew([
            'company_id' => $companyId,
            'key' => 'payment_gateways',
        ]);

        $payload = collect($this->defaults())
            ->map(function (array $default, string $method) use ($configuration) {
                $channel = is_array($configuration[$method] ?? null) ? $configuration[$method] : [];

                return [
                    'label' => trim((string) ($channel['label'] ?? $default['label'])),
                    'enabled' => (bool) ($channel['enabled'] ?? false),
                    'account_name' => trim((string) ($channel['account_name'] ?? '')),
                    'collection_number' => trim((string) ($channel['collection_number'] ?? '')),
                    'instructions' => trim((string) ($channel['instructions'] ?? '')),
                    'cash_account_id' => (int) ($channel['cash_account_id'] ?? 0) ?: null,
                    'auto_record' => (bool) ($channel['auto_record'] ?? false),
                    'callback_secret' => trim((string) ($channel['callback_secret'] ?? '')),
                ];
            })
            ->all();

        $setting->fill([
            'tenant_id' => $tenantId,
            'value' => $payload,
        ])->save();

        return $setting->fresh();
    }

    private function defaults(): array
    {
        return [
            'wave' => [
                'label' => 'Wave',
                'enabled' => false,
                'account_name' => '',
                'collection_number' => '',
                'instructions' => 'Utilise la reference facture comme libelle de transaction.',
            ],
            'orange_money' => [
                'label' => 'Orange Money',
                'enabled' => false,
                'account_name' => '',
                'collection_number' => '',
                'instructions' => 'Indique la reference facture dans le motif ou en commentaire.',
            ],
            'moov_money' => [
                'label' => 'Moov Money',
                'enabled' => false,
                'account_name' => '',
                'collection_number' => '',
                'instructions' => 'Pense a transmettre le numero de transaction a l equipe comptable.',
            ],
            'bank_transfer' => [
                'label' => 'Virement bancaire',
                'enabled' => false,
                'account_name' => '',
                'collection_number' => '',
                'instructions' => 'Merci de rappeler le numero de facture dans l ordre de virement.',
            ],
        ];
    }
}
