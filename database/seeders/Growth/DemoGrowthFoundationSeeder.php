<?php

namespace Database\Seeders\Growth;

use App\Models\User;
use App\Modules\Commerce\Models\CommerceChannelAction;
use App\Modules\Commerce\Models\CommerceChannel;
use App\Modules\Commerce\Models\CommerceChannelSnapshot;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Hr\Models\HrDepartment;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrLeaveRequest;
use App\Modules\Manufacturing\Models\ManufacturingBom;
use App\Modules\Manufacturing\Models\ProductionOrder;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\PayrollSlip;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectTask;
use Illuminate\Database\Seeder;

class DemoGrowthFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('name', 'Nema Distribution')->firstOrFail();
        $branch = Branch::query()->where('company_id', $company->id)->where('code', 'BKO')->firstOrFail();
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $retailDepartment = HrDepartment::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'DEP-0001'],
            [
                'branch_id' => $branch->id,
                'name' => 'Operations retail',
                'manager_name' => 'Chef reseau Bamako',
                'headcount_target' => 18,
                'status' => 'active',
                'notes' => 'Equipe magasin, supervision terrain et service client.',
                'created_by' => $manager->id,
                'updated_by' => $manager->id,
            ]
        );

        $logisticsDepartment = HrDepartment::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'DEP-0002'],
            [
                'branch_id' => $branch->id,
                'name' => 'Logistique et stock',
                'manager_name' => 'Coordinateur depot',
                'headcount_target' => 10,
                'status' => 'scaling',
                'notes' => 'Equipe magasin central, approvisionnements et mise en rayon.',
                'created_by' => $manager->id,
                'updated_by' => $manager->id,
            ]
        );

        $awa = HrEmployee::query()->updateOrCreate(
            ['company_id' => $company->id, 'employee_number' => 'EMP-2026-00001'],
            [
                'branch_id' => $branch->id,
                'department_id' => $retailDepartment->id,
                'full_name' => 'Awa Diallo',
                'email' => 'awa.diallo@nema-erp.test',
                'phone' => '+22370010001',
                'job_title' => 'Responsable caisse et experience client',
                'contract_type' => 'permanent',
                'hire_date' => now()->subMonths(10)->toDateString(),
                'status' => 'active',
                'payroll_cycle' => 'monthly',
                'base_salary' => 325000,
                'notes' => 'Pilote le front office et la formation des caissiers.',
                'created_by' => $manager->id,
                'updated_by' => $manager->id,
            ]
        );

        $mamadou = HrEmployee::query()->updateOrCreate(
            ['company_id' => $company->id, 'employee_number' => 'EMP-2026-00002'],
            [
                'branch_id' => $branch->id,
                'department_id' => $logisticsDepartment->id,
                'full_name' => 'Mamadou Coulibaly',
                'email' => 'mamadou.coulibaly@nema-erp.test',
                'phone' => '+22370010002',
                'job_title' => 'Superviseur depot principal',
                'contract_type' => 'permanent',
                'hire_date' => now()->subMonths(7)->toDateString(),
                'status' => 'active',
                'payroll_cycle' => 'monthly',
                'base_salary' => 285000,
                'notes' => 'Suit les receptions, inventaires et reappro automatiques.',
                'created_by' => $manager->id,
                'updated_by' => $manager->id,
            ]
        );

        $payrollRun = PayrollRun::query()->updateOrCreate(
            ['company_id' => $company->id, 'run_number' => 'PAY-'.now()->format('Y').'-0001'],
            [
                'branch_id' => $branch->id,
                'label' => 'Paie pilote Avril '.now()->year,
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
                'scheduled_pay_date' => now()->endOfMonth()->toDateString(),
                'headcount' => 2,
                'gross_amount' => 760000,
                'net_amount' => 610000,
                'status' => 'review',
                'notes' => 'Simulation de paie pour le perimetre de demonstration.',
                'created_by' => $manager->id,
                'updated_by' => $manager->id,
            ]
        );

        HrLeaveRequest::query()->updateOrCreate(
            ['company_id' => $company->id, 'leave_number' => 'CONGE-'.now()->format('Y').'-0001'],
            [
                'branch_id' => $branch->id,
                'employee_id' => $awa->id,
                'leave_type' => 'annual',
                'start_date' => now()->addDays(3)->toDateString(),
                'end_date' => now()->addDays(5)->toDateString(),
                'total_days' => 3,
                'status' => 'approved',
                'coverage_plan' => 'Rotation caisse assuree par equipe retail BKO 2.',
                'notes' => 'Conge valide avec passation de caisse et suivi WhatsApp.',
                'created_by' => $manager->id,
                'updated_by' => $manager->id,
            ]
        );

        $payrollSlip = PayrollSlip::query()->updateOrCreate(
            ['company_id' => $company->id, 'slip_number' => 'BUL-'.now()->format('Y').'-00001'],
            [
                'branch_id' => $branch->id,
                'payroll_run_id' => $payrollRun->id,
                'employee_id' => $awa->id,
                'base_salary' => 325000,
                'gross_amount' => 365000,
                'deductions_amount' => 42000,
                'employer_contributions_amount' => 58500,
                'net_amount' => 323000,
                'status' => 'review',
                'payout_mode' => 'bank',
                'notes' => 'Bulletin pilote avec prime performance et retenues standards.',
                'created_by' => $manager->id,
                'updated_by' => $manager->id,
            ]
        );

        $payrollSlip->lines()->delete();
        $payrollSlip->lines()->createMany([
            ['line_type' => 'earning', 'code' => 'SALAIRE_BASE', 'label' => 'Salaire de base', 'amount' => 325000, 'sequence' => 1],
            ['line_type' => 'earning', 'code' => 'PRIMES', 'label' => 'Prime performance retail', 'amount' => 40000, 'sequence' => 2],
            ['line_type' => 'deduction', 'code' => 'RETENUES', 'label' => 'Retenues salariales', 'amount' => 42000, 'sequence' => 3],
            ['line_type' => 'employer_charge', 'code' => 'CHARGES_PATRONALES', 'label' => 'Charges patronales', 'amount' => 58500, 'sequence' => 4],
            ['line_type' => 'net', 'code' => 'NET_A_PAYER', 'label' => 'Net a payer', 'amount' => 323000, 'sequence' => 5],
        ]);

        $project = Project::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'PRJ-'.now()->format('Y').'-0001'],
            [
                'branch_id' => $branch->id,
                'name' => 'Ouverture canal B2B Mopti',
                'customer_name' => 'Reseau Delta Market',
                'owner_id' => $manager->id,
                'start_date' => now()->subWeeks(2)->toDateString(),
                'target_end_date' => now()->addWeeks(6)->toDateString(),
                'status' => 'active',
                'progress' => 35,
                'budget_amount' => 4800000,
                'notes' => 'Projet de lancement commercial avec stock tampon et animation terrain.',
                'created_by' => $manager->id,
                'updated_by' => $manager->id,
            ]
        );

        ProjectTask::query()->updateOrCreate(
            ['company_id' => $company->id, 'project_id' => $project->id, 'title' => 'Valider le cadrage commercial Mopti'],
            [
                'owner_id' => $manager->id,
                'item_type' => 'milestone',
                'status' => 'done',
                'priority' => 'high',
                'progress' => 100,
                'due_date' => now()->subWeek()->toDateString(),
                'completed_at' => now()->subDays(6),
                'notes' => 'Kick-off valide avec plan de comptes cle et objectifs du trimestre.',
                'created_by' => $manager->id,
                'updated_by' => $manager->id,
            ]
        );

        ProjectTask::query()->updateOrCreate(
            ['company_id' => $company->id, 'project_id' => $project->id, 'title' => 'Negocier les volumes d ouverture avec Delta Market'],
            [
                'owner_id' => $manager->id,
                'item_type' => 'task',
                'status' => 'in_progress',
                'priority' => 'critical',
                'progress' => 55,
                'due_date' => now()->addDays(5)->toDateString(),
                'completed_at' => null,
                'notes' => 'Pack volume + remise de lancement en cours de validation.',
                'created_by' => $manager->id,
                'updated_by' => $manager->id,
            ]
        );

        ProjectTask::query()->updateOrCreate(
            ['company_id' => $company->id, 'project_id' => $project->id, 'title' => 'Securiser le stock tampon Mopti'],
            [
                'owner_id' => $mamadou->id,
                'item_type' => 'task',
                'status' => 'blocked',
                'priority' => 'high',
                'progress' => 25,
                'due_date' => now()->subDays(2)->toDateString(),
                'completed_at' => null,
                'notes' => 'Blocage transport inter-agence en attente de validation logistique.',
                'created_by' => $manager->id,
                'updated_by' => $manager->id,
            ]
        );

        ProjectTask::query()->updateOrCreate(
            ['company_id' => $company->id, 'project_id' => $project->id, 'title' => 'Lancer le pilote terrain Mopti'],
            [
                'owner_id' => $awa->id,
                'item_type' => 'milestone',
                'status' => 'todo',
                'priority' => 'normal',
                'progress' => 0,
                'due_date' => now()->addDays(6)->toDateString(),
                'completed_at' => null,
                'notes' => 'Animation commerciale, check caisse et parcours de prise de commande.',
                'created_by' => $manager->id,
                'updated_by' => $manager->id,
            ]
        );

        $bom = ManufacturingBom::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'BOM-'.now()->format('Y').'-0001'],
            [
                'branch_id' => $branch->id,
                'item_name' => 'Kit promo Ramadan',
                'output_quantity' => 1,
                'status' => 'active',
                'notes' => 'Nomenclature standard pour kit retail Ramadan.',
                'created_by' => $manager->id,
                'updated_by' => $manager->id,
            ]
        );

        $bom->lines()->delete();
        $bom->lines()->createMany([
            ['component_code' => 'SKU-HUILE-1L', 'component_name' => 'Huile 1L', 'quantity' => 2, 'unit' => 'u', 'wastage_rate' => 0, 'sequence' => 1],
            ['component_code' => 'SKU-SUCRE-1KG', 'component_name' => 'Sucre 1kg', 'quantity' => 2, 'unit' => 'u', 'wastage_rate' => 1.5, 'sequence' => 2],
            ['component_code' => 'EMB-CARTON', 'component_name' => 'Carton kraft', 'quantity' => 1, 'unit' => 'u', 'wastage_rate' => 0, 'sequence' => 3],
            ['component_code' => 'EMB-ETIQ', 'component_name' => 'Etiquette promo', 'quantity' => 1, 'unit' => 'u', 'wastage_rate' => 0, 'sequence' => 4],
        ]);

        ProductionOrder::query()->updateOrCreate(
            ['company_id' => $company->id, 'order_number' => 'OF-'.now()->format('Y').'-0001'],
            [
                'branch_id' => $branch->id,
                'reference' => 'KIT-RAMADAN-01',
                'bill_of_material_id' => $bom->id,
                'item_name' => 'Kit promo Ramadan',
                'planned_quantity' => 500,
                'completed_quantity' => 180,
                'material_cost_estimate' => 2450000,
                'actual_material_cost' => 910000,
                'planned_start_date' => now()->subDays(5)->toDateString(),
                'due_date' => now()->addDays(4)->toDateString(),
                'status' => 'in_progress',
                'routing_stage' => 'packing',
                'notes' => 'Assemblage de paniers promo pour reseau retail et commandes WhatsApp.',
                'created_by' => $manager->id,
                'updated_by' => $manager->id,
            ]
        );

        $whatsAppChannel = CommerceChannel::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'CH-0001'],
            [
                'branch_id' => $branch->id,
                'name' => 'Boutique WhatsApp Bamako',
                'channel_type' => 'mobile',
                'status' => 'active',
                'connector_name' => 'WhatsApp Commerce',
                'settlement_mode' => 'mobile_money',
                'target_monthly_revenue' => 3500000,
                'notes' => 'Canal conversationnel pilote pour commandes et encaissements Wave.',
                'created_by' => $manager->id,
                'updated_by' => $manager->id,
            ]
        );

        $b2bChannel = CommerceChannel::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'CH-0002'],
            [
                'branch_id' => $branch->id,
                'name' => 'Retail grossiste Bamako',
                'channel_type' => 'b2b',
                'status' => 'active',
                'connector_name' => 'Back-office devis/commandes',
                'settlement_mode' => 'mixed',
                'target_monthly_revenue' => 12000000,
                'notes' => 'Canal comptes cle pour reseau semi-grossiste.',
                'created_by' => $manager->id,
                'updated_by' => $manager->id,
            ]
        );

        CommerceChannelSnapshot::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'commerce_channel_id' => $whatsAppChannel->id,
                'snapshot_date' => now()->toDateString(),
            ],
            [
                'gross_revenue' => 2825000,
                'orders_count' => 186,
                'average_order_value' => 15188,
                'conversion_rate' => 21.4,
                'service_level' => 92.5,
                'failed_orders_count' => 4,
                'failed_payments_count' => 2,
                'notes' => 'Traction correcte mais incidents de paiement a reduire sur les pointes du soir.',
                'created_by' => $manager->id,
                'updated_by' => $manager->id,
            ]
        );

        CommerceChannelSnapshot::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'commerce_channel_id' => $b2bChannel->id,
                'snapshot_date' => now()->toDateString(),
            ],
            [
                'gross_revenue' => 9150000,
                'orders_count' => 34,
                'average_order_value' => 269118,
                'conversion_rate' => 48.2,
                'service_level' => 97.8,
                'failed_orders_count' => 1,
                'failed_payments_count' => 0,
                'notes' => 'Canal comptes cle solide avec marge de progression sur couverture de portefeuille.',
                'created_by' => $manager->id,
                'updated_by' => $manager->id,
            ]
        );

        CommerceChannelAction::query()->updateOrCreate(
            ['company_id' => $company->id, 'commerce_channel_id' => $whatsAppChannel->id, 'title' => 'Corriger les echecs Wave sur commandes soir'],
            [
                'owner_id' => $awa->id,
                'action_type' => 'payment',
                'status' => 'in_progress',
                'impact_level' => 'critical',
                'due_date' => now()->addDays(3)->toDateString(),
                'completed_at' => null,
                'notes' => 'Verifier la sequence callback et relancer les paniers en abandon a chaud.',
                'created_by' => $manager->id,
                'updated_by' => $manager->id,
            ]
        );

        CommerceChannelAction::query()->updateOrCreate(
            ['company_id' => $company->id, 'commerce_channel_id' => $whatsAppChannel->id, 'title' => 'Nettoyer le catalogue promo WhatsApp'],
            [
                'owner_id' => $manager->id,
                'action_type' => 'catalog',
                'status' => 'todo',
                'impact_level' => 'high',
                'due_date' => now()->addDays(5)->toDateString(),
                'completed_at' => null,
                'notes' => 'Retirer les doublons et pousser les packs retail a forte rotation.',
                'created_by' => $manager->id,
                'updated_by' => $manager->id,
            ]
        );

        CommerceChannelAction::query()->updateOrCreate(
            ['company_id' => $company->id, 'commerce_channel_id' => $b2bChannel->id, 'title' => 'Activer le plan de relance grossistes dormants'],
            [
                'owner_id' => $manager->id,
                'action_type' => 'campaign',
                'status' => 'done',
                'impact_level' => 'normal',
                'due_date' => now()->subDays(4)->toDateString(),
                'completed_at' => now()->subDays(1),
                'notes' => 'Script de relance termine avec offers de reactivation appliquees.',
                'created_by' => $manager->id,
                'updated_by' => $manager->id,
            ]
        );
    }
}
