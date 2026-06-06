<?php

namespace App\Modules\Core\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_THROTTLED) {
            return back()
                ->withErrors(['email' => 'Veuillez patienter avant de demander un nouveau lien.'])
                ->onlyInput('email');
        }

        return back()->with(
            'status',
            'Si cette adresse correspond à un compte actif, un lien de réinitialisation vient d’être envoyé.'
        );
    }
}
