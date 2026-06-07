<?php

namespace App\Modules\Core\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Platform\Models\SaasSubscription;
use App\Modules\Core\Registration\Services\SaasRegistrationService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountProfileController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function edit(
        Request $request,
        CurrentWorkspace $workspace,
        SaasRegistrationService $registrations
    ): View
    {
        $user = $request->user()->load(['company', 'branch', 'roles']);
        $company = $workspace->company();
        $branch = $workspace->branch();
        $canViewSubscription = $user->hasRole('company_admin') || $user->hasRole('platform_admin');
        $subscription = $canViewSubscription && $company
            ? SaasSubscription::query()->where('company_id', $company->id)->first()
            : null;

        return view('account.profile', [
            'accountUser' => $user,
            'activeCompany' => $company,
            'activeBranch' => $branch,
            'subscriptionInfo' => $subscription ? [
                'plan' => $registrations->plans()[$subscription->plan]['label'] ?? ucfirst($subscription->plan),
                'status' => match ($subscription->status) {
                    'trialing' => 'Essai en cours',
                    'active' => 'Actif',
                    'past_due' => 'Paiement en attente',
                    'suspended' => 'Suspendu',
                    'cancelled' => 'Résilié',
                    default => ucfirst($subscription->status),
                },
                'trial_ends_at' => $subscription->trial_ends_at,
                'trial_days_left' => $subscription->trial_ends_at
                    ? max(0, now()->startOfDay()->diffInDays($subscription->trial_ends_at->startOfDay(), false))
                    : null,
                'user_limit' => $subscription->user_limit,
                'user_count' => $company->users()->count(),
                'branch_limit' => $subscription->branch_limit,
                'branch_count' => $company->branches()->count(),
            ] : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        $user->update([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'],
        ]);

        $this->activityLogger->log(
            'account.profile.update',
            'Mise à jour du compte personnel',
            $user
        );

        return redirect()
            ->route('account.profile.edit')
            ->with('success', 'Vos informations ont été mises à jour.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => [
                'required',
                'confirmed',
                Password::min(10)->mixedCase()->numbers()->symbols(),
            ],
        ]);

        $user = $request->user();
        $user->update(['password' => $data['password']]);
        $request->session()->regenerate();

        $this->activityLogger->log(
            'account.password.update',
            'Modification du mot de passe personnel',
            $user
        );

        return redirect()
            ->route('account.profile.edit')
            ->with('success', 'Votre mot de passe a été modifié.');
    }
}
