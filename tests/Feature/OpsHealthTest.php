<?php

namespace Tests\Feature;

use App\Models\User;
use App\Mail\OpsTestMail;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Integrations\Models\ApiToken;
use App\Modules\Core\Integrations\Models\IntegrationConnection;
use App\Modules\Core\Integrations\Models\IntegrationEvent;
use App\Modules\Core\Ops\Services\SystemHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class OpsHealthTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_operations_page_is_accessible_to_company_admin(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($user)->withSession([
            'current_tenant_id' => $user->tenant_id,
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ]);

        $this->get(route('ops.index'))
            ->assertOk()
            ->assertSee('Sante systeme')
            ->assertSee('Outbox integration')
            ->assertSee('Sauvegardes locales')
            ->assertSee('Restauration guidee')
            ->assertSee('Surveillance applicative')
            ->assertSee('Backplane technique')
            ->assertSee('Connecteurs partenaires')
            ->assertSee('Secrets des connecteurs')
            ->assertSee('nema:ops:monitor-app');
    }

    public function test_health_report_flags_expiring_tokens_and_critical_connections(): void
    {
        $company = Company::query()->where('name', 'Nema Distribution')->firstOrFail();
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        ApiToken::query()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'name' => 'Jeton integration legacy',
            'token_hash' => hash('sha256', 'legacy_ops_token'),
            'last_used_at' => now()->subDays(45),
            'expires_at' => now()->addDays(3),
            'created_by' => $manager->id,
        ]);

        IntegrationConnection::query()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'owner_id' => $manager->id,
            'secret_owner_id' => $manager->id,
            'code' => 'INT-OPS-0001',
            'name' => 'Connecteur logistique critique',
            'partner_name' => 'Sahel Fulfillment',
            'connection_type' => 'logistics',
            'authentication_mode' => 'shared_secret',
            'sync_mode' => 'bidirectional',
            'status' => 'active',
            'health_status' => 'critical',
            'secret_health_status' => 'watch',
            'last_sync_at' => now()->subDays(5),
            'last_health_at' => now()->subDays(4),
            'secret_last_rotated_at' => now()->subDays(40),
            'secret_rotation_due_at' => now()->subDays(2),
            'secret_expires_at' => now()->addDays(2),
            'scope_summary' => 'Expeditions, statuts et preuves de livraison.',
            'secret_notes' => 'Rotation ratee sur environnement de preprod.',
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        $report = app(SystemHealthService::class)->report($company);
        $tokenCheck = collect($report['checks'])->firstWhere('key', 'api_tokens');
        $connectionCheck = collect($report['checks'])->firstWhere('key', 'integration_connections');
        $secretCheck = collect($report['checks'])->firstWhere('key', 'integration_connection_secrets');

        $this->assertNotNull($tokenCheck);
        $this->assertSame('warning', $tokenCheck['status']);
        $this->assertSame(1, (int) data_get($tokenCheck, 'meta.expiring_soon'));
        $this->assertGreaterThanOrEqual(1, (int) data_get($tokenCheck, 'meta.stale'));

        $this->assertNotNull($connectionCheck);
        $this->assertSame('fail', $connectionCheck['status']);
        $this->assertSame(1, (int) data_get($connectionCheck, 'meta.critical_active'));
        $this->assertNotNull($secretCheck);
        $this->assertSame('fail', $secretCheck['status']);
        $this->assertSame(2, (int) data_get($secretCheck, 'meta.rotation_overdue'));
        $this->assertSame(2, (int) data_get($secretCheck, 'meta.expiring_soon'));
        $this->assertSame('fail', $report['overall_status']);
    }

    public function test_health_report_flags_database_backplane_in_production(): void
    {
        $company = Company::query()->where('name', 'Nema Distribution')->firstOrFail();

        config()->set('app.env', 'production');
        config()->set('cache.default', 'redis');
        config()->set('session.driver', 'database');
        config()->set('session.store', null);
        config()->set('queue.default', 'database');

        $report = app(SystemHealthService::class)->report($company);
        $backplaneCheck = collect($report['checks'])->firstWhere('key', 'runtime_backplane');

        $this->assertNotNull($backplaneCheck);
        $this->assertSame('fail', $backplaneCheck['status']);
        $this->assertSame(['session', 'queue'], data_get($backplaneCheck, 'meta.database_backed'));
        $this->assertTrue((bool) data_get($backplaneCheck, 'meta.redis_backplane'));
        $this->assertStringContainsString('Redis/Valkey est disponible', $backplaneCheck['message']);
    }

    public function test_health_check_command_can_store_company_snapshot(): void
    {
        $company = Company::query()->where('name', 'Nema Distribution')->firstOrFail();

        $this->artisan('nema:ops:health-check', [
            '--store' => true,
            '--company' => [$company->id],
        ])->assertExitCode(0);

        $this->assertDatabaseHas('system_health_snapshots', [
            'company_id' => $company->id,
            'scope' => 'company',
        ]);
    }

    public function test_backup_command_creates_manifest_and_health_report_detects_latest_backup(): void
    {
        $backupPath = storage_path('framework/testing/backups');
        config()->set('ops.backups_path', $backupPath);
        File::deleteDirectory($backupPath);

        $this->artisan('nema:ops:backup-run', [
            '--keep' => 3,
        ])->assertExitCode(0);

        $this->artisan('nema:ops:backup-verify')->assertExitCode(0);

        $manifestPath = collect(File::directories($backupPath))
            ->sortDesc()
            ->map(fn (string $directory): string => $directory.DIRECTORY_SEPARATOR.'manifest.json')
            ->first(fn (string $path): bool => File::exists($path));

        $this->assertNotNull($manifestPath);

        $manifest = json_decode(File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);

        $this->assertNotEmpty($manifest['tables'] ?? []);
        $this->assertGreaterThan(0, (int) ($manifest['tables_count'] ?? 0));

        $company = Company::query()->where('name', 'Nema Distribution')->firstOrFail();
        $report = app(SystemHealthService::class)->report($company);
        $backupCheck = collect($report['checks'])->firstWhere('key', 'backups');

        $this->assertNotNull($backupCheck);
        $this->assertSame('ok', $backupCheck['status']);
        $this->assertSame((int) ($manifest['tables_count'] ?? 0), (int) data_get($backupCheck, 'meta.tables_expected'));
        $this->assertSame((int) ($manifest['tables_count'] ?? 0), (int) data_get($backupCheck, 'meta.tables_checked'));
    }

    public function test_backup_verification_fails_when_a_table_dump_is_corrupted(): void
    {
        $backupPath = storage_path('framework/testing/backups');
        config()->set('ops.backups_path', $backupPath);
        File::deleteDirectory($backupPath);

        $this->artisan('nema:ops:backup-run', [
            '--keep' => 2,
        ])->assertExitCode(0);

        $manifestPath = collect(File::directories($backupPath))
            ->sortDesc()
            ->map(fn (string $directory): string => $directory.DIRECTORY_SEPARATOR.'manifest.json')
            ->first(fn (string $path): bool => File::exists($path));

        $this->assertNotNull($manifestPath);

        $manifest = json_decode(File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $firstTable = collect($manifest['tables'] ?? [])->first();

        $this->assertNotNull($firstTable);

        File::put(dirname($manifestPath).DIRECTORY_SEPARATOR.$firstTable['path'], '{invalid json');

        $this->artisan('nema:ops:backup-verify')->assertExitCode(1);

        $company = Company::query()->where('name', 'Nema Distribution')->firstOrFail();
        $report = app(SystemHealthService::class)->report($company);
        $backupCheck = collect($report['checks'])->firstWhere('key', 'backups');

        $this->assertSame('fail', $backupCheck['status']);
        $this->assertNotEmpty(data_get($backupCheck, 'meta.errors', []));
    }

    public function test_monitor_app_command_returns_failure_when_logs_and_failed_jobs_are_detected(): void
    {
        $logPath = storage_path('framework/testing/ops-monitor.log');
        File::ensureDirectoryExists(dirname($logPath));
        File::put($logPath, "[2026-04-04 10:00:00] local.ERROR: Incident de monitoring test\n");

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
            'payload' => json_encode(['job' => 'MonitorOpsTest'], JSON_THROW_ON_ERROR),
            'exception' => 'RuntimeException: incident technique test',
            'failed_at' => now(),
        ]);

        $this->artisan('nema:ops:monitor-app', [
            '--tail' => 20,
        ])->assertExitCode(1);
    }

    public function test_outbox_prune_command_removes_old_published_events_only(): void
    {
        $company = Company::query()->where('name', 'Nema Distribution')->firstOrFail();

        $oldPublished = IntegrationEvent::query()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'aggregate_type' => Company::class,
            'aggregate_id' => (string) $company->id,
            'event_name' => 'company.synced',
            'payload' => ['company' => $company->name],
            'status' => 'published',
            'available_at' => now()->subDays(40),
            'published_at' => now()->subDays(35),
            'attempts' => 1,
        ]);

        $pending = IntegrationEvent::query()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'aggregate_type' => Company::class,
            'aggregate_id' => (string) $company->id,
            'event_name' => 'company.pending',
            'payload' => ['company' => $company->name],
            'status' => 'pending',
            'available_at' => now(),
            'attempts' => 0,
        ]);

        $this->artisan('nema:ops:outbox-prune', [
            '--days' => 30,
        ])->assertExitCode(0);

        $this->assertDatabaseMissing('integration_events', ['id' => $oldPublished->id]);
        $this->assertDatabaseHas('integration_events', ['id' => $pending->id]);
    }

    public function test_alert_dispatch_command_sends_webhook_when_monitoring_is_in_warning(): void
    {
        Http::fake([
            'https://alerts.example.test/*' => Http::response(['ok' => true], 202),
        ]);

        config()->set('services.ops_alerting.webhook_url', 'https://alerts.example.test/webhook');
        config()->set('services.ops_alerting.minimum_level', 'warning');
        config()->set('ops.log_warning_threshold', 1);
        config()->set('ops.log_fail_threshold', 10);

        $logPath = storage_path('framework/testing/ops-alert.log');
        File::ensureDirectoryExists(dirname($logPath));
        File::put($logPath, "[2026-04-17 08:00:00] production.ERROR: Test alert dispatch\n");
        config()->set('logging.default', 'single');
        config()->set('logging.channels.single.path', $logPath);

        $this->artisan('nema:ops:alert-dispatch')->assertExitCode(0);

        Http::assertSentCount(1);
    }

    public function test_backup_offsite_sync_command_uploads_latest_backup_to_remote_disk(): void
    {
        Storage::fake('offsite');

        $backupPath = storage_path('framework/testing/backups-offsite');
        config()->set('ops.backups_path', $backupPath);
        config()->set('ops.backup_offsite_disk', 'offsite');
        config()->set('ops.backup_offsite_prefix', 'nema-erp/testing');
        File::deleteDirectory($backupPath);

        $this->artisan('nema:ops:backup-run', [
            '--keep' => 2,
        ])->assertExitCode(0);

        $this->artisan('nema:ops:backup-offsite-sync')->assertExitCode(0);

        $files = Storage::disk('offsite')->allFiles('nema-erp/testing');
        $this->assertNotEmpty($files);
    }

    public function test_backup_offsite_verify_command_passes_after_sync(): void
    {
        Storage::fake('offsite');

        $backupPath = storage_path('framework/testing/backups-offsite-verify');
        config()->set('ops.backups_path', $backupPath);
        config()->set('ops.backup_offsite_disk', 'offsite');
        config()->set('ops.backup_offsite_prefix', 'nema-erp/testing-verify');
        File::deleteDirectory($backupPath);

        $this->artisan('nema:ops:backup-run', [
            '--keep' => 1,
        ])->assertExitCode(0);

        $this->artisan('nema:ops:backup-offsite-sync')->assertExitCode(0);
        $this->artisan('nema:ops:backup-offsite-verify')->assertExitCode(0);
    }

    public function test_alert_dispatch_adds_idempotency_headers(): void
    {
        Http::fake([
            'https://alerts.example.test/*' => Http::response(['ok' => true], 202),
        ]);

        config()->set('services.ops_alerting.webhook_url', 'https://alerts.example.test/webhook');
        config()->set('services.ops_alerting.minimum_level', 'warning');
        config()->set('ops.log_warning_threshold', 1);
        config()->set('ops.log_fail_threshold', 10);
        config()->set('ops.alert_signature_secret', 'unit-test-secret');

        $logPath = storage_path('framework/testing/ops-alert-headers.log');
        File::ensureDirectoryExists(dirname($logPath));
        File::put($logPath, "[2026-04-17 08:00:00] production.ERROR: Test alert headers\n");
        config()->set('logging.default', 'single');
        config()->set('logging.channels.single.path', $logPath);

        $this->artisan('nema:ops:alert-dispatch')->assertExitCode(0);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            return $request->hasHeader('X-Nema-Idempotency-Key')
                && $request->hasHeader('X-Nema-Signature');
        });
    }

    public function test_execute_priorities_command_runs_in_check_mode(): void
    {
        $company = Company::query()->where('name', 'Nema Distribution')->firstOrFail();

        $this->artisan('nema:ops:execute-priorities', [
            '--company' => [$company->id],
            '--json' => true,
        ])->assertExitCode(0);
    }

    public function test_execute_priorities_command_runs_in_apply_mode_with_fakes(): void
    {
        Storage::fake('offsite');
        Http::fake([
            'https://alerts.example.test/*' => Http::response(['ok' => true], 202),
        ]);

        config()->set('services.ops_alerting.webhook_url', 'https://alerts.example.test/webhook');
        config()->set('ops.alert_signature_secret', 'unit-test-secret');
        config()->set('ops.backups_path', storage_path('framework/testing/backups-priority-exec'));
        config()->set('ops.backup_offsite_disk', 'offsite');
        config()->set('ops.backup_offsite_prefix', 'nema-erp/priority-exec');
        config()->set('filesystems.disks.offsite', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/offsite'),
        ]);

        $this->artisan('nema:ops:backup-run', [
            '--keep' => 1,
        ])->assertExitCode(0);

        $company = Company::query()->where('name', 'Nema Distribution')->firstOrFail();

        $this->artisan('nema:ops:execute-priorities', [
            '--company' => [$company->id],
            '--apply' => true,
            '--json' => true,
        ])->assertExitCode(0);
    }

    public function test_company_admin_can_send_a_test_email_from_ops_page(): void
    {
        Mail::fake();
        config()->set('mail.default', 'smtp');

        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($user)->withSession([
            'current_tenant_id' => $user->tenant_id,
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ]);

        $this->from(route('ops.index'))
            ->post(route('ops.mail-test'), [
                'email' => 'destinataire@example.com',
                'subject' => 'Test SMTP ERP',
            ])
            ->assertRedirect(route('ops.index'))
            ->assertSessionHas('success');

        Mail::assertSent(OpsTestMail::class, function (OpsTestMail $mail): bool {
            return $mail->hasTo('destinataire@example.com')
                && $mail->subjectLine === 'Test SMTP ERP';
        });
    }

    public function test_test_email_action_is_blocked_while_mailer_is_log(): void
    {
        Mail::fake();
        config()->set('mail.default', 'log');

        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($user)->withSession([
            'current_tenant_id' => $user->tenant_id,
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ]);

        $this->from(route('ops.index'))
            ->post(route('ops.mail-test'), [
                'email' => 'destinataire@example.com',
            ])
            ->assertRedirect(route('ops.index'))
            ->assertSessionHasErrors('mail_test');

        Mail::assertNothingSent();
    }
}
