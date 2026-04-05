<?php

namespace Tests\Feature;

use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Notifications\Models\InternalNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class OpsMonitoringAlertTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_sync_internal_command_creates_and_resolves_technical_alerts(): void
    {
        $company = Company::query()->where('name', 'Nema Distribution')->firstOrFail();
        $logPath = storage_path('framework/testing/ops-internal-sync.log');

        File::ensureDirectoryExists(dirname($logPath));
        File::put($logPath, "[2026-04-04 10:00:00] local.ERROR: Incident technique a remonter\n");

        config()->set('logging.default', 'single');
        config()->set('logging.channels.single.path', $logPath);
        config()->set('ops.log_warning_threshold', 1);
        config()->set('ops.log_fail_threshold', 1);
        config()->set('ops.failed_jobs_warning', 1);
        config()->set('ops.failed_jobs_fail', 1);

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['job' => 'OpsSyncInternalTest'], JSON_THROW_ON_ERROR),
            'exception' => 'RuntimeException: job technique en echec',
            'failed_at' => now(),
        ]);

        $this->artisan('nema:notifications:sync-internal', [
            '--company' => [$company->id],
        ])->assertExitCode(0);

        $logAlert = InternalNotification::query()
            ->where('company_id', $company->id)
            ->where('code', 'ops-log-health')
            ->firstOrFail();

        $failedJobsAlert = InternalNotification::query()
            ->where('company_id', $company->id)
            ->where('code', 'ops-failed-jobs')
            ->firstOrFail();

        $this->assertNull($logAlert->resolved_at);
        $this->assertNull($failedJobsAlert->resolved_at);
        $this->assertSame('danger', $logAlert->level);
        $this->assertSame('danger', $failedJobsAlert->level);

        File::put($logPath, "[2026-04-04 10:15:00] local.INFO: Monitoring redevenu propre\n");
        DB::table('failed_jobs')->delete();

        $this->artisan('nema:notifications:sync-internal', [
            '--company' => [$company->id],
        ])->assertExitCode(0);

        $this->assertNotNull($logAlert->fresh()->resolved_at);
        $this->assertNotNull($failedJobsAlert->fresh()->resolved_at);
    }
}
