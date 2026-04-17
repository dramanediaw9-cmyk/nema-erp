<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\PriceList;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Partners\Models\Partner;
use App\Modules\Pos\Models\PosLoyaltyProgram;
use App\Modules\Pos\Models\PosSession;
use App\Modules\Treasury\Models\CashAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosBackofficeTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_manager_can_access_odoo_like_pos_backoffice_pages(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($user)->withSession($this->workspaceSession($user))
            ->get(route('pos.orders.index'))
            ->assertOk()
            ->assertSeeText('Commandes, brouillons et retours');

        $this->actingAs($user)->withSession($this->workspaceSession($user))
            ->get(route('pos.sessions.index'))
            ->assertOk()
            ->assertSeeText('Pilotage des sessions')
            ->assertSeeText('Caisse attendue');

        $this->actingAs($user)->withSession($this->workspaceSession($user))
            ->get(route('pos.payments.index'))
            ->assertOk()
            ->assertSeeText('Paiements comptoir');

        $this->actingAs($user)->withSession($this->workspaceSession($user))
            ->get(route('pos.customers.index'))
            ->assertOk()
            ->assertSeeText('Portefeuille clients du comptoir');

        $this->actingAs($user)->withSession($this->workspaceSession($user))
            ->get(route('pos.products.index'))
            ->assertOk()
            ->assertSeeText('Catalogue PdV, variantes et combos');

        $this->actingAs($user)->withSession($this->workspaceSession($user))
            ->get(route('pos.pricing.index'))
            ->assertOk()
            ->assertSeeText('Listes de prix, fidelite et cartes valeur');

        $this->actingAs($user)->withSession($this->workspaceSession($user))
            ->get(route('pos.analytics.index'))
            ->assertOk()
            ->assertSeeText('Analyse commandes, ventes et preparation');

        $this->actingAs($user)->withSession($this->workspaceSession($user))
            ->get(route('pos.settings.index'))
            ->assertOk()
            ->assertSeeText('Parametres, profils et preparation');
    }

    public function test_manager_can_create_pos_backoffice_configuration_records(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $branch = Branch::query()->where('company_id', $user->company_id)->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $user->company_id)->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->firstOrFail();
        $priceList = PriceList::query()->where('company_id', $user->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->saleable()->firstOrFail();
        $secondProduct = Product::query()->where('company_id', $user->company_id)->saleable()->whereKeyNot($product->id)->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $user->company_id)->firstOrFail();

        $this->actingAs($user)->withSession($this->workspaceSession($user))
            ->post(route('pos.loyalty-programs.store'), [
                'code' => 'LOY-T-0001',
                'name' => 'Programme test POS',
                'program_type' => 'discount',
                'trigger_mode' => 'ticket_total',
                'reward_unit' => 'percent',
                'reward_value' => 7,
                'min_ticket_total' => 15000,
            ])
            ->assertRedirect();

        $loyalty = PosLoyaltyProgram::query()->where('company_id', $user->company_id)->where('code', 'LOY-T-0001')->firstOrFail();

        $this->actingAs($user)->withSession($this->workspaceSession($user))
            ->post(route('pos.stored-value-cards.store'), [
                'code' => 'WLT-T-0001',
                'card_type' => 'e_wallet',
                'partner_id' => $customer->id,
                'holder_name' => 'Wallet Test',
                'balance' => 9500,
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->actingAs($user)->withSession($this->workspaceSession($user))
            ->post(route('pos.payment-methods.store'), [
                'method_code' => 'other',
                'label' => 'Bon d achat',
                'cash_account_id' => $cashAccount->id,
                'sort_order' => 9,
                'is_active' => 1,
            ])
            ->assertRedirect();

        $this->actingAs($user)->withSession($this->workspaceSession($user))
            ->post(route('pos.note-templates.store'), [
                'code' => 'NOTE-T-0001',
                'name' => 'Note test',
                'usage' => 'receipt',
                'content' => 'Merci pour votre passage.',
                'is_active' => 1,
            ])
            ->assertRedirect();

        $this->actingAs($user)->withSession($this->workspaceSession($user))
            ->post(route('pos.preparation-printers.store'), [
                'code' => 'PRN-T-0001',
                'name' => 'Cuisine test',
                'target_area' => 'Cuisine',
                'connection_type' => 'network',
                'endpoint' => 'tcp://192.168.1.50:9100',
                'copy_count' => 1,
                'prep_time_target_minutes' => 11,
            ])
            ->assertRedirect();

        $this->actingAs($user)->withSession($this->workspaceSession($user))
            ->post(route('pos.preparation-displays.store'), [
                'code' => 'DSP-T-0001',
                'name' => 'Display test',
                'target_area' => 'Retrait',
                'display_mode' => 'pickup',
                'endpoint' => 'https://display.test/pickup',
                'refresh_seconds' => 15,
                'prep_time_target_minutes' => 7,
            ])
            ->assertRedirect();

        $this->actingAs($user)->withSession($this->workspaceSession($user))
            ->post(route('pos.combo-choices.store'), [
                'code' => 'CBO-T-0001',
                'name' => 'Combo test',
                'parent_product_id' => $product->id,
                'component_product_ids' => [$product->id, $secondProduct->id],
                'pricing_mode' => 'fixed',
                'price_override' => 1200,
                'max_selectable' => 2,
            ])
            ->assertRedirect();

        $this->actingAs($user)->withSession($this->workspaceSession($user))
            ->post(route('pos.menu-categories.store'), [
                'code' => 'CAT-T-0001',
                'name' => 'Categorie test',
                'color' => '#123456',
                'product_ids' => [$product->id],
                'sort_order' => 2,
            ])
            ->assertRedirect();

        $this->actingAs($user)->withSession($this->workspaceSession($user))
            ->post(route('pos.product-tags.store'), [
                'code' => 'TAG-T-0001',
                'name' => 'Tag test',
                'color' => '#654321',
                'product_ids' => [$product->id, $secondProduct->id],
            ])
            ->assertRedirect();

        $this->actingAs($user)->withSession($this->workspaceSession($user))
            ->post(route('pos.profiles.store'), [
                'code' => 'POS-T-0001',
                'name' => 'Profil test',
                'branch_id' => $branch->id,
                'warehouse_id' => $warehouse->id,
                'cash_account_id' => $cashAccount->id,
                'price_list_id' => $priceList->id,
                'loyalty_program_id' => $loyalty->id,
                'active_payment_methods' => ['cash', 'wave'],
                'cash_denomination_preset' => [
                    '10000' => 2,
                    '5000' => 1,
                ],
                'open_with_cash_control' => 1,
                'auto_print_receipt' => 1,
                'allow_draft_orders' => 1,
                'is_default' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pos_loyalty_programs', [
            'company_id' => $user->company_id,
            'code' => 'LOY-T-0001',
            'name' => 'Programme test POS',
        ]);
        $this->assertDatabaseHas('pos_stored_value_cards', [
            'company_id' => $user->company_id,
            'code' => 'WLT-T-0001',
            'holder_name' => 'Wallet Test',
        ]);
        $this->assertDatabaseHas('pos_payment_methods', [
            'company_id' => $user->company_id,
            'method_code' => 'other',
            'label' => 'Bon d achat',
        ]);
        $this->assertDatabaseHas('pos_note_templates', [
            'company_id' => $user->company_id,
            'code' => 'NOTE-T-0001',
        ]);
        $this->assertDatabaseHas('pos_preparation_printers', [
            'company_id' => $user->company_id,
            'code' => 'PRN-T-0001',
        ]);
        $this->assertDatabaseHas('pos_preparation_displays', [
            'company_id' => $user->company_id,
            'code' => 'DSP-T-0001',
        ]);
        $this->assertDatabaseHas('pos_combo_choices', [
            'company_id' => $user->company_id,
            'code' => 'CBO-T-0001',
        ]);
        $this->assertDatabaseHas('pos_menu_categories', [
            'company_id' => $user->company_id,
            'code' => 'CAT-T-0001',
        ]);
        $this->assertDatabaseHas('pos_product_tags', [
            'company_id' => $user->company_id,
            'code' => 'TAG-T-0001',
        ]);
        $this->assertDatabaseHas('pos_profiles', [
            'company_id' => $user->company_id,
            'code' => 'POS-T-0001',
            'name' => 'Profil test',
        ]);
    }

    public function test_open_session_locks_sensitive_pos_settings(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $user->company_id)->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->firstOrFail();

        $session = PosSession::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'cash_account_id' => $cashAccount->id,
            'session_number' => 'POS-LOCK-0001',
            'status' => 'open',
            'opening_amount' => 25000,
            'opened_at' => now(),
            'opened_by' => $user->id,
        ]);

        $this->actingAs($user)->withSession($this->workspaceSession($user))
            ->get(route('pos.settings.index'))
            ->assertOk()
            ->assertSeeText('Une session POS est en cours sur ce point de vente.')
            ->assertSeeText($session->session_number);

        $this->actingAs($user)->withSession($this->workspaceSession($user))
            ->post(route('pos.payment-methods.store'), [
                'method_code' => 'other',
                'label' => 'Paiement verrouille',
                'cash_account_id' => $cashAccount->id,
                'is_active' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('pos_payment_methods', [
            'company_id' => $user->company_id,
            'label' => 'Paiement verrouille',
        ]);
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
