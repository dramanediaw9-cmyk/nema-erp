<?php

namespace App\Modules\Core\Integrations\Services;

use App\Models\User;
use App\Modules\Core\Integrations\Models\IntegrationConnection;
use Illuminate\Support\Collection;

class IntegrationSecretGovernanceService
{
    public function authenticationModes(): array
    {
        return [
            'api_key' => 'API key',
            'oauth_client' => 'OAuth client',
            'shared_secret' => 'Shared secret',
            'token_exchange' => 'Token exchange',
            'manual' => 'Manuel',
        ];
    }

    public function secretHealthOptions(): array
    {
        return [
            'healthy' => 'Sain',
            'watch' => 'A surveiller',
            'critical' => 'Critique',
        ];
    }

    public function applyToConnection(IntegrationConnection $connection, array $attributes, ?User $actor = null): IntegrationConnection
    {
        $connection->fill([
            'authentication_mode' => $attributes['authentication_mode'],
            'secret_health_status' => $attributes['secret_health_status'],
            'secret_owner_id' => $attributes['secret_owner_id'] ?? null,
            'secret_last_rotated_at' => $attributes['secret_last_rotated_at'] ?? null,
            'secret_rotation_due_at' => $attributes['secret_rotation_due_at'] ?? null,
            'secret_expires_at' => $attributes['secret_expires_at'] ?? null,
            'secret_notes' => $attributes['secret_notes'] ?? null,
            'updated_by' => $actor?->id ?? $connection->updated_by,
        ]);
        $connection->save();

        return $connection->fresh(['branch', 'owner', 'secretOwner']);
    }

    public function summary(Collection $connections): array
    {
        $items = $connections->map(fn (IntegrationConnection $connection): array => $this->profile($connection))->values();

        return [
            'total' => $items->count(),
            'healthy' => $items->where('computed_status', 'healthy')->count(),
            'watch' => $items->where('computed_status', 'watch')->count(),
            'critical' => $items->where('computed_status', 'critical')->count(),
            'rotation_due_soon' => $items->where('rotation_due_soon', true)->count(),
            'rotation_overdue' => $items->where('rotation_overdue', true)->count(),
            'expiring_soon' => $items->where('expires_soon', true)->count(),
            'expired' => $items->where('expired', true)->count(),
            'items' => $items->all(),
        ];
    }

    public function profile(IntegrationConnection $connection): array
    {
        $status = $connection->secret_health_status ?: 'watch';
        $severity = ['healthy' => 0, 'watch' => 1, 'critical' => 2];
        $alerts = collect();
        $now = now();

        if ($connection->status === 'active' && blank($connection->secret_last_rotated_at)) {
            $status = $this->raiseStatus($status, 'watch', $severity);
            $alerts->push('Aucune rotation de secret renseignee.');
        }

        $rotationOverdue = false;
        $rotationDueSoon = false;
        if ($connection->secret_rotation_due_at) {
            if ($connection->secret_rotation_due_at->lte($now)) {
                $rotationOverdue = true;
                $status = $this->raiseStatus($status, 'critical', $severity);
                $alerts->push('Rotation de secret en retard.');
            } elseif ($connection->secret_rotation_due_at->lte($now->copy()->addDays(7))) {
                $rotationDueSoon = true;
                $status = $this->raiseStatus($status, 'watch', $severity);
                $alerts->push('Rotation de secret a planifier sous 7 jours.');
            }
        }

        $expired = false;
        $expiresSoon = false;
        if ($connection->secret_expires_at) {
            if ($connection->secret_expires_at->lte($now)) {
                $expired = true;
                $status = $this->raiseStatus($status, 'critical', $severity);
                $alerts->push('Secret expire.');
            } elseif ($connection->secret_expires_at->lte($now->copy()->addDays(7))) {
                $expiresSoon = true;
                $status = $this->raiseStatus($status, 'watch', $severity);
                $alerts->push('Secret expire sous 7 jours.');
            }
        }

        return [
            'id' => $connection->id,
            'code' => $connection->code,
            'name' => $connection->name,
            'partner_name' => $connection->partner_name,
            'authentication_mode' => $connection->authentication_mode,
            'authentication_mode_label' => $this->authenticationModes()[$connection->authentication_mode] ?? $connection->authentication_mode,
            'declared_status' => $connection->secret_health_status,
            'declared_status_label' => $this->secretHealthOptions()[$connection->secret_health_status] ?? $connection->secret_health_status,
            'computed_status' => $status,
            'computed_status_label' => $this->secretHealthOptions()[$status] ?? $status,
            'rotation_due_soon' => $rotationDueSoon,
            'rotation_overdue' => $rotationOverdue,
            'expires_soon' => $expiresSoon,
            'expired' => $expired,
            'secret_last_rotated_at' => $connection->secret_last_rotated_at,
            'secret_rotation_due_at' => $connection->secret_rotation_due_at,
            'secret_expires_at' => $connection->secret_expires_at,
            'secret_owner_id' => $connection->secret_owner_id,
            'secret_owner_name' => $connection->secretOwner?->name,
            'alerts' => $alerts->values()->all(),
            'message' => $alerts->isNotEmpty()
                ? $alerts->join(' ')
                : 'Secret et rotation sous controle.',
            'notes' => $connection->secret_notes,
        ];
    }

    private function raiseStatus(string $current, string $candidate, array $severity): string
    {
        return ($severity[$candidate] ?? 0) > ($severity[$current] ?? 0) ? $candidate : $current;
    }
}
