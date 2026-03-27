<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\FixedAssets\Models\FixedAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixedAssetFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_manager_can_create_fixed_asset(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('fixed-assets.store'), [
                'name' => 'Moto de livraison Yamaha',
                'category' => 'Materiel roulant',
                'acquisition_date' => now()->toDateString(),
                'commissioning_date' => now()->toDateString(),
                'depreciation_start_date' => now()->startOfMonth()->toDateString(),
                'depreciation_method' => 'linear',
                'useful_life_months' => 36,
                'acquisition_cost' => 2400000,
                'salvage_value' => 300000,
                'status' => 'active',
                'notes' => 'Affectee a la logistique Bamako',
            ]);

        $asset = FixedAsset::query()->where('company_id', $user->company_id)->where('name', 'Moto de livraison Yamaha')->firstOrFail();

        $response->assertRedirect(route('fixed-assets.show', $asset));
        $this->assertMatchesRegularExpression('/^IMO-BKO-\d{4}-\d{5}$/', $asset->asset_number);
        $this->assertSame('active', $asset->status);
    }

    public function test_fixed_asset_detail_shows_depreciation_schedule(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $asset = FixedAsset::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'asset_number' => 'IMO-BKO-'.now()->year.'-00099',
            'name' => 'Ordinateur comptable',
            'category' => 'Informatique',
            'acquisition_date' => now()->subMonths(2)->toDateString(),
            'commissioning_date' => now()->subMonths(2)->toDateString(),
            'depreciation_start_date' => now()->subMonths(2)->startOfMonth()->toDateString(),
            'depreciation_method' => 'linear',
            'useful_life_months' => 24,
            'acquisition_cost' => 1200000,
            'salvage_value' => 0,
            'status' => 'active',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('fixed-assets.show', $asset))
            ->assertOk()
            ->assertSee('Plan d amortissement lineaire')
            ->assertSee('Ordinateur comptable')
            ->assertSee('Previsionnel');
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
