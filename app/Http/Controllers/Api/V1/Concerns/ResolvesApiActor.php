<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

trait ResolvesApiActor
{
    protected function resolveApiUser(Request $request, int $companyId): User
    {
        $token = $request->attributes->get('apiToken');
        $userId = (int) ($token?->created_by ?? 0);

        $user = User::query()
            ->with(['roles.permissions'])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->find($userId);

        if (! $user) {
            throw ValidationException::withMessages([
                'api_token' => 'Le jeton API n est rattache a aucun utilisateur actif.',
            ]);
        }

        return $user;
    }
}
