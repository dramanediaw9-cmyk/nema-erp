<?php

namespace App\Modules\Partners\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Partners\Models\Partner;
use App\Modules\Partners\Models\PartnerAddress;
use App\Modules\Partners\Models\PartnerBankAccount;
use App\Modules\Partners\Models\PartnerContact;
use App\Modules\Partners\Models\PartnerMobileWallet;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PartnerProfileController extends Controller
{
    public function storeContact(Request $request, Partner $partner, CurrentWorkspace $workspace): RedirectResponse
    {
        $this->authorizePartner($request, $partner, $workspace);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('is_primary')) {
            $partner->contacts()->update(['is_primary' => false]);
        }

        $partner->contacts()->create([
            'tenant_id' => $partner->tenant_id,
            'company_id' => $partner->company_id,
            'name' => $data['name'],
            'role' => $data['role'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'is_primary' => $request->boolean('is_primary'),
        ]);

        return back()->with('success', 'Contact ajoute au tiers.');
    }

    public function storeAddress(Request $request, Partner $partner, CurrentWorkspace $workspace): RedirectResponse
    {
        $this->authorizePartner($request, $partner, $workspace);

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:billing,shipping,other'],
            'address_line' => ['required', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:100'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('is_primary')) {
            $partner->addresses()->update(['is_primary' => false]);
        }

        $partner->addresses()->create([
            'tenant_id' => $partner->tenant_id,
            'company_id' => $partner->company_id,
            'label' => $data['label'] ?? null,
            'type' => $data['type'],
            'address_line' => $data['address_line'],
            'city' => $data['city'] ?? null,
            'country' => $data['country'],
            'is_primary' => $request->boolean('is_primary'),
        ]);

        return back()->with('success', 'Adresse ajoutee au tiers.');
    }

    public function storeBankAccount(Request $request, Partner $partner, CurrentWorkspace $workspace): RedirectResponse
    {
        $this->authorizePartner($request, $partner, $workspace);

        $data = $request->validate([
            'bank_name' => ['required', 'string', 'max:255'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:255'],
            'swift_code' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('is_primary')) {
            $partner->bankAccounts()->update(['is_primary' => false]);
        }

        $partner->bankAccounts()->create([
            'tenant_id' => $partner->tenant_id,
            'company_id' => $partner->company_id,
            'bank_name' => $data['bank_name'],
            'account_name' => $data['account_name'] ?? null,
            'account_number' => $data['account_number'],
            'iban' => $data['iban'] ?? null,
            'swift_code' => $data['swift_code'] ?? null,
            'is_primary' => $request->boolean('is_primary'),
        ]);

        return back()->with('success', 'Compte bancaire ajoute au tiers.');
    }

    public function storeWallet(Request $request, Partner $partner, CurrentWorkspace $workspace): RedirectResponse
    {
        $this->authorizePartner($request, $partner, $workspace);

        $data = $request->validate([
            'provider' => ['required', 'string', 'max:255'],
            'wallet_number' => ['required', 'string', 'max:255'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('is_primary')) {
            $partner->mobileWallets()->update(['is_primary' => false]);
        }

        $partner->mobileWallets()->create([
            'tenant_id' => $partner->tenant_id,
            'company_id' => $partner->company_id,
            'provider' => $data['provider'],
            'wallet_number' => $data['wallet_number'],
            'account_name' => $data['account_name'] ?? null,
            'is_primary' => $request->boolean('is_primary'),
        ]);

        return back()->with('success', 'Wallet ajoute au tiers.');
    }

    public function destroyContact(Request $request, Partner $partner, PartnerContact $contact, CurrentWorkspace $workspace): RedirectResponse
    {
        $this->authorizePartner($request, $partner, $workspace);
        abort_if($contact->partner_id !== $partner->id, 403);
        $contact->delete();

        return back()->with('success', 'Contact supprime.');
    }

    public function destroyAddress(Request $request, Partner $partner, PartnerAddress $address, CurrentWorkspace $workspace): RedirectResponse
    {
        $this->authorizePartner($request, $partner, $workspace);
        abort_if($address->partner_id !== $partner->id, 403);
        $address->delete();

        return back()->with('success', 'Adresse supprimee.');
    }

    public function destroyBankAccount(Request $request, Partner $partner, PartnerBankAccount $bankAccount, CurrentWorkspace $workspace): RedirectResponse
    {
        $this->authorizePartner($request, $partner, $workspace);
        abort_if($bankAccount->partner_id !== $partner->id, 403);
        $bankAccount->delete();

        return back()->with('success', 'Compte bancaire supprime.');
    }

    public function destroyWallet(Request $request, Partner $partner, PartnerMobileWallet $wallet, CurrentWorkspace $workspace): RedirectResponse
    {
        $this->authorizePartner($request, $partner, $workspace);
        abort_if($wallet->partner_id !== $partner->id, 403);
        $wallet->delete();

        return back()->with('success', 'Wallet supprime.');
    }

    private function authorizePartner(Request $request, Partner $partner, CurrentWorkspace $workspace): void
    {
        abort_if($workspace->companyId() !== $partner->company_id, 403);

        $permission = in_array($partner->type, ['customer', 'both'], true)
            ? 'customers.manage'
            : 'suppliers.manage';

        abort_unless($request->user()?->hasPermission($permission), 403);
    }
}
