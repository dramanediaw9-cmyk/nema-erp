<?php

namespace App\Modules\Collections\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Collections\Models\CollectionFollowUp;
use App\Modules\Collections\Services\CollectionReminderService;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Partners\Models\Partner;
use App\Modules\Sales\Models\SalesInvoice;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CollectionController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly CollectionReminderService $collectionReminderService,
    ) {
    }

    public function index(Request $request, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $filters = $this->filters($request);
        $items = $this->filteredQuery($companyId, $filters)
            ->paginate(15)
            ->withQueryString();
        $currentCustomer = ($filters['customer_id'] ?? null)
            ? Partner::query()->customers()->where('company_id', $companyId)->find($filters['customer_id'])
            : null;

        return view('collections.index', [
            'items' => $items,
            'reminders' => $this->collectionReminderService->forPortfolio($items->getCollection()),
            'filters' => $filters,
            'summary' => $this->summary($companyId, $filters),
            'branches' => Branch::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'today' => now()->startOfDay(),
            'soonDate' => now()->addDays(7)->endOfDay(),
            'actionOptions' => $this->actionOptions(),
            'outcomeOptions' => $this->outcomeOptions(),
            'currentCustomer' => $currentCustomer,
        ]);
    }

    public function show(SalesInvoice $sale, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $sale->company_id, 403);

        $invoice = $sale->load([
            'customer',
            'branch',
            'warehouse',
            'creator',
            'approver',
            'items.product',
            'paymentAllocations.payment.cashAccount',
            'followUps.creator',
        ]);

        return view('collections.show', [
            'invoice' => $invoice,
            'reminder' => $this->collectionReminderService->forInvoice($invoice),
            'followUps' => $invoice->followUps->sortByDesc(fn (CollectionFollowUp $followUp) => sprintf('%s-%010d', $followUp->action_date?->format('Ymd') ?? '00000000', $followUp->id))->values(),
            'customerOpenInvoices' => SalesInvoice::query()
                ->where('company_id', $invoice->company_id)
                ->where('customer_id', $invoice->customer_id)
                ->where('status', 'validated')
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->whereKeyNot($invoice->id)
                ->orderByDesc('due_date')
                ->orderByDesc('invoice_date')
                ->limit(8)
                ->get(),
            'stats' => [
                'days_overdue' => $invoice->due_date && $invoice->due_date->isPast() ? $invoice->due_date->diffInDays(now()) : 0,
                'customer_open_balance' => (float) SalesInvoice::query()
                    ->where('company_id', $invoice->company_id)
                    ->where('customer_id', $invoice->customer_id)
                    ->where('status', 'validated')
                    ->whereIn('payment_status', ['unpaid', 'partial'])
                    ->sum('balance_due'),
                'follow_up_count' => (int) $invoice->followUps->count(),
                'last_follow_up' => $invoice->followUps->sortByDesc('id')->first(),
            ],
            'actionOptions' => $this->actionOptions(),
            'outcomeOptions' => $this->outcomeOptions(),
        ]);
    }

    public function storeFollowUp(SalesInvoice $sale, Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $sale->company_id, 403);

        $data = $request->validate([
            'action_date' => ['required', 'date'],
            'action_type' => ['required', Rule::in(array_keys($this->actionOptions()))],
            'outcome' => ['nullable', Rule::in(array_keys($this->outcomeOptions()))],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'promised_amount' => ['nullable', 'numeric', 'min:0'],
            'promised_date' => ['nullable', 'date', 'after_or_equal:action_date'],
            'next_action_date' => ['nullable', 'date', 'after_or_equal:action_date'],
            'notes' => ['nullable', 'string'],
        ]);

        if (filled($data['promised_amount'] ?? null) && (float) $data['promised_amount'] > (float) $sale->balance_due) {
            return back()
                ->withInput()
                ->withErrors(['promised_amount' => 'Le montant promis ne peut pas depasser le solde restant de la facture.']);
        }

        $followUp = CollectionFollowUp::query()->create([
            'company_id' => $sale->company_id,
            'branch_id' => $sale->branch_id,
            'sales_invoice_id' => $sale->id,
            'customer_id' => $sale->customer_id,
            'action_date' => $data['action_date'],
            'action_type' => $data['action_type'],
            'outcome' => $data['outcome'] ?? null,
            'contact_name' => $data['contact_name'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'promised_amount' => filled($data['promised_amount'] ?? null) ? $data['promised_amount'] : null,
            'promised_date' => $data['promised_date'] ?? null,
            'next_action_date' => $data['next_action_date'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        $this->activityLogger->log('collections.follow_up.create', 'Enregistrement relance client', $followUp, [
            'invoice_number' => $sale->invoice_number,
            'customer_id' => $sale->customer_id,
            'action_type' => $followUp->action_type,
            'outcome' => $followUp->outcome,
            'promised_amount' => $followUp->promised_amount,
            'promised_date' => optional($followUp->promised_date)?->format('Y-m-d'),
            'next_action_date' => optional($followUp->next_action_date)?->format('Y-m-d'),
        ]);

        return redirect()->route('collections.show', $sale)->with('success', 'Relance de recouvrement enregistree avec succes.');
    }

    private function filteredQuery(int $companyId, array $filters): Builder
    {
        $today = now()->toDateString();
        $soonDate = now()->addDays(7)->toDateString();
        $query = $this->baseQuery($companyId);

        return $query
            ->when($filters['search'], function (Builder $query, string $search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $nested) use ($like) {
                    $nested->where('sales_invoices.invoice_number', 'like', $like)
                        ->orWhere('customers.name', 'like', $like)
                        ->orWhere('customers.phone', 'like', $like)
                        ->orWhere('latest_follow_up.contact_phone', 'like', $like)
                        ->orWhere('latest_follow_up.notes', 'like', $like);
                });
            })
            ->when($filters['branch_id'], fn (Builder $query, int $branchId) => $query->where('sales_invoices.branch_id', $branchId))
            ->when($filters['customer_id'], fn (Builder $query, int $customerId) => $query->where('sales_invoices.customer_id', $customerId))
            ->when($filters['state'] === 'overdue', fn (Builder $query) => $query->whereNotNull('sales_invoices.due_date')->whereDate('sales_invoices.due_date', '<', $today))
            ->when($filters['state'] === 'due_soon', fn (Builder $query) => $query->whereNotNull('sales_invoices.due_date')->whereDate('sales_invoices.due_date', '>=', $today)->whereDate('sales_invoices.due_date', '<=', $soonDate))
            ->when($filters['state'] === 'promised', fn (Builder $query) => $query->whereNotNull('latest_follow_up.promised_date')->whereDate('latest_follow_up.promised_date', '>=', $today))
            ->when($filters['state'] === 'promise_broken', fn (Builder $query) => $query->whereNotNull('latest_follow_up.promised_date')->whereDate('latest_follow_up.promised_date', '<', $today))
            ->when($filters['state'] === 'next_action_due', fn (Builder $query) => $query->whereNotNull('latest_follow_up.next_action_date')->whereDate('latest_follow_up.next_action_date', '<=', $today))
            ->when($filters['state'] === 'no_follow_up', fn (Builder $query) => $query->whereNull('latest_follow_up.id'))
            ->orderByRaw('CASE WHEN sales_invoices.due_date IS NOT NULL AND sales_invoices.due_date < ? THEN 0 ELSE 1 END', [$today])
            ->orderBy('sales_invoices.due_date')
            ->orderByDesc('sales_invoices.balance_due')
            ->orderByDesc('sales_invoices.id');
    }

    private function baseQuery(int $companyId): Builder
    {
        $followUpAggregate = CollectionFollowUp::query()
            ->selectRaw('sales_invoice_id, COUNT(*) as follow_up_count, MAX(id) as latest_follow_up_id')
            ->where('company_id', $companyId)
            ->groupBy('sales_invoice_id');

        return SalesInvoice::query()
            ->join('partners as customers', 'customers.id', '=', 'sales_invoices.customer_id')
            ->leftJoin('branches', 'branches.id', '=', 'sales_invoices.branch_id')
            ->leftJoinSub($followUpAggregate, 'follow_up_aggregate', fn ($join) => $join->on('follow_up_aggregate.sales_invoice_id', '=', 'sales_invoices.id'))
            ->leftJoin('collection_follow_ups as latest_follow_up', 'latest_follow_up.id', '=', 'follow_up_aggregate.latest_follow_up_id')
            ->where('sales_invoices.company_id', $companyId)
            ->where('sales_invoices.status', 'validated')
            ->whereIn('sales_invoices.payment_status', ['unpaid', 'partial'])
            ->select('sales_invoices.*')
            ->selectRaw('customers.name as customer_name')
            ->selectRaw('customers.phone as customer_phone')
            ->selectRaw('branches.name as branch_name')
            ->selectRaw('COALESCE(follow_up_aggregate.follow_up_count, 0) as follow_up_count')
            ->selectRaw('latest_follow_up.id as latest_follow_up_id')
            ->selectRaw('latest_follow_up.action_date as last_action_date')
            ->selectRaw('latest_follow_up.action_type as last_action_type')
            ->selectRaw('latest_follow_up.outcome as last_outcome')
            ->selectRaw('latest_follow_up.promised_amount as last_promised_amount')
            ->selectRaw('latest_follow_up.promised_date as last_promised_date')
            ->selectRaw('latest_follow_up.next_action_date as last_next_action_date')
            ->selectRaw('latest_follow_up.notes as last_notes');
    }

    private function summary(int $companyId, array $filters): array
    {
        $today = now()->toDateString();
        $soonDate = now()->addDays(7)->toDateString();
        $query = $this->filteredQuery($companyId, $filters);

        return [
            'invoice_count' => (int) (clone $query)->count('sales_invoices.id'),
            'open_balance_total' => (float) (clone $query)->sum('sales_invoices.balance_due'),
            'overdue_balance_total' => (float) (clone $query)
                ->whereNotNull('sales_invoices.due_date')
                ->whereDate('sales_invoices.due_date', '<', $today)
                ->sum('sales_invoices.balance_due'),
            'promised_balance_total' => (float) (clone $query)
                ->whereNotNull('latest_follow_up.promised_date')
                ->whereDate('latest_follow_up.promised_date', '>=', $today)
                ->sum('sales_invoices.balance_due'),
            'promise_broken_count' => (int) (clone $query)
                ->whereNotNull('latest_follow_up.promised_date')
                ->whereDate('latest_follow_up.promised_date', '<', $today)
                ->count('sales_invoices.id'),
            'next_actions_due_count' => (int) (clone $query)
                ->whereNotNull('latest_follow_up.next_action_date')
                ->whereDate('latest_follow_up.next_action_date', '<=', $today)
                ->count('sales_invoices.id'),
            'due_soon_count' => (int) (clone $query)
                ->whereNotNull('sales_invoices.due_date')
                ->whereDate('sales_invoices.due_date', '>=', $today)
                ->whereDate('sales_invoices.due_date', '<=', $soonDate)
                ->count('sales_invoices.id'),
        ];
    }

    private function filters(Request $request): array
    {
        $state = $request->string('state')->trim()->value() ?: null;
        if (! in_array($state, ['overdue', 'due_soon', 'promised', 'promise_broken', 'next_action_due', 'no_follow_up'], true)) {
            $state = null;
        }

        return [
            'search' => $request->string('search')->trim()->value() ?: null,
            'branch_id' => $request->integer('branch_id') ?: null,
            'customer_id' => $request->integer('customer_id') ?: null,
            'state' => $state,
        ];
    }

    private function actionOptions(): array
    {
        return [
            'call' => 'Appel',
            'whatsapp' => 'WhatsApp',
            'email' => 'Email',
            'visit' => 'Visite',
            'meeting' => 'Rendez-vous',
            'other' => 'Autre',
        ];
    }

    private function outcomeOptions(): array
    {
        return [
            'no_answer' => 'Sans reponse',
            'promised' => 'Promesse de paiement',
            'disputed' => 'Litige',
            'partial_payment' => 'Paiement partiel annonce',
            'waiting_transfer' => 'Virement en attente',
            'callback' => 'Rappel demande',
            'resolved' => 'Regularise',
            'other' => 'Autre',
        ];
    }
}
