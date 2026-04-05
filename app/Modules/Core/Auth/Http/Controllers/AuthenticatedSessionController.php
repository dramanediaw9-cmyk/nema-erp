<?php

namespace App\Modules\Core\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = sprintf('login:%s:%s', $credentials['email'], $request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors([
                'email' => 'Trop de tentatives. Veuillez patienter une minute avant de réessayer.',
            ])->onlyInput('email');
        }

        if (! Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password'], 'is_active' => true], (bool) $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);

            return back()->withErrors([
                'email' => 'Les identifiants fournis sont invalides.',
            ])->onlyInput('email');
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->save();

        if ($user->company_id) {
            $request->session()->put('current_company_id', $user->company_id);
        }

        if ($user->branch_id) {
            $request->session()->put('current_branch_id', $user->branch_id);
        }

        $this->activityLogger->log('auth.login', 'Connexion utilisateur');

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->activityLogger->log('auth.logout', 'Déconnexion utilisateur');

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
