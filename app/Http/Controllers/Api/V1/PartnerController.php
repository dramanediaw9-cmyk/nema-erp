<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiActor;
use App\Models\User;
use App\Modules\Partners\Models\Partner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PartnerController
{
    use ResolvesApiActor;

    public function index(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        $type = $request->string('type')->trim()->value();
        $search = $request->string('search')->trim()->value();
        $code = $request->string('code')->trim()->value();

        $partners = Partner::query()
            ->with(['paymentTerm', 'priceList', 'contacts', 'bankAccounts', 'mobileWallets'])
            ->where('company_id', $company->id);

        $this->applyReadablePartnerScope($partners, $actor, $type);

        $partners = $partners
            ->when($code !== '', fn ($query) => $query->where('code', $code))
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.$search.'%';

                $query->where(function ($nested) use ($like) {
                    $nested->where('code', 'like', $like)
                        ->orWhere('name', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('nif', 'like', $like);
                });
            })
            ->orderBy('name')
            ->paginate(min((int) $request->integer('per_page', 50), 200));

        return response()->json($partners);
    }

    public function show(Request $request, Partner $partner): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        abort_unless($partner->company_id === $company->id, 404);
        $actor = $this->resolveApiUser($request, $company->id);
        $this->authorizePartnerRead($actor, $partner);

        return response()->json($this->partnerPayload($partner));
    }

    public function store(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $tenantId = (int) $request->attributes->get('apiTenantId');
        $actor = $this->resolveApiUser($request, $company->id);

        $data = $this->validatePartner($request, $company->id);
        $this->authorizePartnerWrite($actor, $data['type']);
        $data['tenant_id'] = $tenantId ?: $company->tenant_id;
        $data['company_id'] = $company->id;
        $data['code'] = filled($data['code'] ?? null) ? $data['code'] : $this->generateCode($company->id, $data['type']);
        $data['is_active'] = $request->boolean('is_active', true);

        $partner = Partner::query()->create($data);

        return response()->json($this->partnerPayload($partner), 201);
    }

    public function update(Request $request, Partner $partner): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        abort_unless($partner->company_id === $company->id, 404);
        $actor = $this->resolveApiUser($request, $company->id);

        $data = $this->validatePartner($request, $company->id, $partner->id);
        $this->authorizePartnerWrite($actor, filled($data['type'] ?? null) ? $data['type'] : $partner->type);
        $data['code'] = filled($data['code'] ?? null) ? $data['code'] : $partner->code;
        $data['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : $partner->is_active;
        $data['type'] = filled($data['type'] ?? null) ? $data['type'] : $partner->type;

        $partner->update($data);

        return response()->json($this->partnerPayload($partner->fresh()));
    }

    private function partnerPayload(Partner $partner): Partner
    {
        return $partner->load(['paymentTerm', 'priceList', 'contacts', 'addresses', 'bankAccounts', 'mobileWallets']);
    }

    private function validatePartner(Request $request, int $companyId, ?int $ignoreId = null): array
    {
        $typeRules = $ignoreId ? ['sometimes', 'required', Rule::in(['customer', 'supplier', 'both'])] : ['required', Rule::in(['customer', 'supplier', 'both'])];

        return $request->validate([
            'type' => $typeRules,
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('partners', 'code')->where(fn ($query) => $query->where('company_id', $companyId))->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'nif' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'payment_term_id' => ['nullable', Rule::exists('payment_terms', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'price_list_id' => ['nullable', Rule::exists('price_lists', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function generateCode(int $companyId, string $type): string
    {
        $documentType = match ($type) {
            'customer' => 'partner_customer_code',
            'supplier' => 'partner_supplier_code',
            default => 'partner_generic_code',
        };

        return app(\App\Modules\Core\Company\Services\DocumentNumberService::class)
            ->nextNumber($companyId, $documentType);
    }

    private function applyReadablePartnerScope(Builder $query, User $actor, string $type): void
    {
        $canViewCustomers = $actor->hasPermission('customers.view');
        $canViewSuppliers = $actor->hasPermission('suppliers.view');

        abort_unless($canViewCustomers || $canViewSuppliers, 403);

        if ($type === 'customer') {
            $this->ensureApiPermission($actor, 'customers.view');
            $query->whereIn('type', ['customer', 'both']);

            return;
        }

        if ($type === 'supplier') {
            $this->ensureApiPermission($actor, 'suppliers.view');
            $query->whereIn('type', ['supplier', 'both']);

            return;
        }

        if ($type === 'both') {
            $this->ensureAnyApiPermission($actor, ['customers.view', 'suppliers.view']);
            $query->where('type', 'both');

            return;
        }

        $query->where(function (Builder $partnerQuery) use ($canViewCustomers, $canViewSuppliers): void {
            if ($canViewCustomers) {
                $partnerQuery->orWhereIn('type', ['customer', 'both']);
            }

            if ($canViewSuppliers) {
                $partnerQuery->orWhereIn('type', ['supplier', 'both']);
            }
        });
    }

    private function authorizePartnerRead(User $actor, Partner $partner): void
    {
        match ($partner->type) {
            'customer' => $this->ensureApiPermission($actor, 'customers.view'),
            'supplier' => $this->ensureApiPermission($actor, 'suppliers.view'),
            default => $this->ensureAnyApiPermission($actor, ['customers.view', 'suppliers.view']),
        };
    }

    private function authorizePartnerWrite(User $actor, string $type): void
    {
        match ($type) {
            'customer' => $this->ensureApiPermission($actor, 'customers.manage'),
            'supplier' => $this->ensureApiPermission($actor, 'suppliers.manage'),
            default => abort_unless(
                $actor->hasPermission('customers.manage') && $actor->hasPermission('suppliers.manage'),
                403
            ),
        };
    }
}
