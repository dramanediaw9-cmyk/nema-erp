<?php

namespace App\Modules\Core\Platform\Services;

use App\Models\User;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Ops\Models\SystemHealthSnapshot;
use App\Modules\Core\Platform\Models\SaasSubscription;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class ControlCenterConnectorService
{
    public function configured(): bool
    {
        return filled(config('services.nema_control_center.url'))
            && filled($this->connectorToken());
    }

    public function sync(): array
    {
        if (! $this->configured()) {
            return ['status' => 'skipped', 'message' => 'Connecteur NEMA Control Center non configure.'];
        }

        $response = $this->client()->post(
            (string) config('services.nema_control_center.url'),
            $this->payload(),
        );

        if (! $response->successful()) {
            return [
                'status' => 'failed',
                'http_status' => $response->status(),
                'message' => 'Le Control Center a refuse la synchronisation.',
            ];
        }

        return [
            'status' => 'accepted',
            'http_status' => $response->status(),
            'message' => 'Inventaire ERP synchronise.',
            'result' => $response->json(),
        ];
    }

    public function payload(): array
    {
        $observedAt = now();
        $subscriptions = Schema::hasTable('saas_subscriptions')
            ? SaasSubscription::query()->orderBy('id')->limit(500)->get()
            : collect();
        $subscriptionsByCompany = $subscriptions->keyBy('company_id');

        $companies = Company::query()
            ->withCount('users')
            ->withMax('users', 'last_login_at')
            ->orderBy('id')
            ->limit(500)
            ->get();

        $users = User::query()
            ->with('roles:id,slug')
            ->orderBy('id')
            ->limit(1000)
            ->get();

        $sessions = $this->sessions($observedAt->timestamp);
        $latestHealth = Schema::hasTable('system_health_snapshots')
            ? SystemHealthSnapshot::query()->latest('captured_at')->first()
            : null;
        $backupStatus = match ($latestHealth?->overall_status) {
            'ok', 'healthy' => 'healthy',
            'warning' => 'warning',
            'fail', 'critical' => 'critical',
            default => 'unknown',
        };

        return [
            'schema_version' => 1,
            'observed_at' => $observedAt->toIso8601String(),
            'source_version' => (string) config('app.version', 'nema-erp'),
            'metrics' => [
                'total_users' => $users->count(),
                'active_users_24h' => $users->filter(fn (User $user): bool => $this->isWithinLastDay($user->last_login_at, $observedAt))->count(),
                'active_sessions' => count($sessions),
                'organizations' => $companies->count(),
                'active_subscriptions' => $subscriptions->whereIn('status', ['active', 'trialing'])->count(),
                'open_incidents' => in_array($latestHealth?->overall_status, ['fail', 'critical'], true) ? 1 : 0,
                'backup_status' => $backupStatus,
            ],
            'organizations' => $companies->map(function (Company $company) use ($subscriptionsByCompany): array {
                $subscription = $subscriptionsByCompany->get($company->id);

                return [
                    'source_id' => (string) $company->id,
                    'name' => $company->name,
                    'status' => $company->is_active ? 'active' : 'suspended',
                    'plan' => $subscription?->plan,
                    'user_count' => (int) $company->users_count,
                    'last_activity_at' => $this->toIso($company->getAttribute('users_max_last_login_at')),
                ];
            })->values()->all(),
            'users' => $users->map(fn (User $user): array => [
                'source_id' => (string) $user->id,
                'organization_source_id' => $user->company_id ? (string) $user->company_id : null,
                'display_name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->roles->pluck('slug')->first(),
                'status' => $user->is_active ? 'active' : 'disabled',
                'last_sign_in_at' => $this->toIso($user->last_login_at),
            ])->values()->all(),
            'sessions' => $sessions,
            'subscriptions' => $subscriptions->map(fn (SaasSubscription $subscription): array => [
                'source_id' => (string) $subscription->id,
                'organization_source_id' => (string) $subscription->company_id,
                'plan' => $subscription->plan,
                'status' => $this->subscriptionStatus($subscription->status),
                'amount_minor' => null,
                'currency' => null,
                'started_at' => $this->toIso($subscription->starts_at),
                'renews_at' => $this->toIso($subscription->trial_ends_at ?? $subscription->ends_at),
            ])->values()->all(),
        ];
    }

    private function client(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withToken($this->connectorToken())
            ->timeout(max((int) config('services.nema_control_center.timeout', 10), 2))
            ->retry(2, 500, throw: false);
    }

    private function connectorToken(): string
    {
        $token = trim((string) config('services.nema_control_center.connector_token'));
        if ($token !== '') {
            return $token;
        }

        $tokenFile = (string) config('services.nema_control_center.connector_token_file');
        if ($tokenFile === '' || ! is_file($tokenFile) || ! is_readable($tokenFile)) {
            return '';
        }

        return trim((string) file_get_contents($tokenFile));
    }

    private function sessions(int $nowTimestamp): array
    {
        if (! Schema::hasTable('sessions')) {
            return [];
        }

        return DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $nowTimestamp - 900)
            ->orderByDesc('last_activity')
            ->limit(500)
            ->get(['id', 'user_id', 'user_agent', 'last_activity'])
            ->map(fn (object $session): array => [
                'session_ref' => hash_hmac('sha256', (string) $session->id, (string) config('app.key')),
                'user_source_id' => (string) $session->user_id,
                'state' => 'active',
                'device_class' => $this->deviceClass((string) $session->user_agent),
                'started_at' => null,
                'last_seen_at' => now()->setTimestamp((int) $session->last_activity)->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    private function deviceClass(string $userAgent): string
    {
        if (preg_match('/ipad|tablet/i', $userAgent)) {
            return 'tablet';
        }

        return preg_match('/mobile|android|iphone/i', $userAgent) ? 'mobile' : 'desktop';
    }

    private function subscriptionStatus(string $status): string
    {
        return in_array($status, ['trialing', 'active', 'past_due', 'paused', 'cancelled', 'expired'], true)
            ? $status
            : 'unknown';
    }

    private function isWithinLastDay(mixed $value, Carbon $now): bool
    {
        return filled($value) && Carbon::parse($value)->greaterThanOrEqualTo($now->copy()->subDay());
    }

    private function toIso(mixed $value): ?string
    {
        return filled($value) ? Carbon::parse($value)->toIso8601String() : null;
    }
}
