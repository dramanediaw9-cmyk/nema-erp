<?php

namespace App\Modules\Core\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Dashboard\Models\UserNavigationFavorite;
use App\Support\CurrentWorkspace;
use App\Support\ErpNavigationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class NavigationFavoriteController extends Controller
{
    public function toggle(Request $request, CurrentWorkspace $workspace, ErpNavigationService $navigation): RedirectResponse
    {
        $user = $request->user();
        $companyId = $workspace->companyId();

        abort_if(! $user || ! $companyId, 403);

        $validated = $request->validate([
            'module_key' => ['required', 'string', 'max:50'],
        ]);

        $allowedModuleKeys = collect($navigation->build($user, $request, false, $companyId)['modules'] ?? [])
            ->pluck('key');

        abort_unless($allowedModuleKeys->contains($validated['module_key']), 403);

        if (! $this->favoritesFeatureAvailable()) {
            return back()->with('error', 'Les favoris seront disponibles apres la mise a jour de la base.');
        }

        $favorite = UserNavigationFavorite::query()->where([
            'company_id' => $companyId,
            'user_id' => $user->id,
            'module_key' => $validated['module_key'],
        ])->first();

        if ($favorite) {
            $favorite->delete();

            return back()->with('success', 'Module retire des favoris.');
        }

        $nextSortOrder = (int) UserNavigationFavorite::query()
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->max('sort_order');

        UserNavigationFavorite::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $companyId,
            'user_id' => $user->id,
            'module_key' => $validated['module_key'],
            'sort_order' => $nextSortOrder + 1,
        ]);

        return back()->with('success', 'Module ajoute aux favoris.');
    }

    private function favoritesFeatureAvailable(): bool
    {
        return Schema::hasTable('user_navigation_favorites');
    }
}
