<?php

namespace App\Modules\Core\Integrations\Services;

use App\Modules\Core\Company\Models\Setting;
use App\Modules\Core\Integrations\Models\IntegrationEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class IntegrationOutboxService
{
    public function record(Model $aggregate, string $eventName, array $payload = []): IntegrationEvent
    {
        return IntegrationEvent::query()->create([
            'tenant_id' => $aggregate->getAttribute('tenant_id'),
            'company_id' => $aggregate->getAttribute('company_id'),
            'aggregate_type' => $aggregate::class,
            'aggregate_id' => (string) $aggregate->getKey(),
            'event_name' => $eventName,
            'payload' => $payload,
            'status' => 'pending',
            'available_at' => now(),
            'attempts' => 0,
        ]);
    }

    public function configurationForCompany(int $companyId): array
    {
        $setting = Setting::query()
            ->where('company_id', $companyId)
            ->where('key', 'integrations')
            ->first();

        $webhook = data_get($setting?->value ?? [], 'webhook', []);

        return [
            'enabled' => (bool) ($webhook['enabled'] ?? false),
            'url' => trim((string) ($webhook['url'] ?? '')),
            'secret' => trim((string) ($webhook['secret'] ?? '')),
            'timeout' => max((int) ($webhook['timeout'] ?? config('services.integrations.webhook_timeout', 10)), 1),
        ];
    }

    public function updateConfiguration(int $companyId, ?int $tenantId, array $configuration): Setting
    {
        $setting = Setting::query()->firstOrNew([
            'company_id' => $companyId,
            'key' => 'integrations',
        ]);

        $value = is_array($setting->value) ? $setting->value : [];
        $value['webhook'] = [
            'enabled' => (bool) ($configuration['enabled'] ?? false),
            'url' => trim((string) ($configuration['url'] ?? '')),
            'secret' => trim((string) ($configuration['secret'] ?? '')),
            'timeout' => max((int) ($configuration['timeout'] ?? config('services.integrations.webhook_timeout', 10)), 1),
        ];

        $setting->fill([
            'tenant_id' => $tenantId,
            'value' => $value,
        ])->save();

        return $setting->fresh();
    }

    public function processPending(?int $companyId = null, int $limit = 50): array
    {
        $ids = $this->claimDispatchableEventIds($companyId, $limit);

        $events = IntegrationEvent::query()
            ->with(['company', 'latestDelivery'])
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get();

        $summary = [
            'selected' => $events->count(),
            'processed' => 0,
            'published' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        $configurations = [];

        foreach ($events as $event) {
            $key = (int) $event->company_id;
            $configurations[$key] ??= $this->configurationForCompany($key);

            if (($configurations[$key]['enabled'] ?? false) !== true) {
                $summary['skipped']++;
                $this->releaseClaimedEvent($event);

                continue;
            }

            $summary['processed']++;
            $dispatched = $this->dispatch($event);

            if ($dispatched->status === 'published') {
                $summary['published']++;
            } else {
                $summary['failed']++;
            }
        }

        return $summary;
    }

    public function dispatch(IntegrationEvent $event): IntegrationEvent
    {
        return DB::transaction(function () use ($event) {
            $event = IntegrationEvent::query()
                ->with(['company', 'deliveries'])
                ->whereKey($event->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($event->status === 'pending') {
                $event->forceFill([
                    'status' => 'processing',
                ])->save();
            }

            if ($event->status !== 'processing') {
                return $event->fresh(['company', 'latestDelivery', 'deliveries']);
            }

            $configuration = $this->configurationForCompany((int) $event->company_id);
            $attemptNumber = (int) $event->attempts + 1;
            $payload = $this->deliveryPayload($event);
            $requestedAt = now();
            $targetUrl = $configuration['url'];

            try {
                if (($configuration['enabled'] ?? false) !== true) {
                    throw new RuntimeException('Le webhook d integration est desactive pour cette societe.');
                }

                if ($targetUrl === '') {
                    throw new RuntimeException('Le webhook d integration est actif mais aucune URL n est configuree.');
                }

                $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                $headers = [
                    'X-Nema-Event' => $event->event_name,
                    'X-Nema-Event-Id' => (string) $event->id,
                    'X-Nema-Company-Id' => (string) $event->company_id,
                    'X-Nema-Delivery-Attempt' => (string) $attemptNumber,
                ];

                if ($configuration['secret'] !== '') {
                    $headers['X-Nema-Signature'] = 'sha256='.hash_hmac('sha256', $jsonPayload, $configuration['secret']);
                }

                $response = Http::acceptJson()
                    ->asJson()
                    ->timeout((int) $configuration['timeout'])
                    ->withHeaders($headers)
                    ->post($targetUrl, $payload);

                $delivery = $event->deliveries()->create([
                    'company_id' => $event->company_id,
                    'channel' => 'webhook',
                    'target_url' => $targetUrl,
                    'status' => $response->successful() ? 'sent' : 'failed',
                    'attempt_number' => $attemptNumber,
                    'requested_at' => $requestedAt,
                    'responded_at' => now(),
                    'request_payload' => $payload,
                    'response_status' => $response->status(),
                    'response_headers' => $response->headers(),
                    'response_body' => Str::limit($response->body(), 5000, ''),
                    'error_message' => $response->successful() ? null : 'HTTP '.$response->status().' - '.$response->reason(),
                ]);

                $event->forceFill([
                    'status' => $response->successful() ? 'published' : 'failed',
                    'available_at' => $response->successful() ? null : now()->addMinutes(min($attemptNumber * 5, 60)),
                    'published_at' => $response->successful() ? now() : null,
                    'attempts' => $attemptNumber,
                    'last_error' => $response->successful() ? null : ($delivery->error_message ?: 'Echec de publication webhook.'),
                ])->save();
            } catch (Throwable $exception) {
                $event->deliveries()->create([
                    'company_id' => $event->company_id,
                    'channel' => 'webhook',
                    'target_url' => $targetUrl ?: null,
                    'status' => 'failed',
                    'attempt_number' => $attemptNumber,
                    'requested_at' => $requestedAt,
                    'responded_at' => now(),
                    'request_payload' => $payload,
                    'error_message' => $exception->getMessage(),
                ]);

                $event->forceFill([
                    'status' => 'failed',
                    'available_at' => now()->addMinutes(min($attemptNumber * 5, 60)),
                    'published_at' => null,
                    'attempts' => $attemptNumber,
                    'last_error' => $exception->getMessage(),
                ])->save();
            }

            return $event->fresh(['company', 'latestDelivery', 'deliveries']);
        });
    }

    private function claimDispatchableEventIds(?int $companyId, int $limit): array
    {
        return DB::transaction(function () use ($companyId, $limit): array {
            $ids = IntegrationEvent::query()
                ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
                ->where(function ($query): void {
                    $query->where('status', 'pending')
                        ->orWhere(function ($processingQuery): void {
                            $processingQuery->where('status', 'processing')
                                ->where('updated_at', '<=', now()->subMinutes(15));
                        });
                })
                ->where(function ($query): void {
                    $query->whereNull('available_at')->orWhere('available_at', '<=', now());
                })
                ->orderBy('available_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->limit(max($limit, 1))
                ->pluck('id');

            if ($ids->isEmpty()) {
                return [];
            }

            IntegrationEvent::query()
                ->whereIn('id', $ids)
                ->update([
                    'status' => 'processing',
                    'updated_at' => now(),
                ]);

            return $ids->map(fn ($id): int => (int) $id)->all();
        });
    }

    private function releaseClaimedEvent(IntegrationEvent $event): void
    {
        IntegrationEvent::query()
            ->whereKey($event->id)
            ->where('status', 'processing')
            ->update([
                'status' => 'pending',
                'updated_at' => now(),
            ]);
    }

    private function deliveryPayload(IntegrationEvent $event): array
    {
        return [
            'id' => $event->id,
            'event_name' => $event->event_name,
            'aggregate_type' => $event->aggregate_type,
            'aggregate_id' => $event->aggregate_id,
            'company_id' => $event->company_id,
            'tenant_id' => $event->tenant_id,
            'occurred_at' => $event->created_at?->toIso8601String(),
            'payload' => $event->payload ?? [],
        ];
    }
}
