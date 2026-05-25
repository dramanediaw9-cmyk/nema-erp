<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Automation\Models\AutomationRule;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Collaboration\Models\Attachment;
use App\Modules\Core\Notifications\Models\InternalNotification;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Models\Payment;
use App\Modules\Treasury\Models\TreasuryReconciliation;
use App\Modules\Treasury\Models\TreasuryReconciliationPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationTreasurySignalTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_manager_can_create_and_run_mobile_money_reconciliation_watch_rule(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $waveAccount = CashAccount::query()->where('company_id', $user->company_id)->where('name', 'Wave')->firstOrFail();
        $orangeAccount = CashAccount::query()->where('company_id', $user->company_id)->where('name', 'Orange Money')->firstOrFail();
        $sikasso = Branch::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'name' => 'Agence Sikasso test',
            'code' => 'SIK-TMM',
            'city' => 'Sikasso',
            'address' => 'Zone test',
            'is_active' => true,
            'is_default' => false,
        ]);
        $sikassoWave = CashAccount::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $sikasso->id,
            'name' => 'Wave Sikasso test',
            'type' => 'mobile_money',
            'account_number' => '22370000',
            'opening_balance' => 0,
            'is_active' => true,
        ]);

        Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $waveAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-MM-AUTO-001',
            'direction' => 'in',
            'payment_type' => 'customer_receipt',
            'payment_date' => now()->toDateString(),
            'amount' => 25000,
            'method' => 'wave',
            'reference' => null,
            'notes' => 'Wave non rapproche Bamako',
            'created_by' => $user->id,
        ]);

        Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $orangeAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-MM-AUTO-002',
            'direction' => 'in',
            'payment_type' => 'customer_receipt',
            'payment_date' => now()->toDateString(),
            'amount' => 18000,
            'method' => 'orange_money',
            'reference' => 'OM-AUTO-002',
            'notes' => 'Orange rapproche Bamako',
            'created_by' => $user->id,
        ]);

        $reconciledPayment = Payment::query()->where('company_id', $user->company_id)->where('payment_number', 'ENC-MM-AUTO-002')->firstOrFail();
        $reconciliation = TreasuryReconciliation::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $orangeAccount->id,
            'reconciliation_number' => 'RAP-MM-AUTO-001',
            'statement_date' => now()->toDateString(),
            'statement_reference' => 'AUTO-STATEMENT-001',
            'statement_balance' => 18000,
            'matched_total' => 18000,
            'book_balance' => 18000,
            'difference' => 0,
            'payments_count' => 1,
            'status' => 'balanced',
            'notes' => 'Rapprochement test automation',
            'created_by' => $user->id,
        ]);
        TreasuryReconciliationPayment::query()->create([
            'treasury_reconciliation_id' => $reconciliation->id,
            'payment_id' => $reconciledPayment->id,
        ]);

        Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $sikasso->id,
            'cash_account_id' => $sikassoWave->id,
            'partner_id' => null,
            'payment_number' => 'ENC-MM-AUTO-003',
            'direction' => 'in',
            'payment_type' => 'customer_receipt',
            'payment_date' => now()->toDateString(),
            'amount' => 31000,
            'method' => 'wave',
            'reference' => null,
            'notes' => 'Wave non rapproche Sikasso',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->withSession($this->workspaceSession($user));

        $this->post(route('automation.store'), [
            'name' => 'Veille mobile money Bamako',
            'signal_key' => 'treasury.unreconciled_mobile_money',
            'status' => 'active',
            'severity' => 'warning',
            'action_type' => 'internal_alert',
            'threshold_value' => 1,
            'window_hours' => 48,
            'cooldown_minutes' => 60,
            'branch_id' => $user->branch_id,
            'description' => 'Suivre les flux mobile money encore ouverts sur l agence pilote.',
        ])->assertRedirect(route('automation.index'));

        $rule = AutomationRule::query()
            ->where('company_id', $user->company_id)
            ->where('name', 'Veille mobile money Bamako')
            ->firstOrFail();

        $this->post(route('automation.run', $rule))
            ->assertRedirect(route('automation.index'));

        $this->assertDatabaseHas('automation_executions', [
            'company_id' => $user->company_id,
            'automation_rule_id' => $rule->id,
            'matched' => true,
            'observed_value' => 1,
        ]);
        $this->assertDatabaseHas('internal_notifications', [
            'company_id' => $user->company_id,
            'code' => 'automation-rule-'.$rule->id,
            'type' => 'automation',
        ]);

        $notification = InternalNotification::query()
            ->where('company_id', $user->company_id)
            ->where('code', 'automation-rule-'.$rule->id)
            ->firstOrFail();

        $this->assertSame($rule->id, $notification->meta['rule_id'] ?? null);
        $this->assertStringContainsString('1 flux mobile money restent a rapprocher', (string) $notification->message);
        $this->assertStringContainsString('25 000 XOF', (string) $notification->message);
        $this->assertStringContainsString('1 sans reference', (string) $notification->message);

        $this->get(route('automation.index'))
            ->assertOk()
            ->assertSee('Mobile money a rapprocher')
            ->assertSee('Veille mobile money Bamako');
    }

    public function test_manager_can_create_and_run_pending_internal_transfer_deposit_rule(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $bankAccount = CashAccount::query()->where('company_id', $user->company_id)->where('name', 'Banque BDM')->firstOrFail();
        $sikasso = Branch::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'name' => 'Agence Sikasso depot test',
            'code' => 'SIK-TDEP',
            'city' => 'Sikasso',
            'address' => 'Zone depot test',
            'is_active' => true,
            'is_default' => false,
        ]);

        Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $bankAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-DEP-AUTO-001',
            'direction' => 'in',
            'payment_type' => 'internal_transfer',
            'payment_date' => now()->subDays(3)->toDateString(),
            'amount' => 64000,
            'method' => 'bank_transfer',
            'reference' => null,
            'notes' => 'Depot Bamako ancien non rapproche',
            'created_by' => $user->id,
        ]);

        Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $bankAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-DEP-AUTO-002',
            'direction' => 'in',
            'payment_type' => 'internal_transfer',
            'payment_date' => now()->toDateString(),
            'amount' => 12000,
            'method' => 'bank_transfer',
            'reference' => 'BORD-AUTO-002',
            'notes' => 'Depot Bamako recent',
            'created_by' => $user->id,
        ]);

        $reconciledPayment = Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $bankAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-DEP-AUTO-003',
            'direction' => 'in',
            'payment_type' => 'internal_transfer',
            'payment_date' => now()->subDays(4)->toDateString(),
            'amount' => 21000,
            'method' => 'bank_transfer',
            'reference' => 'BORD-AUTO-003',
            'notes' => 'Depot Bamako rapproche',
            'created_by' => $user->id,
        ]);

        $reconciliation = TreasuryReconciliation::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $bankAccount->id,
            'reconciliation_number' => 'RAP-DEP-AUTO-001',
            'statement_date' => now()->toDateString(),
            'statement_reference' => 'BANK-STATEMENT-DEPOT-001',
            'statement_balance' => 21000,
            'matched_total' => 21000,
            'book_balance' => 21000,
            'difference' => 0,
            'payments_count' => 1,
            'status' => 'balanced',
            'notes' => 'Rapprochement depot automation',
            'created_by' => $user->id,
        ]);
        TreasuryReconciliationPayment::query()->create([
            'treasury_reconciliation_id' => $reconciliation->id,
            'payment_id' => $reconciledPayment->id,
        ]);

        Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $sikasso->id,
            'cash_account_id' => $bankAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-DEP-AUTO-004',
            'direction' => 'in',
            'payment_type' => 'internal_transfer',
            'payment_date' => now()->subDays(5)->toDateString(),
            'amount' => 51000,
            'method' => 'bank_transfer',
            'reference' => 'BORD-AUTO-004',
            'notes' => 'Depot Sikasso ancien non rapproche',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->withSession($this->workspaceSession($user));

        $this->post(route('automation.store'), [
            'name' => 'Veille depots Bamako',
            'signal_key' => 'treasury.pending_internal_transfer_deposits',
            'status' => 'active',
            'severity' => 'danger',
            'action_type' => 'internal_alert',
            'threshold_value' => 1,
            'window_hours' => 48,
            'cooldown_minutes' => 60,
            'branch_id' => $user->branch_id,
            'description' => 'Surveiller les depots agence encore absents du releve bancaire.',
        ])->assertRedirect(route('automation.index'));

        $rule = AutomationRule::query()
            ->where('company_id', $user->company_id)
            ->where('name', 'Veille depots Bamako')
            ->firstOrFail();

        $this->post(route('automation.run', $rule))
            ->assertRedirect(route('automation.index'));

        $this->assertDatabaseHas('automation_executions', [
            'company_id' => $user->company_id,
            'automation_rule_id' => $rule->id,
            'matched' => true,
            'observed_value' => 1,
        ]);
        $this->assertDatabaseHas('internal_notifications', [
            'company_id' => $user->company_id,
            'code' => 'automation-rule-'.$rule->id,
            'type' => 'automation',
        ]);

        $notification = InternalNotification::query()
            ->where('company_id', $user->company_id)
            ->where('code', 'automation-rule-'.$rule->id)
            ->firstOrFail();

        $this->assertSame($rule->id, $notification->meta['rule_id'] ?? null);
        $this->assertStringContainsString('1 versement(s) agence attendent encore confirmation', (string) $notification->message);
        $this->assertStringContainsString('64 000 XOF', (string) $notification->message);
        $this->assertStringContainsString('1 sans bordereau exploitable', (string) $notification->message);
        $this->assertStringContainsString('Plus ancien depot', (string) $notification->message);

        $this->get(route('automation.index'))
            ->assertOk()
            ->assertSee('Versements agence a confirmer')
            ->assertSee('Veille depots Bamako');
    }

    public function test_pending_internal_transfer_rule_ignores_missing_proof_phrase_when_attachment_exists(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $bankAccount = CashAccount::query()->where('company_id', $user->company_id)->where('name', 'Banque BDM')->firstOrFail();

        $payment = Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $bankAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-DEP-AUTO-005',
            'direction' => 'in',
            'payment_type' => 'internal_transfer',
            'payment_date' => now()->subDays(3)->toDateString(),
            'amount' => 58000,
            'method' => 'bank_transfer',
            'reference' => null,
            'notes' => 'Depot avec justificatif joint',
            'created_by' => $user->id,
        ]);

        Attachment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'attachable_type' => Payment::class,
            'attachable_id' => $payment->id,
            'disk' => 'public',
            'path' => 'tests/bordereau-auto.pdf',
            'original_name' => 'bordereau-auto.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 4096,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->withSession($this->workspaceSession($user));

        $this->post(route('automation.store'), [
            'name' => 'Veille depots avec justificatif',
            'signal_key' => 'treasury.pending_internal_transfer_deposits',
            'status' => 'active',
            'severity' => 'warning',
            'action_type' => 'internal_alert',
            'threshold_value' => 1,
            'window_hours' => 48,
            'cooldown_minutes' => 60,
            'branch_id' => $user->branch_id,
            'description' => 'Suivre les depots agences anciens meme quand le bordereau est joint.',
        ])->assertRedirect(route('automation.index'));

        $rule = AutomationRule::query()
            ->where('company_id', $user->company_id)
            ->where('name', 'Veille depots avec justificatif')
            ->firstOrFail();

        $this->post(route('automation.run', $rule))
            ->assertRedirect(route('automation.index'));

        $notification = InternalNotification::query()
            ->where('company_id', $user->company_id)
            ->where('code', 'automation-rule-'.$rule->id)
            ->firstOrFail();

        $this->assertStringContainsString('1 versement(s) agence attendent encore confirmation', (string) $notification->message);
        $this->assertStringContainsString('58 000 XOF', (string) $notification->message);
        $this->assertStringNotContainsString('sans bordereau exploitable', (string) $notification->message);
    }

    public function test_manager_can_create_and_run_documented_internal_transfer_deposit_rule(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $bankAccount = CashAccount::query()->where('company_id', $user->company_id)->where('name', 'Banque BDM')->firstOrFail();
        $sikasso = Branch::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'name' => 'Agence Sikasso depot documente',
            'code' => 'SIK-TDOC',
            'city' => 'Sikasso',
            'address' => 'Zone depot documente',
            'is_active' => true,
            'is_default' => false,
        ]);
        $sikassoBank = CashAccount::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $sikasso->id,
            'name' => 'Banque Sikasso test',
            'type' => 'bank',
            'account_number' => '22381111',
            'opening_balance' => 0,
            'is_active' => true,
        ]);

        Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $bankAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-DEP-DOC-AUTO-001',
            'direction' => 'in',
            'payment_type' => 'internal_transfer',
            'payment_date' => now()->subDays(3)->toDateString(),
            'amount' => 42000,
            'method' => 'bank_transfer',
            'reference' => 'BORD-DOC-AUTO-001',
            'notes' => 'Depot documente par reference Bamako',
            'created_by' => $user->id,
        ]);

        $paymentWithAttachment = Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $bankAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-DEP-DOC-AUTO-002',
            'direction' => 'in',
            'payment_type' => 'internal_transfer',
            'payment_date' => now()->subDays(2)->toDateString(),
            'amount' => 37000,
            'method' => 'bank_transfer',
            'reference' => null,
            'notes' => 'Depot documente par piece jointe Bamako',
            'created_by' => $user->id,
        ]);

        Attachment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'attachable_type' => Payment::class,
            'attachable_id' => $paymentWithAttachment->id,
            'disk' => 'public',
            'path' => 'tests/bordereau-documented-auto.pdf',
            'original_name' => 'bordereau-documented-auto.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 4096,
            'created_by' => $user->id,
        ]);

        $reconciledPayment = Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $bankAccount->id,
            'partner_id' => null,
            'payment_number' => 'ENC-DEP-DOC-AUTO-003',
            'direction' => 'in',
            'payment_type' => 'internal_transfer',
            'payment_date' => now()->subDays(4)->toDateString(),
            'amount' => 18000,
            'method' => 'bank_transfer',
            'reference' => 'BORD-DOC-AUTO-003',
            'notes' => 'Depot documente deja rapproche',
            'created_by' => $user->id,
        ]);

        $reconciliation = TreasuryReconciliation::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $bankAccount->id,
            'reconciliation_number' => 'RAP-DEP-DOC-AUTO-001',
            'statement_date' => now()->toDateString(),
            'statement_reference' => 'BANK-STATEMENT-DOC-001',
            'statement_balance' => 18000,
            'matched_total' => 18000,
            'book_balance' => 18000,
            'difference' => 0,
            'payments_count' => 1,
            'status' => 'balanced',
            'notes' => 'Rapprochement depot documente automation',
            'created_by' => $user->id,
        ]);
        TreasuryReconciliationPayment::query()->create([
            'treasury_reconciliation_id' => $reconciliation->id,
            'payment_id' => $reconciledPayment->id,
        ]);

        Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $sikasso->id,
            'cash_account_id' => $sikassoBank->id,
            'partner_id' => null,
            'payment_number' => 'ENC-DEP-DOC-AUTO-004',
            'direction' => 'in',
            'payment_type' => 'internal_transfer',
            'payment_date' => now()->subDays(3)->toDateString(),
            'amount' => 29000,
            'method' => 'bank_transfer',
            'reference' => 'BORD-DOC-AUTO-004',
            'notes' => 'Depot documente Sikasso hors scope',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->withSession($this->workspaceSession($user));

        $this->post(route('automation.store'), [
            'name' => 'Rapprochement depots documentes Bamako',
            'signal_key' => 'treasury.documented_internal_transfer_deposits',
            'status' => 'active',
            'severity' => 'warning',
            'action_type' => 'internal_alert',
            'threshold_value' => 1,
            'window_hours' => 24,
            'cooldown_minutes' => 60,
            'branch_id' => $user->branch_id,
            'description' => 'Suivre les depots deja justifies mais encore absents du rapprochement.',
        ])->assertRedirect(route('automation.index'));

        $rule = AutomationRule::query()
            ->where('company_id', $user->company_id)
            ->where('name', 'Rapprochement depots documentes Bamako')
            ->firstOrFail();

        $this->post(route('automation.run', $rule))
            ->assertRedirect(route('automation.index'));

        $this->assertDatabaseHas('automation_executions', [
            'company_id' => $user->company_id,
            'automation_rule_id' => $rule->id,
            'matched' => true,
            'observed_value' => 2,
        ]);

        $notification = InternalNotification::query()
            ->where('company_id', $user->company_id)
            ->where('code', 'automation-rule-'.$rule->id)
            ->firstOrFail();

        $this->assertStringContainsString('2 versement(s) documente(s) attendent encore rapprochement', (string) $notification->message);
        $this->assertStringContainsString('79 000 XOF', (string) $notification->message);
        $this->assertStringContainsString('1 avec reference exploitable', (string) $notification->message);
        $this->assertStringContainsString('1 via justificatif joint', (string) $notification->message);

        $this->get(route('automation.index'))
            ->assertOk()
            ->assertSee('Versements documentes a rapprocher')
            ->assertSee('Rapprochement depots documentes Bamako');
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_tenant_id' => $user->tenant_id,
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
