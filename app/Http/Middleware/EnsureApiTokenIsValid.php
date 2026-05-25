<?php

namespace App\Http\Middleware;

use App\Modules\Core\Integrations\Models\ApiToken;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnsureApiTokenIsValid
{
    public function handle(Request $request, Closure $next): mixed
    {
        $token = $request->bearerToken() ?: $request->header('X-Api-Key');

        if (! $token) {
            return new JsonResponse(['message' => 'Jeton API manquant.'], 401);
        }

        $hashed = hash('sha256', $token);
        /** @var ApiToken|null $apiToken */
        $apiToken = ApiToken::query()
            ->with('company')
            ->where('token_hash', $hashed)
            ->when($request->route('company'), fn ($query, $company) => $query->where('company_id', (int) $company))
            ->first();

        /** @var CarbonInterface|null $expiresAt */
        $expiresAt = $apiToken?->expires_at;

        if (! $apiToken || ($expiresAt instanceof CarbonInterface && $expiresAt->isPast())) {
            return new JsonResponse(['message' => 'Jeton API invalide ou expire.'], 401);
        }

        $apiToken->forceFill(['last_used_at' => now()])->save();

        $request->attributes->set('apiToken', $apiToken);
        $request->attributes->set('apiCompany', $apiToken->company);
        $request->attributes->set('apiTenantId', $apiToken->tenant_id);

        return $next($request);
    }
}
