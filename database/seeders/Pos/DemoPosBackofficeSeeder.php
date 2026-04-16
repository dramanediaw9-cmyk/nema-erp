<?php

namespace Database\Seeders\Pos;

use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Company\Models\PriceList;
use App\Modules\Pos\Models\PosComboChoice;
use App\Modules\Pos\Models\PosLoyaltyProgram;
use App\Modules\Pos\Models\PosMenuCategory;
use App\Modules\Pos\Models\PosNoteTemplate;
use App\Modules\Pos\Models\PosPaymentMethod;
use App\Modules\Pos\Models\PosPreparationDisplay;
use App\Modules\Pos\Models\PosPreparationPrinter;
use App\Modules\Pos\Models\PosProductTag;
use App\Modules\Pos\Models\PosProfile;
use App\Modules\Pos\Models\PosStoredValueCard;
use App\Modules\Treasury\Models\CashAccount;
use App\Support\PaymentMethodCatalog;
use Illuminate\Database\Seeder;

class DemoPosBackofficeSeeder extends Seeder
{
    public function run(): void
    {
        Company::query()->orderBy('id')->get()->each(function (Company $company): void {
            $branch = Branch::query()->where('company_id', $company->id)->orderBy('id')->first();
            $cashAccount = CashAccount::query()->where('company_id', $company->id)->orderBy('id')->first();
            $waveAccount = CashAccount::query()->where('company_id', $company->id)->where('name', 'Wave')->first();
            $warehouse = $company->warehouses()->orderByDesc('is_default')->orderBy('id')->first();
            $priceList = PriceList::query()->where('company_id', $company->id)->where('is_active', true)->orderByDesc('is_default')->orderBy('id')->first();
            $products = Product::query()->where('company_id', $company->id)->saleable()->orderBy('id')->take(6)->get();

            foreach (PaymentMethodCatalog::options() as $code => $label) {
                PosPaymentMethod::query()->updateOrCreate(
                    ['company_id' => $company->id, 'method_code' => $code],
                    [
                        'branch_id' => $branch?->id,
                        'cash_account_id' => in_array($code, ['wave', 'orange_money', 'moov_money', 'mobile_money'], true) ? $waveAccount?->id : $cashAccount?->id,
                        'label' => $label,
                        'transaction_label' => $label.' POS',
                        'requires_reference' => in_array($code, ['wave', 'orange_money', 'moov_money', 'bank_transfer', 'cheque'], true),
                        'supports_change' => $code === 'cash',
                        'is_default' => $code === 'cash',
                        'is_active' => true,
                        'sort_order' => (int) array_search($code, array_keys(PaymentMethodCatalog::options()), true),
                    ]
                );
            }

            $loyalty = PosLoyaltyProgram::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'LOY-0001'],
                [
                    'branch_id' => $branch?->id,
                    'name' => 'Fidelite comptoir',
                    'program_type' => 'discount',
                    'trigger_mode' => 'ticket_total',
                    'reward_unit' => 'percent',
                    'reward_value' => 5,
                    'min_ticket_total' => 10000,
                    'is_active' => true,
                ]
            );

            PosStoredValueCard::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'GFT-0001'],
                [
                    'branch_id' => $branch?->id,
                    'card_type' => 'gift_card',
                    'holder_name' => 'Carte cadeau standard',
                    'currency_code' => $company->currency_code,
                    'balance' => 25000,
                    'issued_at' => now()->toDateString(),
                    'status' => 'active',
                ]
            );

            PosStoredValueCard::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'WLT-0001'],
                [
                    'branch_id' => $branch?->id,
                    'card_type' => 'e_wallet',
                    'holder_name' => 'Wallet client comptoir',
                    'currency_code' => $company->currency_code,
                    'balance' => 12500,
                    'issued_at' => now()->toDateString(),
                    'status' => 'active',
                ]
            );

            $printer = PosPreparationPrinter::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'PRN-0001'],
                [
                    'branch_id' => $branch?->id,
                    'name' => 'Cuisine principale',
                    'target_area' => 'Cuisine',
                    'connection_type' => 'network',
                    'endpoint' => 'tcp://192.168.1.40:9100',
                    'copy_count' => 2,
                    'prep_time_target_minutes' => 12,
                    'is_active' => true,
                ]
            );

            $display = PosPreparationDisplay::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'DSP-0001'],
                [
                    'branch_id' => $branch?->id,
                    'name' => 'Ecran retrait comptoir',
                    'target_area' => 'Retrait',
                    'display_mode' => 'pickup',
                    'endpoint' => 'https://display.nema.test/pickup',
                    'refresh_seconds' => 20,
                    'prep_time_target_minutes' => 8,
                    'is_active' => true,
                ]
            );

            $noteTemplate = PosNoteTemplate::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'NOTE-0001'],
                [
                    'name' => 'Note ticket standard',
                    'usage' => 'receipt',
                    'content' => "Merci pour votre achat.\nRetour sous 48h avec ticket.",
                    'is_default' => true,
                    'is_active' => true,
                ]
            );

            if ($products->count() >= 3) {
                PosComboChoice::query()->updateOrCreate(
                    ['company_id' => $company->id, 'code' => 'CBO-0001'],
                    [
                        'branch_id' => $branch?->id,
                        'name' => 'Menu duo rapide',
                        'parent_product_id' => $products->first()->id,
                        'component_product_ids' => $products->take(3)->pluck('id')->values()->all(),
                        'pricing_mode' => 'fixed',
                        'price_override' => 1500,
                        'max_selectable' => 2,
                        'is_active' => true,
                    ]
                );
            }

            PosMenuCategory::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'CAT-0001'],
                [
                    'name' => 'Best sellers',
                    'color' => '#1f8ef1',
                    'product_ids' => $products->take(4)->pluck('id')->values()->all(),
                    'sort_order' => 1,
                    'is_active' => true,
                ]
            );

            PosProductTag::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'TAG-0001'],
                [
                    'name' => 'Vente rapide',
                    'color' => '#16a34a',
                    'product_ids' => $products->take(3)->pluck('id')->values()->all(),
                    'is_active' => true,
                ]
            );

            PosProfile::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'POS-0001'],
                [
                    'branch_id' => $branch?->id,
                    'warehouse_id' => $warehouse?->id,
                    'cash_account_id' => $cashAccount?->id,
                    'price_list_id' => $priceList?->id,
                    'loyalty_program_id' => $loyalty->id,
                    'note_template_id' => $noteTemplate->id,
                    'default_printer_id' => $printer->id,
                    'default_display_id' => $display->id,
                    'name' => 'Profil caisse principale',
                    'active_payment_methods' => ['cash', 'wave', 'orange_money', 'bank_transfer'],
                    'cash_denomination_preset' => ['10000' => 2, '5000' => 4, '1000' => 10],
                    'open_with_cash_control' => true,
                    'auto_print_receipt' => true,
                    'allow_draft_orders' => true,
                    'is_default' => true,
                    'is_active' => true,
                ]
            );
        });
    }
}
