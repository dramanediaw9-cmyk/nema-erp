<?php

namespace App\Modules\Crm\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Crm\Models\Opportunity;
use App\Modules\Partners\Models\Partner;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OpportunityController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    public function index(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $opportunities = Opportunity::query()
            ->with(['branch', 'partner'])
            ->where('company_id', $companyId)
            ->orderByRaw("FIELD(stage, 'new','qualified','proposal','negotiation','won','lost')")
            ->orderByDesc('expected_amount')
            ->orderByDesc('id')
            ->paginate(15);

        $all = Opportunity::query()->where('company_id', $companyId)->get();

        return view('crm.index', [
            'opportunities' => $opportunities,
            'stageOptions' => $this->stageOptions(),
            'summary' => [
                'count' => $all->count(),
                'open_count' => $all->whereNotIn('stage', ['won', 'lost'])->count(),
                'pipeline_total' => (float) $all->whereNotIn('stage', ['won', 'lost'])->sum(fn (Opportunity $opportunity) => (float) ($opportunity->expected_amount ?? 0)),
                'won_total' => (float) $all->where('stage', 'won')->sum(fn (Opportunity $opportunity) => (float) ($opportunity->expected_amount ?? 0)),
            ],
        ]);
    }

    public function create(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        return view('crm.create', [
            'branches' => Branch::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'customers' => Partner::query()->customers()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'stageOptions' => $this->stageOptions(),
        ]);
    }

    public function store(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId, 403);

        $payload = $request->validate([
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'partner_id' => ['nullable', Rule::exists('partners', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'lead_name' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'source' => ['nullable', 'string', 'max:100'],
            'stage' => ['required', Rule::in(array_keys($this->stageOptions()))],
            'expected_amount' => ['nullable', 'numeric', 'min:0'],
            'expected_close_date' => ['nullable', 'date'],
            'last_contact_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $opportunity = Opportunity::query()->create([
            'company_id' => $companyId,
            'branch_id' => $payload['branch_id'] ?? $branchId,
            'partner_id' => $payload['partner_id'] ?? null,
            'lead_name' => $payload['lead_name'],
            'title' => $payload['title'],
            'contact_name' => $payload['contact_name'] ?? null,
            'contact_phone' => $payload['contact_phone'] ?? null,
            'contact_email' => $payload['contact_email'] ?? null,
            'source' => $payload['source'] ?? null,
            'stage' => $payload['stage'],
            'expected_amount' => $payload['expected_amount'] ?? null,
            'expected_close_date' => $payload['expected_close_date'] ?? null,
            'last_contact_date' => $payload['last_contact_date'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        $this->activityLogger->log('crm.opportunities.create', 'Creation opportunite commerciale', $opportunity, [
            'title' => $opportunity->title,
            'stage' => $opportunity->stage,
            'expected_amount' => $opportunity->expected_amount,
        ]);

        return redirect()->route('crm.show', $opportunity)->with('success', 'Opportunite enregistree avec succes.');
    }

    public function show(Opportunity $opportunity, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $opportunity->company_id, 403);

        return view('crm.show', [
            'opportunity' => $opportunity->load(['branch', 'partner', 'creator', 'updater']),
            'stageOptions' => $this->stageOptions(),
        ]);
    }

    public function updateStage(Opportunity $opportunity, Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $opportunity->company_id, 403);

        $data = $request->validate([
            'stage' => ['required', Rule::in(array_keys($this->stageOptions()))],
        ]);

        $opportunity->update([
            'stage' => $data['stage'],
            'updated_by' => $request->user()?->id,
        ]);

        $this->activityLogger->log('crm.opportunities.stage', 'Changement etape opportunite', $opportunity, [
            'stage' => $opportunity->stage,
        ]);

        return redirect()->route('crm.show', $opportunity)->with('success', 'Etape commerciale mise a jour.');
    }

    public function convertToCustomer(Opportunity $opportunity, Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $opportunity->company_id, 403);

        if ($opportunity->partner_id) {
            return redirect()->route('crm.show', $opportunity)->with('success', 'Cette opportunite est deja liee a un client.');
        }

        $customer = Partner::query()->create([
            'company_id' => $opportunity->company_id,
            'type' => 'customer',
            'code' => $this->generateCustomerCode($opportunity->company_id),
            'name' => $opportunity->lead_name,
            'phone' => $opportunity->contact_phone,
            'email' => $opportunity->contact_email,
            'city' => null,
            'nif' => null,
            'address' => null,
            'opening_balance' => 0,
            'notes' => 'Client cree depuis opportunite CRM '.$opportunity->title,
            'is_active' => true,
        ]);

        $opportunity->update([
            'partner_id' => $customer->id,
            'stage' => $opportunity->stage === 'new' ? 'qualified' : $opportunity->stage,
            'updated_by' => $request->user()?->id,
        ]);

        $this->activityLogger->log('crm.opportunities.convert', 'Conversion opportunite en client', $opportunity, [
            'customer_id' => $customer->id,
            'customer_code' => $customer->code,
        ]);

        return redirect()->route('customers.show', $customer)->with('success', 'Opportunite convertie en client avec succes.');
    }

    private function generateCustomerCode(int $companyId): string
    {
        return app(\App\Modules\Core\Company\Services\DocumentNumberService::class)
            ->nextNumber($companyId, 'partner_customer_code');
    }

    private function stageOptions(): array
    {
        return [
            'new' => 'Nouveau',
            'qualified' => 'Qualifie',
            'proposal' => 'Proposition',
            'negotiation' => 'Negociation',
            'won' => 'Gagne',
            'lost' => 'Perdu',
        ];
    }
}

