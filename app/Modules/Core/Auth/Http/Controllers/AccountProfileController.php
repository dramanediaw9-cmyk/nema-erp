<?php

namespace App\Modules\Core\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountProfileController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function edit(Request $request): View
    {
        return view('account.profile', [
            'accountUser' => $request->user()->load(['company', 'branch', 'roles']),
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
