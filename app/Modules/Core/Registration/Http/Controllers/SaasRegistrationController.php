<?php

namespace App\Modules\Core\Registration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Core\Company\Models\Tenant;
use App\Modules\Core\Company\Services\SectorProfileService;
use App\Modules\Core\Registration\Services\SaasRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SaasRegistrationController extends Controller
{
    private const SESSION_KEY = 'saas_registration';

    public function __construct(
        private readonly SaasRegistrationService $registrations,
        private readonly SectorProfileService $sectorProfiles,
    ) {
    }

    public function account(Request $request): View
    {
        $registration = $this->registration($request);
        $registration['started_at'] ??= now()->timestamp;
        $this->storeRegistration($request, $registration);

        return $this->view('account', $registration);
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $registration = $this->registration($request);
        $registration['account'] = [
            'name' => trim($data['name']),
            'email' => $data['email'],
            'phone' => trim((string) ($data['phone'] ?? '')),
            'password_hash' => Hash::make($data['password']),
        ];
        $this->storeRegistration($request, $registration);

        return redirect()->route('saas.register.company');
    }

    public function company(Request $request): View|RedirectResponse
    {
        $registration = $this->registration($request);

        if (! isset($registration['account'])) {
            return redirect()->route('saas.register.account');
        }

        return $this->view('company', $registration);
    }

    public function storeCompany(Request $request): RedirectResponse
    {
        if (! isset($this->registration($request)['account'])) {
            return redirect()->route('saas.register.account');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'legal_name' => ['nullable', 'string', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $registration = $this->registration($request);
        $registration['company'] = [
            'name' => trim($data['name']),
            'legal_name' => trim((string) ($data['legal_name'] ?? '')),
            'phone' => trim((string) ($data['phone'] ?? '')),
            'email' => Str::lower(trim((string) ($data['email'] ?? ''))),
            'address' => trim((string) ($data['address'] ?? '')),
        ];
        $this->storeRegistration($request, $registration);

        return redirect()->route('saas.register.workspace');
    }

    public function workspace(Request $request): View|RedirectResponse
    {
        $registration = $this->registration($request);

        if (! isset($registration['account'], $registration['company'])) {
            return redirect()->route('saas.register.account');
        }

        $registration['workspace']['slug'] ??= $this->availableSlug($registration['company']['name']);

        return $this->view('workspace', $registration);
    }

    public function storeWorkspace(Request $request): RedirectResponse
    {
        $registration = $this->registration($request);

        if (! isset($registration['account'], $registration['company'])) {
            return redirect()->route('saas.register.account');
        }

        $request->merge(['slug' => Str::slug((string) $request->input('slug'))]);

        $data = $request->validate([
            'slug' => ['required', 'alpha_dash:ascii', 'min:3', 'max:80', Rule::unique('tenants', 'slug')],
            'sector_profile' => ['required', Rule::in($this->sectorProfiles->keys())],
            'branch_name' => ['required', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
        ]);

        $registration['workspace'] = [
            'slug' => $data['slug'],
            'sector_profile' => $data['sector_profile'],
            'branch_name' => trim($data['branch_name']),
            'city' => trim((string) ($data['city'] ?? '')),
        ];
        $this->storeRegistration($request, $registration);

        return redirect()->route('saas.register.plan');
    }

    public function plan(Request $request): View|RedirectResponse
    {
        $registration = $this->registration($request);

        if (! isset($registration['account'], $registration['company'], $registration['workspace'])) {
            return redirect()->route('saas.register.account');
        }

        return $this->view('plan', $registration);
    }

    public function complete(Request $request): RedirectResponse
    {
        $registration = $this->registration($request);

        if (! isset($registration['account'], $registration['company'], $registration['workspace'])) {
            return redirect()->route('saas.register.account')
                ->withErrors(['registration' => 'Veuillez compléter les quatre étapes dans l ordre.']);
        }

        $data = $request->validate([
            'plan' => ['required', Rule::in(array_keys($this->registrations->plans()))],
            'terms' => ['accepted'],
            'website' => ['nullable', 'max:0'],
        ], [
            'terms.accepted' => 'Vous devez accepter les conditions pour créer l espace.',
            'website.max' => 'La création de cet espace n a pas pu être validée.',
        ]);

        if (User::query()->where('email', $registration['account']['email'])->exists()) {
            return redirect()->route('saas.register.account')
                ->withErrors(['email' => 'Un compte utilise déjà cette adresse e-mail.']);
        }

        if (Tenant::query()->where('slug', $registration['workspace']['slug'])->exists()) {
            return redirect()->route('saas.register.workspace')
                ->withErrors(['slug' => 'Cette adresse d espace est déjà utilisée.']);
        }

        $registration['plan'] = $data['plan'];
        $created = $this->registrations->create($registration);

        Auth::login($created['user']);
        $request->session()->regenerate();
        $request->session()->forget(self::SESSION_KEY);
        $request->session()->put([
            'current_tenant_id' => $created['tenant']->id,
            'current_company_id' => $created['company']->id,
            'current_branch_id' => $created['branch']->id,
            'ui_mode' => 'full',
        ]);

        return redirect()->route('onboarding.index')
            ->with('success', 'Votre espace Nema est prêt. Commençons sa configuration.');
    }

    private function view(string $step, array $registration): View
    {
        return view('saas.register', [
            'step' => $step,
            'registration' => $registration,
            'profiles' => $this->sectorProfiles->profiles(),
            'plans' => $this->registrations->plans(),
        ]);
    }

    private function registration(Request $request): array
    {
        return (array) $request->session()->get(self::SESSION_KEY, []);
    }

    private function storeRegistration(Request $request, array $registration): void
    {
        $request->session()->put(self::SESSION_KEY, $registration);
    }

    private function availableSlug(string $companyName): string
    {
        $base = Str::slug($companyName) ?: 'entreprise';
        $candidate = $base;
        $suffix = 2;

        while (Tenant::query()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
