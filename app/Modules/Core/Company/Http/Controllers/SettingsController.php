<?php

namespace App\Modules\Core\Company\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Core\Approvals\Services\ApprovalSettingsService;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\DocumentSequence;
use App\Modules\Core\Company\Models\PaymentTerm;
use App\Modules\Core\Company\Models\PriceList;
use App\Modules\Core\Company\Models\PriceListItem;
use App\Modules\Core\Company\Models\Setting;
use App\Modules\Core\Company\Models\TaxRule;
use App\Modules\Core\Company\Services\SectorProfileService;
use App\Modules\Core\Integrations\Models\ApiToken;
use App\Modules\Core\Integrations\Services\IntegrationOutboxService;
use App\Modules\Catalog\Models\Product;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Services\PaymentGatewayService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly ApprovalSettingsService $approvalSettingsService,
        private readonly IntegrationOutboxService $integrationOutboxService,
        private readonly PaymentGatewayService $paymentGatewayService,
        private readonly SectorProfileService $sectorProfileService,
    ) {
    }

    public function index(CurrentWorkspace $workspace): View|RedirectResponse
    {
        $company = $workspace->company();

        if (! $company) {
            return redirect()->route('companies.index')->with('error', 'Creez d abord une entreprise pour acceder aux parametres.');
        }

        $this->ensureDefaultSequences($company->id);
        $branches = Branch::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
        $approvalUsers = User::query()
            ->with(['branch:id,name', 'roles.permissions'])
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('settings.index', [
            'company' => $company,
            'general' => Setting::query()->firstOrCreate(
                ['company_id' => $company->id, 'key' => 'general'],
                ['value' => ['country' => 'Mali', 'timezone' => 'Africa/Bamako', 'locale' => 'fr']]
            ),
            'sequences' => DocumentSequence::query()->where('company_id', $company->id)->orderBy('document_type')->get(),
            'approvalWorkflows' => $this->approvalSettingsService->workflowForCompany($company->id),
            'approvalAssignees' => [
                'sales' => [
                    'step1' => $approvalUsers->filter(fn (User $user) => $this->approvalSettingsService->canUserBeAssigned($user, 'sales', 1))->values(),
                    'step2' => $approvalUsers->filter(fn (User $user) => $this->approvalSettingsService->canUserBeAssigned($user, 'sales', 2))->values(),
                ],
                'purchases' => [
                    'step1' => $approvalUsers->filter(fn (User $user) => $this->approvalSettingsService->canUserBeAssigned($user, 'purchases', 1))->values(),
                    'step2' => $approvalUsers->filter(fn (User $user) => $this->approvalSettingsService->canUserBeAssigned($user, 'purchases', 2))->values(),
                ],
                'expenses' => [
                    'step1' => $approvalUsers->filter(fn (User $user) => $this->approvalSettingsService->canUserBeAssigned($user, 'expenses', 1))->values(),
                    'step2' => $approvalUsers->filter(fn (User $user) => $this->approvalSettingsService->canUserBeAssigned($user, 'expenses', 2))->values(),
                ],
            ],
            'branches' => $branches,
            'approvalNotificationChannels' => $this->approvalSettingsService->notificationChannelsForCompany($company->id),
            'paymentTerms' => PaymentTerm::query()->where('company_id', $company->id)->orderByDesc('is_default')->orderBy('days')->get(),
            'priceLists' => PriceList::query()->with(['items.product'])->where('company_id', $company->id)->orderByDesc('is_default')->orderBy('name')->get(),
            'taxRules' => TaxRule::query()->where('company_id', $company->id)->orderByDesc('is_default_sales')->orderBy('name')->get(),
            'products' => Product::query()->where('company_id', $company->id)->where('is_active', true)->orderBy('name')->get(),
            'apiTokens' => ApiToken::query()->where('company_id', $company->id)->latest()->get(),
            'integrationWebhook' => $this->integrationOutboxService->configurationForCompany($company->id),
            'paymentGateways' => $this->paymentGatewayService->configurationForCompany($company->id),
            'cashAccounts' => CashAccount::query()->where('company_id', $company->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'sectorProfiles' => $this->sectorProfileService->profiles(),
            'sectorProfile' => $this->sectorProfileService->profileForCompany($company->id),
        ]);
    }

    public function updateCompany(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $company = $workspace->company();
        abort_if(! $company, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'nif' => ['nullable', 'string', 'max:100', 'unique:companies,nif,'.$company->id],
            'rccm' => ['nullable', 'string', 'max:100', 'unique:companies,rccm,'.$company->id],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'currency_code' => ['required', 'string', 'size:3'],
            'country' => ['required', 'string', 'max:100'],
            'timezone' => ['required', 'string', 'max:100'],
            'locale' => ['required', 'string', 'max:10'],
        ]);

        $company->update([
            'name' => $data['name'],
            'legal_name' => $data['legal_name'] ?? null,
            'nif' => $data['nif'] ?? null,
            'rccm' => $data['rccm'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'currency_code' => strtoupper($data['currency_code']),
        ]);

        Setting::query()->updateOrCreate(
            ['company_id' => $company->id, 'key' => 'general'],
            ['value' => [
                'country' => $data['country'],
                'timezone' => $data['timezone'],
                'locale' => $data['locale'],
            ]]
        );

        $this->activityLogger->log('settings.company.update', 'Mise a jour parametres societe', $company);

        return redirect()->route('settings.index')->with('success', 'Parametres societe mis a jour.');
    }
    public function updateSectorProfile(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $company = $workspace->company();
        abort_if(! $company, 403);

        $data = $request->validate([
            'sector_profile' => ['required', Rule::in($this->sectorProfileService->keys())],
        ]);

        $this->sectorProfileService->updateProfile($company->id, $company->tenant_id, $data['sector_profile']);

        $profile = $this->sectorProfileService->profileForCompany($company->id);

        $this->activityLogger->log('settings.sector_profile.update', 'Mise a jour profil secteur', $company, [
            'sector_profile' => $profile['key'],
            'sector_label' => $profile['label'],
        ]);

        return redirect()->route('settings.index')->with('success', 'Profil secteur mis a jour.');
    }

    public function updateSequences(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $company = $workspace->company();
        abort_if(! $company, 403);

        $data = $request->validate([
            'sequences' => ['required', 'array', 'min:1'],
            'sequences.*.id' => ['required', 'integer', 'exists:document_sequences,id'],
            'sequences.*.prefix' => ['required', 'string', 'max:20'],
            'sequences.*.next_number' => ['required', 'integer', 'min:1'],
            'sequences.*.padding' => ['required', 'integer', 'min:3', 'max:10'],
        ]);

        foreach ($data['sequences'] as $sequenceData) {
            $sequence = DocumentSequence::query()
                ->where('company_id', $company->id)
                ->findOrFail($sequenceData['id']);

            $sequence->update([
                'prefix' => $sequenceData['prefix'],
                'next_number' => $sequenceData['next_number'],
                'padding' => $sequenceData['padding'],
            ]);
        }

        $this->activityLogger->log('settings.sequences.update', 'Mise a jour sequences documents', $company);

        return redirect()->route('settings.index')->with('success', 'Sequences de documents mises a jour.');
    }

    public function updateApprovalWorkflows(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $company = $workspace->company();
        abort_if(! $company, 403);

        $validator = Validator::make($request->all(), [
            'workflows' => ['required', 'array'],
            'workflows.sales.step2_threshold' => ['required', 'integer', 'min:0'],
            'workflows.sales.critical_threshold' => ['required', 'integer', 'min:0'],
            'workflows.sales.step1_sla_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'workflows.sales.step2_sla_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'workflows.sales.step1_assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $company->id)->where('is_active', true))],
            'workflows.sales.step2_assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $company->id)->where('is_active', true))],
            'workflows.sales.branch_assignments' => ['nullable', 'array'],
            'workflows.sales.branch_assignments.*.step1_assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $company->id)->where('is_active', true))],
            'workflows.sales.branch_assignments.*.step2_assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $company->id)->where('is_active', true))],
            'workflows.purchases.step2_threshold' => ['required', 'integer', 'min:0'],
            'workflows.purchases.critical_threshold' => ['required', 'integer', 'min:0'],
            'workflows.purchases.step1_sla_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'workflows.purchases.step2_sla_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'workflows.purchases.step1_assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $company->id)->where('is_active', true))],
            'workflows.purchases.step2_assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $company->id)->where('is_active', true))],
            'workflows.purchases.branch_assignments' => ['nullable', 'array'],
            'workflows.purchases.branch_assignments.*.step1_assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $company->id)->where('is_active', true))],
            'workflows.purchases.branch_assignments.*.step2_assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $company->id)->where('is_active', true))],
            'workflows.expenses.step2_threshold' => ['required', 'integer', 'min:0'],
            'workflows.expenses.critical_threshold' => ['required', 'integer', 'min:0'],
            'workflows.expenses.step1_sla_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'workflows.expenses.step2_sla_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'workflows.expenses.step1_assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $company->id)->where('is_active', true))],
            'workflows.expenses.step2_assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $company->id)->where('is_active', true))],
            'workflows.expenses.branch_assignments' => ['nullable', 'array'],
            'workflows.expenses.branch_assignments.*.step1_assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $company->id)->where('is_active', true))],
            'workflows.expenses.branch_assignments.*.step2_assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $company->id)->where('is_active', true))],
        ]);

        $validator->after(function ($validator) use ($company, $request): void {
            $availableUsers = User::query()
                ->with(['roles.permissions'])
                ->where('company_id', $company->id)
                ->where('is_active', true)
                ->get()
                ->keyBy('id');
            $branchIds = Branch::query()
                ->where('company_id', $company->id)
                ->pluck('id')
                ->map(fn (int $branchId) => (string) $branchId)
                ->all();

            foreach (['sales', 'purchases', 'expenses'] as $module) {
                $step2Threshold = (int) data_get($request->all(), 'workflows.'.$module.'.step2_threshold', 0);
                $criticalThreshold = (int) data_get($request->all(), 'workflows.'.$module.'.critical_threshold', 0);
                $step1Sla = (int) data_get($request->all(), 'workflows.'.$module.'.step1_sla_hours', 0);
                $step2Sla = (int) data_get($request->all(), 'workflows.'.$module.'.step2_sla_hours', 0);

                if ($criticalThreshold < $step2Threshold) {
                    $validator->errors()->add('workflows.'.$module.'.critical_threshold', 'Le seuil de direction obligatoire doit etre superieur ou egal au seuil de double validation.');
                }

                if ($step2Sla > $step1Sla) {
                    $validator->errors()->add('workflows.'.$module.'.step2_sla_hours', 'Le SLA de la deuxieme etape doit etre inferieur ou egal au SLA de la premiere etape.');
                }

                $this->validateWorkflowAssignee(
                    $validator,
                    $availableUsers,
                    $module,
                    1,
                    data_get($request->all(), 'workflows.'.$module.'.step1_assignee_id'),
                    'workflows.'.$module.'.step1_assignee_id'
                );
                $this->validateWorkflowAssignee(
                    $validator,
                    $availableUsers,
                    $module,
                    2,
                    data_get($request->all(), 'workflows.'.$module.'.step2_assignee_id'),
                    'workflows.'.$module.'.step2_assignee_id'
                );

                foreach ((array) data_get($request->all(), 'workflows.'.$module.'.branch_assignments', []) as $branchId => $assignment) {
                    if (! in_array((string) $branchId, $branchIds, true)) {
                        $validator->errors()->add('workflows.'.$module.'.branch_assignments.'.$branchId, 'Agence invalide pour le routage d approbation.');
                        continue;
                    }

                    $this->validateWorkflowAssignee(
                        $validator,
                        $availableUsers,
                        $module,
                        1,
                        $assignment['step1_assignee_id'] ?? null,
                        'workflows.'.$module.'.branch_assignments.'.$branchId.'.step1_assignee_id'
                    );
                    $this->validateWorkflowAssignee(
                        $validator,
                        $availableUsers,
                        $module,
                        2,
                        $assignment['step2_assignee_id'] ?? null,
                        'workflows.'.$module.'.branch_assignments.'.$branchId.'.step2_assignee_id'
                    );
                }
            }
        });

        $data = $validator->validate();

        $workflows = collect($data['workflows'])
            ->map(fn (array $module) => [
                'step2_threshold' => (int) $module['step2_threshold'],
                'critical_threshold' => (int) $module['critical_threshold'],
                'step1_sla_hours' => (int) $module['step1_sla_hours'],
                'step2_sla_hours' => (int) $module['step2_sla_hours'],
                'step1_assignee_id' => $this->nullableInteger($module['step1_assignee_id'] ?? null),
                'step2_assignee_id' => $this->nullableInteger($module['step2_assignee_id'] ?? null),
                'branch_assignments' => collect($module['branch_assignments'] ?? [])
                    ->map(fn (array $assignment) => [
                        'step1_assignee_id' => $this->nullableInteger($assignment['step1_assignee_id'] ?? null),
                        'step2_assignee_id' => $this->nullableInteger($assignment['step2_assignee_id'] ?? null),
                    ])
                    ->filter(fn (array $assignment) => $assignment['step1_assignee_id'] || $assignment['step2_assignee_id'])
                    ->all(),
            ])
            ->all();

        $this->approvalSettingsService->updateWorkflowForCompany($company->id, $workflows);
        $this->activityLogger->log('settings.approvals.update', 'Mise a jour seuils approbation', $company, ['workflows' => $workflows]);

        return redirect()->route('settings.index')->with('success', 'Workflow d approbation mis a jour.');
    }

    public function updateApprovalNotifications(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $company = $workspace->company();
        abort_if(! $company, 403);

        $data = $request->validate([
            'channels.email.copy_to' => ['nullable', 'string', 'max:500'],
            'channels.whatsapp.copy_to' => ['nullable', 'string', 'max:500'],
        ]);

        $channels = [
            'email' => [
                'enabled' => $request->boolean('channels.email.enabled'),
                'copy_to' => trim((string) data_get($data, 'channels.email.copy_to', '')),
            ],
            'whatsapp' => [
                'enabled' => $request->boolean('channels.whatsapp.enabled'),
                'copy_to' => trim((string) data_get($data, 'channels.whatsapp.copy_to', '')),
            ],
        ];

        $invalidEmails = collect($this->parseList($channels['email']['copy_to']))
            ->filter(fn (string $item) => filter_var($item, FILTER_VALIDATE_EMAIL) === false)
            ->values();

        if ($invalidEmails->isNotEmpty()) {
            throw ValidationException::withMessages([
                'channels.email.copy_to' => 'Adresse(s) email invalide(s) : '.$invalidEmails->implode(', '),
            ]);
        }

        $this->approvalSettingsService->updateNotificationChannelsForCompany($company->id, $channels);
        $this->activityLogger->log('settings.approval_notifications.update', 'Mise a jour canaux notifications approbation', $company, ['channels' => $channels]);

        return redirect()->route('settings.index')->with('success', 'Canaux de notification d approbation mis a jour.');
    }

    public function storePaymentTerm(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $company = $workspace->company();
        abort_if(! $company, 403);

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:30', Rule::unique('payment_terms', 'code')->where(fn ($query) => $query->where('company_id', $company->id))],
            'name' => ['required', 'string', 'max:255'],
            'days' => ['required', 'integer', 'min:0', 'max:365'],
            'description' => ['nullable', 'string'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('is_default')) {
            PaymentTerm::query()->where('company_id', $company->id)->update(['is_default' => false]);
        }

        PaymentTerm::query()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'code' => $data['code'] ?: app(\App\Modules\Core\Company\Services\DocumentNumberService::class)
                ->nextNumber($company->id, 'payment_term_code'),
            'name' => $data['name'],
            'days' => $data['days'],
            'description' => $data['description'] ?? null,
            'is_default' => $request->boolean('is_default'),
            'is_active' => true,
        ]);

        return redirect()->route('settings.index')->with('success', 'Condition de paiement ajoutee.');
    }

    public function storePriceList(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $company = $workspace->company();
        abort_if(! $company, 403);

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:30', Rule::unique('price_lists', 'code')->where(fn ($query) => $query->where('company_id', $company->id))],
            'name' => ['required', 'string', 'max:255'],
            'currency_code' => ['required', 'string', 'size:3'],
            'description' => ['nullable', 'string'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('is_default')) {
            PriceList::query()->where('company_id', $company->id)->update(['is_default' => false]);
        }

        PriceList::query()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'code' => $data['code'] ?: app(\App\Modules\Core\Company\Services\DocumentNumberService::class)
                ->nextNumber($company->id, 'price_list_code'),
            'name' => $data['name'],
            'currency_code' => strtoupper($data['currency_code']),
            'description' => $data['description'] ?? null,
            'is_default' => $request->boolean('is_default'),
            'is_active' => true,
        ]);

        return redirect()->route('settings.index')->with('success', 'Liste de prix ajoutee.');
    }

    public function storePriceListItem(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $company = $workspace->company();
        abort_if(! $company, 403);

        $data = $request->validate([
            'price_list_id' => ['required', Rule::exists('price_lists', 'id')->where(fn ($query) => $query->where('company_id', $company->id))],
            'product_id' => ['required', Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $company->id)->where('is_active', true))],
            'min_qty' => ['required', 'numeric', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        PriceListItem::query()->updateOrCreate(
            [
                'price_list_id' => $data['price_list_id'],
                'product_id' => $data['product_id'],
                'min_qty' => $data['min_qty'],
            ],
            [
                'tenant_id' => $company->tenant_id,
                'company_id' => $company->id,
                'price' => $data['price'],
            ]
        );

        return redirect()->route('settings.index')->with('success', 'Article ajoute a la liste de prix.');
    }

    public function storeTaxRule(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $company = $workspace->company();
        abort_if(! $company, 403);

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:30', Rule::unique('tax_rules', 'code')->where(fn ($query) => $query->where('company_id', $company->id))],
            'name' => ['required', 'string', 'max:255'],
            'scope' => ['required', Rule::in(['sales', 'purchases', 'both'])],
            'tax_kind' => ['required', Rule::in(['vat', 'withholding'])],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'collect_account_code' => ['nullable', 'string', 'max:20'],
            'deductible_account_code' => ['nullable', 'string', 'max:20'],
            'is_default_sales' => ['nullable', 'boolean'],
            'is_default_purchases' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('is_default_sales')) {
            TaxRule::query()->where('company_id', $company->id)->update(['is_default_sales' => false]);
        }

        if ($request->boolean('is_default_purchases')) {
            TaxRule::query()->where('company_id', $company->id)->update(['is_default_purchases' => false]);
        }

        TaxRule::query()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'code' => $data['code'] ?: app(\App\Modules\Core\Company\Services\DocumentNumberService::class)
                ->nextNumber($company->id, 'tax_rule_code'),
            'name' => $data['name'],
            'scope' => $data['scope'],
            'tax_kind' => $data['tax_kind'],
            'rate' => $data['rate'],
            'collect_account_code' => $data['collect_account_code'] ?: null,
            'deductible_account_code' => $data['deductible_account_code'] ?: null,
            'is_default_sales' => $request->boolean('is_default_sales'),
            'is_default_purchases' => $request->boolean('is_default_purchases'),
            'is_active' => true,
        ]);

        return redirect()->route('settings.index')->with('success', 'Regle fiscale ajoutee.');
    }

    public function updateIntegrationWebhook(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $company = $workspace->company();
        abort_if(! $company, 403);

        $data = $request->validate([
            'webhook.url' => ['nullable', 'url', 'max:500'],
            'webhook.secret' => ['nullable', 'string', 'max:255'],
            'webhook.timeout' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $configuration = [
            'enabled' => $request->boolean('webhook.enabled'),
            'url' => trim((string) data_get($data, 'webhook.url', '')),
            'secret' => trim((string) data_get($data, 'webhook.secret', '')),
            'timeout' => max((int) data_get($data, 'webhook.timeout', config('services.integrations.webhook_timeout', 10)), 1),
        ];

        if ($configuration['enabled'] && $configuration['url'] === '') {
            throw ValidationException::withMessages([
                'webhook.url' => 'Renseigne une URL webhook avant d activer la publication outbox.',
            ]);
        }

        $this->integrationOutboxService->updateConfiguration($company->id, $company->tenant_id, $configuration);
        $this->activityLogger->log('settings.integrations.webhook.update', 'Mise a jour webhook integrations', $company, [
            'enabled' => $configuration['enabled'],
            'url' => $configuration['url'],
            'timeout' => $configuration['timeout'],
        ]);

        return redirect()->route('settings.index')->with('success', 'Webhook integrations mis a jour.');
    }

    public function updatePaymentGateways(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $company = $workspace->company();
        abort_if(! $company, 403);

        $channelLabels = [
            'wave' => 'Wave',
            'orange_money' => 'Orange Money',
            'moov_money' => 'Moov Money',
            'bank_transfer' => 'Virement bancaire',
        ];

        $rules = [];
        foreach (array_keys($channelLabels) as $channel) {
            $rules['channels.'.$channel.'.label'] = ['nullable', 'string', 'max:120'];
            $rules['channels.'.$channel.'.account_name'] = ['nullable', 'string', 'max:160'];
            $rules['channels.'.$channel.'.collection_number'] = ['nullable', 'string', 'max:120'];
            $rules['channels.'.$channel.'.instructions'] = ['nullable', 'string', 'max:1000'];
            $rules['channels.'.$channel.'.cash_account_id'] = ['nullable', Rule::exists('cash_accounts', 'id')->where(fn ($query) => $query->where('company_id', $company->id))];
            $rules['channels.'.$channel.'.callback_secret'] = ['nullable', 'string', 'max:255'];
        }

        $data = $request->validate($rules);
        $configuration = [];

        foreach ($channelLabels as $channel => $defaultLabel) {
            $entry = [
                'label' => trim((string) data_get($data, 'channels.'.$channel.'.label', $defaultLabel)) ?: $defaultLabel,
                'enabled' => $request->boolean('channels.'.$channel.'.enabled'),
                'account_name' => trim((string) data_get($data, 'channels.'.$channel.'.account_name', '')),
                'collection_number' => trim((string) data_get($data, 'channels.'.$channel.'.collection_number', '')),
                'instructions' => trim((string) data_get($data, 'channels.'.$channel.'.instructions', '')),
                'cash_account_id' => data_get($data, 'channels.'.$channel.'.cash_account_id') ? (int) data_get($data, 'channels.'.$channel.'.cash_account_id') : null,
                'auto_record' => $request->boolean('channels.'.$channel.'.auto_record'),
                'callback_secret' => trim((string) data_get($data, 'channels.'.$channel.'.callback_secret', '')),
            ];

            if ($entry['enabled'] && $entry['account_name'] === '' && $entry['collection_number'] === '') {
                throw ValidationException::withMessages([
                    'channels.'.$channel.'.collection_number' => 'Renseigne au moins un numero ou un compte de collecte avant d activer '.$entry['label'].'.',
                ]);
            }

            if ($entry['auto_record'] && ! $entry['cash_account_id']) {
                throw ValidationException::withMessages([
                    'channels.'.$channel.'.cash_account_id' => 'Choisis un compte de tresorerie avant d activer l enregistrement automatique sur '.$entry['label'].'.',
                ]);
            }

            $configuration[$channel] = $entry;
        }

        $this->paymentGatewayService->updateConfiguration($company->id, $company->tenant_id, $configuration);
        $this->activityLogger->log('settings.payment_gateways.update', 'Mise a jour passerelles de paiement terrain', $company, [
            'channels' => collect($configuration)
                ->map(fn (array $channel) => [
                    'label' => $channel['label'],
                    'enabled' => $channel['enabled'],
                    'collection_number' => $channel['collection_number'],
                    'cash_account_id' => $channel['cash_account_id'],
                    'auto_record' => $channel['auto_record'],
                    'callback_ready' => filled($channel['callback_secret']),
                ])
                ->all(),
        ]);

        return redirect()->route('settings.index')->with('success', 'Passerelles de paiement mises a jour.');
    }
    public function createApiToken(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $company = $workspace->company();
        abort_if(! $company, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        $plainToken = 'nema_'.Str::random(48);

        ApiToken::query()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'name' => $data['name'],
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => $data['expires_at'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        return redirect()->route('settings.index')->with('success', 'Jeton API genere. Copie-le maintenant.')->with('generated_api_token', $plainToken);
    }

    public function revokeApiToken(ApiToken $apiToken, CurrentWorkspace $workspace): RedirectResponse
    {
        $company = $workspace->company();
        abort_if(! $company || $apiToken->company_id !== $company->id, 403);

        $apiToken->delete();

        return redirect()->route('settings.index')->with('success', 'Jeton API revoque.');
    }

    private function ensureDefaultSequences(int $companyId): void
    {
        $defaults = [
            ['document_type' => 'expense', 'prefix' => 'DEP-{BRANCH}-{YEAR}-', 'next_number' => 1, 'padding' => 5],
            ['document_type' => 'journal_entry', 'prefix' => 'JRN-{JOURNAL}-{YEAR}-', 'next_number' => 1, 'padding' => 5],
            ['document_type' => 'payment', 'prefix' => 'ENC-{BRANCH}-{YEAR}-', 'next_number' => 1, 'padding' => 5],
            ['document_type' => 'purchase_bill', 'prefix' => 'ACH-{BRANCH}-{YEAR}-', 'next_number' => 1, 'padding' => 5],
            ['document_type' => 'sales_invoice', 'prefix' => 'FAC-{BRANCH}-{YEAR}-', 'next_number' => 1, 'padding' => 5],
            ['document_type' => 'sales_credit_note', 'prefix' => 'AVO-{BRANCH}-{YEAR}-', 'next_number' => 1, 'padding' => 5],
        ];

        foreach ($defaults as $default) {
            DocumentSequence::query()->firstOrCreate(
                ['company_id' => $companyId, 'document_type' => $default['document_type']],
                $default
            );
        }
    }

    private function parseList(string $raw): array
    {
        return collect(preg_split('/[\s,;]+/', $raw) ?: [])
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->values()
            ->all();
    }

    private function validateWorkflowAssignee($validator, $availableUsers, string $module, int $stepOrder, mixed $assigneeId, string $field): void
    {
        $assigneeId = $this->nullableInteger($assigneeId);
        if (! $assigneeId) {
            return;
        }

        $assignee = $availableUsers->get($assigneeId);
        if (! $assignee || ! $this->approvalSettingsService->canUserBeAssigned($assignee, $module, $stepOrder)) {
            $validator->errors()->add($field, $stepOrder > 1
                ? 'Le valideur choisi doit pouvoir porter une validation direction.'
                : 'Le valideur choisi doit pouvoir approuver ce module.');
        }
    }

    private function nullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max((int) $value, 0) ?: null;
    }
}























