<?php

namespace App\Modules\Core\Ops\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ApplicationMonitoringService
{
    public function summary(?int $tail = null): array
    {
        $tail = max($tail ?? (int) config('ops.log_tail', 400), 1);
        $logs = $this->logSummary($tail);
        $failedJobs = $this->failedJobsSummary();

        $status = collect([$logs['status'], $failedJobs['status']])->contains('fail')
            ? 'fail'
            : (collect([$logs['status'], $failedJobs['status']])->contains('warning') ? 'warning' : 'ok');

        return [
            'status' => $status,
            'logs' => $logs,
            'failed_jobs' => $failedJobs,
        ];
    }

    public function logSummary(int $tail): array
    {
        $path = $this->resolveLogFile();

        if (! $path || ! File::exists($path)) {
            return [
                'status' => 'warning',
                'message' => 'Aucun fichier log recent n a ete detecte.',
                'path' => $path,
                'line_count' => 0,
                'signals_count' => 0,
                'critical_count' => 0,
                'exception_mentions' => 0,
                'recent_signals' => [],
                'last_signal_at' => null,
                'last_signal_excerpt' => null,
                'modified_at' => null,
            ];
        }

        $lines = collect(preg_split('/\r\n|\r|\n/', (string) File::get($path)))
            ->filter(fn (?string $line): bool => filled($line))
            ->take(-$tail)
            ->values();

        $signals = $lines
            ->map(fn (string $line): ?array => $this->parseLogLine($line))
            ->filter();

        $signalLevels = ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];
        $signalMatches = $signals->filter(fn (array $signal): bool => in_array($signal['level'], $signalLevels, true))->values();
        $criticalCount = $signalMatches->whereIn('level', ['CRITICAL', 'ALERT', 'EMERGENCY'])->count();
        $signalsCount = $signalMatches->count();
        $exceptionMentions = $lines->filter(fn (string $line): bool => Str::contains(Str::lower($line), ['exception', 'stack trace']))->count();
        $warningThreshold = max((int) config('ops.log_warning_threshold', 1), 1);
        $failThreshold = max((int) config('ops.log_fail_threshold', 10), $warningThreshold);

        $status = 'ok';
        if ($criticalCount > 0 || $signalsCount >= $failThreshold) {
            $status = 'fail';
        } elseif ($signalsCount >= $warningThreshold || $exceptionMentions > 0) {
            $status = 'warning';
        }

        $lastSignal = $signalMatches->last();
        $modifiedAt = Carbon::createFromTimestamp((int) File::lastModified($path));

        return [
            'status' => $status,
            'message' => $signalsCount === 0
                ? 'Aucun signal erreur recent dans les logs applicatifs.'
                : $signalsCount.' signal(s) erreur recent(s) detecte(s) dans les logs applicatifs.',
            'path' => $path,
            'line_count' => $lines->count(),
            'signals_count' => $signalsCount,
            'critical_count' => $criticalCount,
            'exception_mentions' => $exceptionMentions,
            'recent_signals' => $signalMatches
                ->take(-5)
                ->map(fn (array $signal): array => [
                    'level' => $signal['level'],
                    'occurred_at' => $signal['occurred_at'],
                    'message' => Str::limit($signal['message'], 220),
                ])
                ->values()
                ->all(),
            'last_signal_at' => $lastSignal['occurred_at'] ?? null,
            'last_signal_excerpt' => $lastSignal ? Str::limit($lastSignal['message'], 220) : null,
            'modified_at' => $modifiedAt->toDateTimeString(),
        ];
    }

    public function failedJobsSummary(): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return [
                'status' => 'ok',
                'message' => 'La table failed_jobs n est pas presente sur cet environnement.',
                'count' => 0,
                'recent_count' => 0,
                'last_failed_at' => null,
                'recent_jobs' => [],
            ];
        }

        $count = DB::table('failed_jobs')->count();
        $recentCount = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();
        $lastFailedAt = DB::table('failed_jobs')->max('failed_at');
        $warningThreshold = max((int) config('ops.failed_jobs_warning', 1), 1);
        $failThreshold = max((int) config('ops.failed_jobs_fail', 10), $warningThreshold);

        $status = $count >= $failThreshold
            ? 'fail'
            : ($count >= $warningThreshold ? 'warning' : 'ok');

        $recentJobs = collect(DB::table('failed_jobs')->orderByDesc('id')->limit(5)->get())
            ->map(fn (object $job): array => [
                'id' => $job->id,
                'queue' => $job->queue,
                'failed_at' => $job->failed_at,
                'exception' => Str::limit(Str::before((string) $job->exception, "\n"), 220),
            ])
            ->all();

        return [
            'status' => $status,
            'message' => $count === 0
                ? 'Aucun job en echec a signaler.'
                : $count.' job(s) en echec sont presents dans la file technique.',
            'count' => $count,
            'recent_count' => $recentCount,
            'last_failed_at' => $lastFailedAt,
            'recent_jobs' => $recentJobs,
        ];
    }

    private function parseLogLine(string $line): ?array
    {
        if (! preg_match('/\[(?<timestamp>[^\]]+)\]\s+[^\.]+\.(?<level>[A-Z]+):\s*(?<message>.*)$/', $line, $matches)) {
            return null;
        }

        return [
            'occurred_at' => $matches['timestamp'],
            'level' => $matches['level'],
            'message' => trim($matches['message']),
        ];
    }

    private function resolveLogFile(): ?string
    {
        $default = (string) config('logging.default', 'stack');
        $channels = $default === 'stack'
            ? collect(config('logging.channels.stack.channels', []))
            : collect([$default]);

        $candidates = $channels
            ->map(function (string $channel): array {
                return [
                    'channel' => $channel,
                    'path' => (string) config('logging.channels.'.$channel.'.path', ''),
                ];
            })
            ->filter(fn (array $candidate): bool => filled($candidate['path']))
            ->values();

        foreach ($candidates as $candidate) {
            $resolved = $candidate['channel'] === 'daily'
                ? $this->resolveDailyLogPath($candidate['path'])
                : $candidate['path'];

            if ($resolved && File::exists($resolved)) {
                return $resolved;
            }
        }

        $fallback = storage_path('logs/laravel.log');

        if (File::exists($fallback)) {
            return $fallback;
        }

        return $this->resolveDailyLogPath($fallback);
    }

    private function resolveDailyLogPath(string $basePath): ?string
    {
        $directory = dirname($basePath);
        $filename = pathinfo($basePath, PATHINFO_FILENAME);
        $extension = pathinfo($basePath, PATHINFO_EXTENSION);
        $todayPath = $directory.DIRECTORY_SEPARATOR.$filename.'-'.now()->format('Y-m-d').($extension ? '.'.$extension : '');

        if (File::exists($todayPath)) {
            return $todayPath;
        }

        $pattern = $directory.DIRECTORY_SEPARATOR.$filename.'-*'.($extension ? '.'.$extension : '');
        $matches = glob($pattern) ?: [];

        if (empty($matches)) {
            return null;
        }

        usort($matches, fn (string $left, string $right): int => filemtime($right) <=> filemtime($left));

        return $matches[0] ?? null;
    }
}
