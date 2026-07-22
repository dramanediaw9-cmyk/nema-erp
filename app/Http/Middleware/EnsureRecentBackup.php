<?php

namespace App\Http\Middleware;

use App\Modules\Core\Ops\Services\BackupService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureRecentBackup
{
    public function __construct(private readonly BackupService $backupService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! (bool) config('ops.web_backup_guard_enabled', true)) {
            return;
        }

        if (! Cache::add('nema:web-backup-guard:lock', now()->toDateTimeString(), $this->intervalSeconds())) {
            return;
        }

        try {
            $latest = $this->backupService->latest();
            $createdAt = ! empty($latest['created_at']) ? Carbon::parse($latest['created_at']) : null;

            if ($createdAt && $createdAt->greaterThanOrEqualTo(now()->subHours($this->maxAgeHours()))) {
                return;
            }

            $manifest = $this->backupService->create((int) config('ops.backup_retention', 7));
            $verification = $this->backupService->verify($manifest['manifest_path'] ?? null);

            Log::info('Sauvegarde automatique web terminee.', [
                'status' => $verification['status'] ?? 'unknown',
                'directory' => $manifest['directory'] ?? null,
                'tables' => $verification['tables_checked'] ?? null,
                'assets' => $verification['assets_checked'] ?? null,
            ]);
        } catch (Throwable $exception) {
            Log::error('Sauvegarde automatique web echouee.', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function intervalSeconds(): int
    {
        return max((int) config('ops.web_backup_guard_interval_minutes', 60), 5) * 60;
    }

    private function maxAgeHours(): int
    {
        return max((int) config('ops.web_backup_guard_max_age_hours', 26), 1);
    }
}
