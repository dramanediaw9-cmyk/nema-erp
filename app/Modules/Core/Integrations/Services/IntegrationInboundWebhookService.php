<?php

namespace App\Modules\Core\Integrations\Services;

use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Company\Models\Setting;
use App\Modules\Core\Integrations\Models\IntegrationEvent;
use App\Modules\Core\Integrations\Models\IntegrationInboundWebhook;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class IntegrationInboundWebhookService
{
    public function configurationForCompany(int $companyId): array
    {
        $setting = Setting::query()
            ->where('company_id', $companyId)
            ->where('key', 'integrations')
            ->first();

        $inbound = data_get($setting?->value ?? [], 'inbound', []);

        return [
            'enabled' => (bool) ($inbound['enabled'] ?? false),
            'secret' => trim((string) ($inbound['secret'] ?? '')),
            'source_name' => trim((string) ($inbound['source_name'] ?? '')),
        ];
    }

    public function updateConfiguration(int $companyId, ?int $tenantId, array $configuration): Setting
    {
        $setting = Setting::query()->firstOrNew([
            'company_id' => $companyId,
            'key' => 'integrations',
        ]);

        $value = is_array($setting->value) ? $setting->value : [];
        $value['inbound'] = [
            'enabled' => (bool) ($configuration['enabled'] ?? false),
            'secret' => trim((string) ($configuration['secret'] ?? '')),
            'source_name' => trim((string) ($configuration['source_name'] ?? '')),
        ];

        $setting->fill([
            'tenant_id' => $tenantId,
            'value' => $value,
        ])->save();

        return $setting->fresh();
    }

    public function endpointUrl(Company $company): string
    {
        return route('integrations.webhooks.inbound.receive', ['company' => $company]);
    }

    public function summaryForCompany(int $companyId): array
    {
        $query = IntegrationInboundWebhook::query()->where('company_id', $companyId);

        return [
            'accepted' => (clone $query)->where('status', 'accepted')->count(),
            'duplicate' => (clone $query)->where('status', 'duplicate')->count(),
            'rejected' => (clone $query)->where('status', 'rejected')->count(),
            'last_received_at' => (clone $query)->max('processed_at'),
        ];
    }

    public function receive(Company $company, Request $request): array
    {
        $configuration = $this->configurationForCompany($company->id);
        $payload = $this->payload($request);
        $rawBody = (string) $request->getContent();
        $signature = trim((string) $request->header('X-Nema-Signature'));
        $headers = $this->headers($request);
        $eventName = trim((string) ($request->header('X-Nema-Event') ?: data_get($payload, 'event_name', '')));
        $source = trim((string) ($request->header('X-Webhook-Source') ?: data_get($payload, 'source', $configuration['source_name'] ?? '')));
        $externalId = trim((string) (data_get($payload, 'external_id') ?: data_get($payload, 'id', '')));
        $integrationEvent = $this->resolveRelatedEvent($company->id, $payload);

        if (($configuration['enabled'] ?? false) !== true) {
            $this->reject($company, $payload, $headers, $signature, $eventName, $source, $externalId, $integrationEvent, 403, 'Webhook entrant desactive pour cette societe.');
        }

        if (($configuration['secret'] ?? '') === '') {
            $this->reject($company, $payload, $headers, $signature, $eventName, $source, $externalId, $integrationEvent, 403, 'Webhook entrant actif sans secret configure.');
        }

        if (! $this->validSignature($rawBody, $signature, $configuration['secret'])) {
            $this->reject($company, $payload, $headers, $signature, $eventName, $source, $externalId, $integrationEvent, 401, 'Signature webhook invalide.');
        }

        $isDuplicate = $externalId !== ''
            && IntegrationInboundWebhook::query()
                ->where('company_id', $company->id)
                ->where('event_name', $eventName)
                ->where('external_id', $externalId)
                ->exists();

        $receipt = IntegrationInboundWebhook::query()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'integration_event_id' => $integrationEvent?->id,
            'source' => $source !== '' ? $source : null,
            'event_name' => $eventName !== '' ? $eventName : null,
            'external_id' => $externalId !== '' ? $externalId : null,
            'status' => $isDuplicate ? 'duplicate' : 'accepted',
            'headers' => $headers,
            'payload' => $payload,
            'signature' => $signature !== '' ? $signature : null,
            'ip_address' => $request->ip(),
            'processed_at' => now(),
            'error_message' => $isDuplicate ? 'Evenement externe deja recu.' : null,
        ]);

        return [
            'receipt' => $receipt->fresh(['integrationEvent']),
            'duplicate' => $isDuplicate,
        ];
    }

    private function reject(
        Company $company,
        array $payload,
        array $headers,
        string $signature,
        string $eventName,
        string $source,
        string $externalId,
        ?IntegrationEvent $integrationEvent,
        int $statusCode,
        string $message,
    ): never {
        IntegrationInboundWebhook::query()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'integration_event_id' => $integrationEvent?->id,
            'source' => $source !== '' ? $source : null,
            'event_name' => $eventName !== '' ? $eventName : null,
            'external_id' => $externalId !== '' ? $externalId : null,
            'status' => 'rejected',
            'headers' => $headers,
            'payload' => $payload,
            'signature' => $signature !== '' ? $signature : null,
            'ip_address' => request()->ip(),
            'processed_at' => now(),
            'error_message' => $message,
        ]);

        throw new HttpResponseException(new JsonResponse([
            'message' => $message,
        ], $statusCode));
    }

    private function resolveRelatedEvent(int $companyId, array $payload): ?IntegrationEvent
    {
        $eventId = (int) (data_get($payload, 'integration_event_id')
            ?: data_get($payload, 'event_id')
            ?: data_get($payload, 'payload.integration_event_id')
            ?: 0);

        if ($eventId <= 0) {
            return null;
        }

        return IntegrationEvent::query()
            ->where('company_id', $companyId)
            ->find($eventId);
    }

    private function validSignature(string $rawBody, string $signature, string $secret): bool
    {
        if ($signature === '' || $secret === '') {
            return false;
        }

        $provided = str_starts_with($signature, 'sha256=')
            ? substr($signature, 7)
            : $signature;

        $expected = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $provided);
    }

    private function payload(Request $request): array
    {
        $json = $request->json()->all();

        if (is_array($json) && $json !== []) {
            return $json;
        }

        $raw = trim((string) $request->getContent());
        if ($raw !== '') {
            $decoded = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return Arr::wrap($request->all()) === [] ? [] : $request->all();
    }

    private function headers(Request $request): array
    {
        return collect($request->headers->all())
            ->map(fn (array $values) => count($values) === 1 ? $values[0] : $values)
            ->all();
    }
}
