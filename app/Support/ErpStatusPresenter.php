<?php

namespace App\Support;

use Illuminate\Support\Str;

class ErpStatusPresenter
{
    public static function present(string $type, mixed $value = null, array $context = []): array
    {
        $normalized = is_bool($value)
            ? $value
            : Str::lower(trim((string) $value));

        return match ($type) {
            'workflow' => self::workflowStatus($normalized),
            'payment' => self::paymentStatus($normalized),
            'activity' => self::activityStatus($normalized),
            'sync' => self::syncStatus($normalized),
            'portfolio' => self::portfolioStatus($normalized),
            default => self::genericStatus($normalized, $context),
        };
    }

    private static function workflowStatus(string|bool $value): array
    {
        return match ($value) {
            'draft' => self::badge('Brouillon', 'muted'),
            'pending', 'pending_approval', 'review', 'sent' => self::badge('En attente', 'warning'),
            'validated', 'confirmed', 'approved', 'accepted', 'ready', 'active', 'partial_delivered', 'delivered', 'converted', 'completed' => self::badge('Confirme', 'success'),
            'cancelled' => self::badge('Annule', 'danger'),
            'rejected', 'declined' => self::badge('Rejete', 'danger'),
            default => self::genericStatus($value),
        };
    }

    private static function paymentStatus(string|bool $value): array
    {
        return match ($value) {
            'paid' => self::badge('Paye', 'success'),
            'partial', 'partially_paid' => self::badge('Partiellement paye', 'warning'),
            'unpaid', 'open', 'pending' => self::badge('En attente', 'muted'),
            'cancelled', 'failed' => self::badge('Annule', 'danger'),
            default => self::genericStatus($value),
        };
    }

    private static function activityStatus(string|bool $value): array
    {
        if ($value === true || in_array($value, ['active', 'enabled', 'open'], true)) {
            return self::badge('Actif', 'success');
        }

        if ($value === false || in_array($value, ['inactive', 'disabled', 'closed'], true)) {
            return self::badge('Inactif', 'muted');
        }

        return self::genericStatus($value);
    }

    private static function syncStatus(string|bool $value): array
    {
        return match ($value) {
            'synced', 'synchronised', 'synchronized', 'auto_recorded' => self::badge('Synchronise', 'success'),
            'pending', 'queued', 'offline', 'draft' => self::badge('En attente', 'warning'),
            'pending_review' => self::badge('A rapprocher', 'warning'),
            'ignored' => self::badge('Ignore', 'muted'),
            'failed', 'error', 'rejected' => self::badge('Erreur de synchronisation', 'danger'),
            default => self::genericStatus($value),
        };
    }

    private static function portfolioStatus(string|bool $value): array
    {
        return match ($value) {
            'clear', 'current', 'up_to_date' => self::badge('A jour', 'success'),
            'open', 'watch' => self::badge('A suivre', 'muted'),
            'overdue', 'late' => self::badge('En retard', 'warning'),
            default => self::genericStatus($value),
        };
    }

    private static function genericStatus(string|bool $value, array $context = []): array
    {
        if (isset($context['label'], $context['tone'])) {
            return self::badge((string) $context['label'], (string) $context['tone']);
        }

        $label = is_bool($value)
            ? ($value ? 'Oui' : 'Non')
            : Str::of((string) $value)->replace('_', ' ')->title()->value();

        return self::badge($label, 'muted');
    }

    private static function badge(string $label, string $tone): array
    {
        $class = match ($tone) {
            'success' => 'badge-success',
            'warning' => 'badge-warning',
            'danger' => 'badge-danger',
            default => 'badge-muted',
        };

        return [
            'label' => $label,
            'tone' => $tone,
            'class' => $class,
        ];
    }
}
