<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Audit\Models\ActivityLog;
use App\Modules\Core\Branch\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogViewTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_activity_logs_show_branch_and_connection_context_with_filters(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $mainBranch = Branch::query()->findOrFail($user->branch_id);
        $otherBranch = Branch::query()
            ->where('company_id', $user->company_id)
            ->whereKeyNot($mainBranch->id)
            ->firstOrFail();

        ActivityLog::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $mainBranch->id,
            'user_id' => $user->id,
            'action' => 'audit.login',
            'description' => 'Connexion comptable principale',
            'ip_address' => '10.20.30.40',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) TestBrowser',
        ]);

        ActivityLog::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $otherBranch->id,
            'user_id' => $user->id,
            'action' => 'audit.login',
            'description' => 'Connexion autre agence',
            'ip_address' => '10.20.30.41',
            'user_agent' => 'Mozilla/5.0 SecondaryBrowser',
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('activity-logs.index', [
                'branch_id' => $mainBranch->id,
                'search' => 'principale',
            ]))
            ->assertOk()
            ->assertSee('Historique des actions')
            ->assertSee('Lignes visibles')
            ->assertSee('Connexion comptable principale')
            ->assertSee($mainBranch->name)
            ->assertSee('10.20.30.40')
            ->assertSee('TestBrowser')
            ->assertDontSee('Connexion autre agence');
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
