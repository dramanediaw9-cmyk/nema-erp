<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiActor;
use App\Modules\Payroll\Models\PayrollRun;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayrollRunController
{
    use ResolvesApiActor;

    public function index(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('payroll.view'), 403);

        $status = $request->string('status')->trim()->value();
        $search = $request->string('search')->trim()->value();

        $runs = PayrollRun::query()
            ->with('branch')
            ->where('company_id', $company->id)
            ->when($request->integer('branch_id') > 0, fn (Builder $query) => $query->where('branch_id', $request->integer('branch_id')))
            ->when(in_array($status, ['draft', 'review', 'ready', 'paid'], true), fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $nested) use ($like) {
                    $nested->where('run_number', 'like', $like)
                        ->orWhere('label', 'like', $like);
                });
            })
            ->orderByDesc('period_end')
            ->paginate(min(max((int) $request->integer('per_page', 50), 1), 200));

        return response()->json($runs);
    }

    public function show(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        abort_unless($payrollRun->company_id === $company->id, 404);

        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('payroll.view'), 403);

        return response()->json($payrollRun->load('branch'));
    }

    public function store(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('payroll.manage'), 403);

        $data = $request->validate([
            'run_number' => ['nullable', 'string', 'max:30', Rule::unique('payroll_runs', 'run_number')->where(fn ($query) => $query->where('company_id', $company->id))],
            'label' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $company->id))],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'scheduled_pay_date' => ['nullable', 'date', 'after_or_equal:period_end'],
            'headcount' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'gross_amount' => ['nullable', 'numeric', 'min:0'],
            'net_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['draft', 'review', 'ready', 'paid'])],
            'notes' => ['nullable', 'string'],
        ]);

        $run = PayrollRun::query()->create([
            'company_id' => $company->id,
            'branch_id' => $data['branch_id'] ?? null,
            'run_number' => ($data['run_number'] ?? null) ?: $this->generateNumber($company->id),
            'label' => $data['label'],
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'scheduled_pay_date' => $data['scheduled_pay_date'] ?? null,
            'headcount' => (int) ($data['headcount'] ?? 0),
            'gross_amount' => $data['gross_amount'] ?? 0,
            'net_amount' => $data['net_amount'] ?? 0,
            'status' => $data['status'] ?? 'draft',
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        return response()->json($run->load('branch'), 201);
    }

    private function generateNumber(int $companyId): string
    {
        return app(\App\Modules\Core\Company\Services\DocumentNumberService::class)
            ->nextNumber($companyId, 'payroll_run_number');
    }
}
