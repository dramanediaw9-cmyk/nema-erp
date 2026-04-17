<?php

namespace App\Modules\Core\Ops\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OpsAlertingService
{
    public function dispatchMonitoringAlert(array $summary, bool $force = false): array
    {
        $webhookUrl = trim((string) config('services.ops_alerting.webhook_url', ''));
        $timeout = max((int) config('services.ops_alerting.timeout', 10), 1);
        $minLevel = strtolower((string) config('services.ops_alerting.minimum_level', 'warning'));
        $retryTimes = max((int) config('ops.alert_retry_times', 3), 1);
        $retryBackoff = max((int) config('ops.alert_retry_backoff_ms', 300), 50);
        $circuitFailures = max((int) config('ops.alert_circuit_failures', 5), 1);
        $circuitTtlMinutes = max((int) config('ops.alert_circuit_ttl_minutes', 10), 1);

        if ($this->isCircuitOpen()) {
            return [
                'status' => 'warning',
                'message' => 'Alerting temporairement suspendu (circuit breaker actif).',
                'sent' => false,
            ];
        }

        if ($webhookUrl === '') {
            return [
                'status' => 'warning',
                'message' => 'Webhook alerting Ops non configure.',
                'sent' => false,
            ];
        }

        $status = strtolower((string) Arr::get($summary, 'status', 'ok'));

        if (! $force && ! $this->shouldSend($status, $minLevel)) {
            return [
                'status' => 'ok',
                'message' => 'Alerte ignoree : statut sous le seuil minimum.',
                'sent' => false,
                'minimum_level' => $minLevel,
            ];
        }

        $idempotencyKey = $this->idempotencyKey($summary);
        $payload = [
            'source' => 'nema-erp',
            'type' => 'ops.monitoring',
            'status' => $status,
            'sent_at' => now()->toIso8601String(),
            'idempotency_key' => $idempotencyKey,
            'app_url' => config('app.url'),
            'environment' => config('app.env'),
            'summary' => [
                'logs' => [
                    'status' => Arr::get($summary, 'logs.status', 'ok'),
                    'signals_count' => (int) Arr::get($summary, 'logs.signals_count', 0),
                    'critical_count' => (int) Arr::get($summary, 'logs.critical_count', 0),
                    'last_signal_at' => Arr::get($summary, 'logs.last_signal_at'),
                    'last_signal_excerpt' => Arr::get($summary, 'logs.last_signal_excerpt'),
                ],
                'failed_jobs' => [
                    'status' => Arr::get($summary, 'failed_jobs.status', 'ok'),
                    'count' => (int) Arr::get($summary, 'failed_jobs.count', 0),
                    'recent_count' => (int) Arr::get($summary, 'failed_jobs.recent_count', 0),
                    'last_failed_at' => Arr::get($summary, 'failed_jobs.last_failed_at'),
                ],
            ],
        ];
        $signature = $this->signature($payload);

        $response = Http::timeout($timeout)
            ->retry($retryTimes, $retryBackoff)
            ->acceptJson()
            ->asJson()
            ->withHeaders(array_filter([
                'X-Nema-Event-Type' => 'ops.monitoring',
                'X-Nema-Event-Id' => (string) now()->timestamp,
                'X-Nema-Idempotency-Key' => $idempotencyKey,
                'X-Nema-Signature' => $signature,
            ]))
            ->post($webhookUrl, $payload);

        if (! $response->successful()) {
            $this->rememberFailure($circuitFailures, $circuitTtlMinutes);

            return [
                'status' => 'fail',
                'message' => 'Echec envoi alerte Ops (HTTP '.$response->status().').',
                'sent' => false,
                'http_status' => $response->status(),
            ];
        }

        $this->clearFailures();

        return [
            'status' => 'ok',
            'message' => 'Alerte Ops envoyee avec succes.',
            'sent' => true,
            'http_status' => $response->status(),
        ];
    }

    private function shouldSend(string $status, string $minimumLevel): bool
    {
        $priority = [
            'ok' => 0,
            'warning' => 1,
            'fail' => 2,
        ];

        return ($priority[$status] ?? 0) >= ($priority[$minimumLevel] ?? 1);
    }

    private function idempotencyKey(array $summary): string
    {
        return hash('sha256', json_encode([
            'status' => Arr::get($summary, 'status', 'ok'),
            'logs.last_signal_at' => Arr::get($summary, 'logs.last_signal_at'),
            'logs.signals_count' => Arr::get($summary, 'logs.signals_count'),
            'failed_jobs.count' => Arr::get($summary, 'failed_jobs.count'),
        ], JSON_UNESCAPED_UNICODE));
    }

    private function signature(array $payload): ?string
    {
        $secret = trim((string) config('ops.alert_signature_secret', ''));

        if ($secret === '') {
            return null;
        }

        return 'sha256='.hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE), $secret);
    }

    private function isCircuitOpen(): bool
    {
        return Cache::get('ops_alerting:circuit_open_until', 0) > now()->timestamp;
    }

    private function rememberFailure(int $limit, int $ttlMinutes): void
    {
        $failures = (int) Cache::get('ops_alerting:consecutive_failures', 0) + 1;
        Cache::put('ops_alerting:consecutive_failures', $failures, now()->addMinutes($ttlMinutes));

        if ($failures >= $limit) {
            Cache::put('ops_alerting:circuit_open_until', now()->addMinutes($ttlMinutes)->timestamp, now()->addMinutes($ttlMinutes));
        }
    }

    private function clearFailures(): void
    {
        Cache::forget('ops_alerting:consecutive_failures');
        Cache::forget('ops_alerting:circuit_open_until');
    }
}
