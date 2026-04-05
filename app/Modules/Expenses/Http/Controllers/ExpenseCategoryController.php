<?php

namespace App\Modules\Expenses\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpenseCategoryController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    public function index(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        return view('expense-categories.index', [
            'categories' => ExpenseCategory::query()
                ->where('company_id', $companyId)
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    public function create(CurrentWorkspace $workspace): View
    {
        abort_if(! $workspace->companyId(), 403);

        return view('expense-categories.create', [
            'category' => new ExpenseCategory(['is_active' => true, 'default_account_code' => '606300']),
        ]);
    }

    public function store(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $data = $this->validateCategory($request, $companyId);
        $data['company_id'] = $companyId;
        $data['is_active'] = $request->boolean('is_active', true);

        $category = ExpenseCategory::query()->create($data);
        $this->activityLogger->log('expense_categories.create', 'Creation categorie de depense', $category);

        return redirect()->route('expense-categories.index')->with('success', 'Categorie de depense creee avec succes.');
    }

    public function edit(ExpenseCategory $expenseCategory, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $expenseCategory->company_id, 403);

        return view('expense-categories.edit', [
            'category' => $expenseCategory,
        ]);
    }

    public function update(Request $request, ExpenseCategory $expenseCategory, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $expenseCategory->company_id, 403);

        $data = $this->validateCategory($request, $expenseCategory->company_id, $expenseCategory->id);
        $data['is_active'] = $request->boolean('is_active', true);

        $expenseCategory->update($data);
        $this->activityLogger->log('expense_categories.update', 'Mise a jour categorie de depense', $expenseCategory);

        return redirect()->route('expense-categories.index')->with('success', 'Categorie de depense mise a jour avec succes.');
    }

    private function validateCategory(Request $request, int $companyId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('expense_categories', 'name')
                    ->where(fn ($query) => $query->where('company_id', $companyId))
                    ->ignore($ignoreId),
            ],
            'description' => ['nullable', 'string'],
            'default_account_code' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
