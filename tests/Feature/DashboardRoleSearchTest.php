<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Audit\Models\ActivityLog;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Treasury\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardRoleSearchTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_dashboard_adapts_to_each_main_role_profile(): void
    {
        $director = User::query()->where('email', 'dg@nema-erp.test')->firstOrFail();
        $operations = User::query()->where('email', 'ops@nema-erp.test')->firstOrFail();
        $cashier = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($director)
            ->withSession($this->workspaceSession($director))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pilotage executif')
            ->assertSee('Situation operationnelle')
            ->assertSee('Centre d actions premium');

        $this->actingAs($operations)
            ->withSession($this->workspaceSession($operations))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Operations du jour')
            ->assertSee('Situation operationnelle');

        $this->actingAs($cashier)
            ->withSession($this->workspaceSession($cashier))
            ->get(route('dashboard'))
            ->assertRedirect(route('pos.index'));

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Cockpit entreprise')
            ->assertSee('Recherche globale')
            ->assertSee('Centre d actions premium')
            ->assertSee('Situation operationnelle');
    }

    public function test_dashboard_premium_action_center_surfaces_technical_risk_when_monitoring_is_degraded(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $logPath = storage_path('framework/testing/dashboard-premium-monitor.log');

        File::ensureDirectoryExists(dirname($logPath));
        File::put($logPath, "[2026-04-04 10:00:00] local.ERROR: Degradation technique dashboard\n");

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
            'payload' => json_encode(['job' => 'DashboardPremiumMonitor'], JSON_THROW_ON_ERROR),
            'exception' => 'RuntimeException: dashboard monitor incident',
            'failed_at' => now(),
        ]);

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Centre d actions premium')
            ->assertSee('Stabiliser la plateforme');
    }

    public function test_global_search_groups_results_for_authorized_user(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $invoice = SalesInvoice::query()->where('company_id', $manager->company_id)->where('notes', 'Facture de demonstration initiale')->firstOrFail();
        $customer = $invoice->customer()->firstOrFail();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('search.index', ['q' => $customer->name]))
            ->assertOk()
            ->assertSee('Clients')
            ->assertSee($customer->name)
            ->assertSee('Ventes')
            ->assertSee($invoice->invoice_number)
            ->assertSee('Paiements');
    }

    public function test_global_search_can_match_payments_by_cash_account_without_sql_error(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $payment = Payment::query()
            ->where('company_id', $manager->company_id)
            ->with('cashAccount')
            ->whereNotNull('cash_account_id')
            ->firstOrFail();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('search.index', ['q' => $payment->cashAccount?->name]))
            ->assertOk()
            ->assertSee('Paiements')
            ->assertSee($payment->payment_number)
            ->assertSee($payment->cashAccount?->name ?? '');
    }

    public function test_global_search_hides_unauthorized_modules_for_cashier(): void
    {
        $cashier = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $supplier = PurchaseBill::query()->where('company_id', $cashier->company_id)->with('supplier')->firstOrFail()->supplier;
        $this->assertInstanceOf(Partner::class, $supplier);

        $this->actingAs($cashier)
            ->withSession($this->workspaceSession($cashier))
            ->get(route('search.index', ['q' => $supplier->name]))
            ->assertOk()
            ->assertSee('Paiements')
            ->assertDontSee('Fournisseurs')
            ->assertDontSee('Achats');
    }

    public function test_branch_limited_users_do_not_see_other_branch_activity_in_dashboard_or_search(): void
    {
        $operations = User::query()->where('email', 'ops@nema-erp.test')->firstOrFail();
        $cashier = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $director = User::query()->where('email', 'dg@nema-erp.test')->firstOrFail();
        $basePayment = Payment::query()->where('company_id', $operations->company_id)->where('direction', 'in')->firstOrFail();

        $otherBranch = Branch::query()->create([
            'tenant_id' => $operations->tenant_id,
            'company_id' => $operations->company_id,
            'name' => 'Agence Kayes',
            'code' => 'KAY',
            'city' => 'Kayes',
            'address' => 'Kayes Centre',
            'is_active' => true,
            'is_default' => false,
        ]);

        ActivityLog::query()->create([
            'company_id' => $operations->company_id,
            'branch_id' => $otherBranch->id,
            'user_id' => $director->id,
            'action' => 'branch.remote.activity',
            'description' => 'Activite agence Kayes distante',
            'subject_type' => Payment::class,
            'subject_id' => $basePayment->id,
            'properties' => ['channel' => 'test'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        $remotePayment = $basePayment->replicate();
        $remotePayment->fill([
            'branch_id' => $otherBranch->id,
            'payment_number' => 'PAY-BRANCH-SECOND-001',
            'payment_date' => now()->toDateString(),
            'amount' => 654321,
            'reference' => 'BRANCH-SECOND-PAY',
            'notes' => 'BRANCH-SECOND-PAY',
        ]);
        $remotePayment->save();

        $this->actingAs($operations)
            ->withSession($this->workspaceSession($operations))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Activite agence Kayes distante');

        $this->actingAs($director)
            ->withSession($this->workspaceSession($director))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Activite agence Kayes distante');

        $this->actingAs($cashier)
            ->withSession($this->workspaceSession($cashier))
            ->get(route('search.index', ['q' => 'PAY-BRANCH-SECOND-001']))
            ->assertOk()
            ->assertSee('Aucun resultat')
            ->assertDontSee('654 321 XOF')
            ->assertDontSee('BRANCH-SECOND-PAY');

        $this->actingAs($director)
            ->withSession($this->workspaceSession($director))
            ->get(route('search.index', ['q' => 'PAY-BRANCH-SECOND-001']))
            ->assertOk()
            ->assertSee('Paiements')
            ->assertSee('654 321 XOF');
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
