<?php

namespace App\Modules\Core\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UiModeController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['merchant', 'full'])],
        ]);

        $request->session()->put('ui_mode', $data['mode']);

        return back()->with(
            'success',
            $data['mode'] === 'merchant'
                ? 'Mode commercant active : navigation simplifiee et actions quotidiennes mises en avant.'
                : 'Mode complet reactive : tous les modules restent visibles.'
        );
    }
}
