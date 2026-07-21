<?php

namespace App\Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pos\Models\PosComboChoice;
use App\Modules\Pos\Models\PosLoyaltyProgram;
use App\Modules\Pos\Models\PosMenuCategory;
use App\Modules\Pos\Models\PosNoteTemplate;
use App\Modules\Pos\Models\PosPaymentMethod;
use App\Modules\Pos\Models\PosPreparationDisplay;
use App\Modules\Pos\Models\PosPreparationPrinter;
use App\Modules\Pos\Models\PosProductTag;
use App\Modules\Pos\Models\PosProfile;
use App\Modules\Pos\Models\PosSession;
use App\Modules\Pos\Models\PosStoredValueCard;
use App\Modules\Pos\Services\PosBackofficeService;
use App\Support\CurrentWorkspace;
use App\Support\PaymentMethodCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PosBackofficeController extends Controller
{
    public function __construct(
        private readonly PosBackofficeService $backofficeService,
    ) {
    }

    public function orders(CurrentWorkspace $workspace, Request $request): View
    {
        [$companyId, $branchId] = $this->workspaceIds($workspace);
        $cashierId = $request->user()?->hasRole('cashier') ? $request->user()->id : null;

        return view('pos.backoffice.orders', [
            'menu' => $this->backofficeService->moduleMenu(),
            'data' => $this->backofficeService->orders($companyId, $branchId, $cashierId),
        ]);
    }

    public function sessions(CurrentWorkspace $workspace): View
    {
        [$companyId, $branchId] = $this->workspaceIds($workspace);

        return view('pos.backoffice.sessions', [
            'menu' => $this->backofficeService->moduleMenu(),
            'data' => $this->backofficeService->sessions($companyId, $branchId),
        ]);
    }

    public function payments(CurrentWorkspace $workspace): View
    {
        [$companyId, $branchId] = $this->workspaceIds($workspace);

        return view('pos.backoffice.payments', [
            'menu' => $this->backofficeService->moduleMenu(),
            'data' => $this->backofficeService->payments($companyId, $branchId),
        ]);
    }

    public function customers(CurrentWorkspace $workspace): View
    {
        [$companyId, $branchId] = $this->workspaceIds($workspace);

        return view('pos.backoffice.customers', [
            'menu' => $this->backofficeService->moduleMenu(),
            'data' => $this->backofficeService->customers($companyId, $branchId),
        ]);
    }

    public function products(CurrentWorkspace $workspace, Request $request): View
    {
        [$companyId] = $this->workspaceIds($workspace);
        $productSearch = trim($request->string('product_search')->value());

        return view('pos.backoffice.products', [
            'menu' => $this->backofficeService->moduleMenu(),
            'data' => $this->backofficeService->products($companyId),
            'productOptions' => $this->backofficeService->productOptions($companyId, $productSearch),
            'productOptionsSearch' => $productSearch,
        ]);
    }

    public function pricing(CurrentWorkspace $workspace): View
    {
        [$companyId] = $this->workspaceIds($workspace);

        return view('pos.backoffice.pricing', [
            'menu' => $this->backofficeService->moduleMenu(),
            'data' => $this->backofficeService->pricing($companyId),
            'customers' => $this->backofficeService->customerOptions($companyId),
        ]);
    }

    public function analytics(CurrentWorkspace $workspace): View
    {
        [$companyId, $branchId] = $this->workspaceIds($workspace);

        return view('pos.backoffice.analytics', [
            'menu' => $this->backofficeService->moduleMenu(),
            'data' => $this->backofficeService->analytics($companyId, $branchId),
            'methodOptions' => PaymentMethodCatalog::options(),
        ]);
    }

    public function settings(CurrentWorkspace $workspace): View
    {
        [$companyId, $branchId] = $this->workspaceIds($workspace);

        return view('pos.backoffice.settings', [
            'menu' => $this->backofficeService->moduleMenu(),
            'data' => $this->backofficeService->settings($companyId, $branchId),
            'methodOptions' => PaymentMethodCatalog::options(),
        ]);
    }

    public function storePaymentMethod(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        [$companyId, $branchId] = $this->workspaceIds($workspace);

        if ($response = $this->configurationLockResponse($companyId, $branchId)) {
            return $response;
        }

        $data = $request->validate([
            'method_code' => ['required', Rule::in(PaymentMethodCatalog::values())],
            'label' => ['required', 'string', 'max:100'],
            'transaction_label' => ['nullable', 'string', 'max:100'],
            'cash_account_id' => ['nullable', Rule::exists('cash_accounts', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'requires_reference' => ['nullable', 'boolean'],
            'supports_change' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($request->boolean('is_default')) {
            PosPaymentMethod::query()->where('company_id', $companyId)->update(['is_default' => false]);
        }

        PosPaymentMethod::query()->updateOrCreate(
            ['company_id' => $companyId, 'method_code' => $data['method_code']],
            [
                'branch_id' => $branchId,
                'cash_account_id' => $data['cash_account_id'] ?? null,
                'label' => $data['label'],
                'transaction_label' => $data['transaction_label'] ?? null,
                'requires_reference' => $request->boolean('requires_reference'),
                'supports_change' => $request->boolean('supports_change'),
                'is_default' => $request->boolean('is_default'),
                'is_active' => $request->boolean('is_active', true),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'notes' => $data['notes'] ?? null,
            ]
        );

        return back()->with('success', 'Mode de paiement POS enregistre avec succes.');
    }

    public function storeLoyaltyProgram(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        [$companyId, $branchId] = $this->workspaceIds($workspace);

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40', Rule::unique('pos_loyalty_programs', 'code')->where(fn ($query) => $query->where('company_id', $companyId))],
            'name' => ['required', 'string', 'max:120'],
            'program_type' => ['required', Rule::in(['discount', 'points', 'stamp'])],
            'trigger_mode' => ['required', Rule::in(['ticket_total', 'product_qty', 'combo'])],
            'reward_unit' => ['required', Rule::in(['percent', 'fixed', 'points', 'gift'])],
            'reward_value' => ['required', 'numeric', 'min:0'],
            'min_ticket_total' => ['nullable', 'numeric', 'min:0'],
            'active_from' => ['nullable', 'date'],
            'active_to' => ['nullable', 'date', 'after_or_equal:active_from'],
            'notes' => ['nullable', 'string'],
        ]);

        PosLoyaltyProgram::query()->create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'code' => $data['code'] ?: $this->backofficeService->nextCode('pos_loyalty_programs', 'LOY', $companyId),
            'name' => $data['name'],
            'program_type' => $data['program_type'],
            'trigger_mode' => $data['trigger_mode'],
            'reward_unit' => $data['reward_unit'],
            'reward_value' => $data['reward_value'],
            'min_ticket_total' => $data['min_ticket_total'] ?? 0,
            'active_from' => $data['active_from'] ?? null,
            'active_to' => $data['active_to'] ?? null,
            'is_active' => true,
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Programme de fidelite enregistre avec succes.');
    }

    public function storeStoredValueCard(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        [$companyId, $branchId] = $this->workspaceIds($workspace);
        $company = $workspace->company();

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:80', Rule::unique('pos_stored_value_cards', 'code')->where(fn ($query) => $query->where('company_id', $companyId))],
            'card_type' => ['required', Rule::in(['gift_card', 'e_wallet'])],
            'partner_id' => ['nullable', Rule::exists('partners', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'holder_name' => ['nullable', 'string', 'max:120'],
            'balance' => ['required', 'numeric', 'min:0'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'status' => ['required', Rule::in(['draft', 'active', 'blocked', 'redeemed'])],
            'notes' => ['nullable', 'string'],
        ]);

        PosStoredValueCard::query()->create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'partner_id' => $data['partner_id'] ?? null,
            'card_type' => $data['card_type'],
            'code' => $data['code'] ?: $this->backofficeService->nextCode('pos_stored_value_cards', $data['card_type'] === 'gift_card' ? 'GFT' : 'WLT', $companyId),
            'holder_name' => $data['holder_name'] ?? null,
            'currency_code' => $company?->currency_code,
            'balance' => $data['balance'],
            'issued_at' => $data['issued_at'] ?? now()->toDateString(),
            'expires_at' => $data['expires_at'] ?? null,
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Carte-cadeau / e-wallet enregistre avec succes.');
    }

    public function storePreparationPrinter(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        [$companyId, $branchId] = $this->workspaceIds($workspace);

        if ($response = $this->configurationLockResponse($companyId, $branchId)) {
            return $response;
        }

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40', Rule::unique('pos_preparation_printers', 'code')->where(fn ($query) => $query->where('company_id', $companyId))],
            'name' => ['required', 'string', 'max:120'],
            'target_area' => ['nullable', 'string', 'max:80'],
            'connection_type' => ['required', Rule::in(['network', 'usb', 'cloud'])],
            'endpoint' => ['nullable', 'string', 'max:255'],
            'copy_count' => ['nullable', 'integer', 'min:1', 'max:10'],
            'prep_time_target_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'notes' => ['nullable', 'string'],
        ]);

        PosPreparationPrinter::query()->create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'code' => $data['code'] ?: $this->backofficeService->nextCode('pos_preparation_printers', 'PRN', $companyId),
            'name' => $data['name'],
            'target_area' => $data['target_area'] ?? null,
            'connection_type' => $data['connection_type'],
            'endpoint' => $data['endpoint'] ?? null,
            'copy_count' => (int) ($data['copy_count'] ?? 1),
            'prep_time_target_minutes' => (int) ($data['prep_time_target_minutes'] ?? 0),
            'is_active' => true,
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Imprimante de preparation enregistree avec succes.');
    }

    public function storePreparationDisplay(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        [$companyId, $branchId] = $this->workspaceIds($workspace);

        if ($response = $this->configurationLockResponse($companyId, $branchId)) {
            return $response;
        }

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40', Rule::unique('pos_preparation_displays', 'code')->where(fn ($query) => $query->where('company_id', $companyId))],
            'name' => ['required', 'string', 'max:120'],
            'target_area' => ['nullable', 'string', 'max:80'],
            'display_mode' => ['required', Rule::in(['kitchen', 'pickup', 'counter'])],
            'endpoint' => ['nullable', 'string', 'max:255'],
            'refresh_seconds' => ['nullable', 'integer', 'min:5', 'max:300'],
            'prep_time_target_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'notes' => ['nullable', 'string'],
        ]);

        PosPreparationDisplay::query()->create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'code' => $data['code'] ?: $this->backofficeService->nextCode('pos_preparation_displays', 'DSP', $companyId),
            'name' => $data['name'],
            'target_area' => $data['target_area'] ?? null,
            'display_mode' => $data['display_mode'],
            'endpoint' => $data['endpoint'] ?? null,
            'refresh_seconds' => (int) ($data['refresh_seconds'] ?? 20),
            'prep_time_target_minutes' => (int) ($data['prep_time_target_minutes'] ?? 0),
            'is_active' => true,
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Preparation Display enregistre avec succes.');
    }

    public function storeNoteTemplate(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        [$companyId, $branchId] = $this->workspaceIds($workspace);

        if ($response = $this->configurationLockResponse($companyId, $branchId)) {
            return $response;
        }

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40', Rule::unique('pos_note_templates', 'code')->where(fn ($query) => $query->where('company_id', $companyId))],
            'name' => ['required', 'string', 'max:120'],
            'usage' => ['required', Rule::in(['receipt', 'kitchen', 'prep'])],
            'content' => ['required', 'string'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('is_default')) {
            PosNoteTemplate::query()->where('company_id', $companyId)->update(['is_default' => false]);
        }

        PosNoteTemplate::query()->create([
            'company_id' => $companyId,
            'code' => $data['code'] ?: $this->backofficeService->nextCode('pos_note_templates', 'NOTE', $companyId),
            'name' => $data['name'],
            'usage' => $data['usage'],
            'content' => $data['content'],
            'is_default' => $request->boolean('is_default'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Modele de note POS enregistre avec succes.');
    }

    public function storeComboChoice(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        [$companyId, $branchId] = $this->workspaceIds($workspace);

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40', Rule::unique('pos_combo_choices', 'code')->where(fn ($query) => $query->where('company_id', $companyId))],
            'name' => ['required', 'string', 'max:120'],
            'parent_product_id' => ['nullable', Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'component_product_ids' => ['nullable', 'array'],
            'component_product_ids.*' => ['integer', Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'pricing_mode' => ['required', Rule::in(['sum', 'fixed', 'free_choice'])],
            'price_override' => ['nullable', 'numeric', 'min:0'],
            'max_selectable' => ['nullable', 'integer', 'min:1', 'max:20'],
            'notes' => ['nullable', 'string'],
        ]);

        PosComboChoice::query()->create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'code' => $data['code'] ?: $this->backofficeService->nextCode('pos_combo_choices', 'CBO', $companyId),
            'name' => $data['name'],
            'parent_product_id' => $data['parent_product_id'] ?? null,
            'component_product_ids' => $this->normalizeIntegerArray($data['component_product_ids'] ?? []),
            'pricing_mode' => $data['pricing_mode'],
            'price_override' => $data['price_override'] ?? null,
            'max_selectable' => $data['max_selectable'] ?? null,
            'is_active' => true,
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Choix de combo enregistre avec succes.');
    }

    public function storeMenuCategory(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        [$companyId] = $this->workspaceIds($workspace);

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40', Rule::unique('pos_menu_categories', 'code')->where(fn ($query) => $query->where('company_id', $companyId))],
            'name' => ['required', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'max:20'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        PosMenuCategory::query()->create([
            'company_id' => $companyId,
            'code' => $data['code'] ?: $this->backofficeService->nextCode('pos_menu_categories', 'CAT', $companyId),
            'name' => $data['name'],
            'color' => $data['color'] ?? null,
            'product_ids' => $this->normalizeIntegerArray($data['product_ids'] ?? []),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => true,
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Categorie POS enregistree avec succes.');
    }

    public function storeProductTag(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        [$companyId] = $this->workspaceIds($workspace);

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40', Rule::unique('pos_product_tags', 'code')->where(fn ($query) => $query->where('company_id', $companyId))],
            'name' => ['required', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'max:20'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'notes' => ['nullable', 'string'],
        ]);

        PosProductTag::query()->create([
            'company_id' => $companyId,
            'code' => $data['code'] ?: $this->backofficeService->nextCode('pos_product_tags', 'TAG', $companyId),
            'name' => $data['name'],
            'color' => $data['color'] ?? null,
            'product_ids' => $this->normalizeIntegerArray($data['product_ids'] ?? []),
            'is_active' => true,
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Etiquette produit POS enregistree avec succes.');
    }

    public function storeProfile(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        [$companyId, $branchId] = $this->workspaceIds($workspace);

        if ($response = $this->configurationLockResponse($companyId, $branchId)) {
            return $response;
        }

        $data = $request->validate([
            'profile_id' => ['nullable', Rule::exists('pos_profiles', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'code' => ['nullable', 'string', 'max:40', Rule::unique('pos_profiles', 'code')->where(fn ($query) => $query->where('company_id', $companyId))->ignore($request->integer('profile_id') ?: null)],
            'name' => ['required', 'string', 'max:120'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'cash_account_id' => ['nullable', Rule::exists('cash_accounts', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'price_list_id' => ['nullable', Rule::exists('price_lists', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'loyalty_program_id' => ['nullable', Rule::exists('pos_loyalty_programs', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'note_template_id' => ['nullable', Rule::exists('pos_note_templates', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'default_printer_id' => ['nullable', Rule::exists('pos_preparation_printers', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'default_display_id' => ['nullable', Rule::exists('pos_preparation_displays', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'active_payment_methods' => ['nullable', 'array'],
            'active_payment_methods.*' => [Rule::in(PaymentMethodCatalog::values())],
            'cash_denomination_preset' => ['nullable', 'array'],
            'cash_denomination_preset.*' => ['nullable', 'integer', 'min:0'],
            'open_with_cash_control' => ['nullable', 'boolean'],
            'auto_print_receipt' => ['nullable', 'boolean'],
            'allow_draft_orders' => ['nullable', 'boolean'],
            'stock_policy' => ['nullable', Rule::in(['block', 'warn', 'allow'])],
            'show_stock_quantity' => ['nullable', 'boolean'],
            'show_product_images' => ['nullable', 'boolean'],
            'group_products_by_category' => ['nullable', 'boolean'],
            'share_open_orders' => ['nullable', 'boolean'],
            'quick_cash_payment' => ['nullable', 'boolean'],
            'cash_rounding_enabled' => ['nullable', 'boolean'],
            'cash_rounding_precision' => ['nullable', 'numeric', 'min:0.01', 'max:1000'],
            'max_cash_variance' => ['nullable', 'numeric', 'min:0'],
            'allow_tips' => ['nullable', 'boolean'],
            'receipt_show_cashier' => ['nullable', 'boolean'],
            'receipt_show_address' => ['nullable', 'boolean'],
            'receipt_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'receipt_header' => ['nullable', 'string', 'max:255'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($request->boolean('is_default')) {
            PosProfile::query()->where('company_id', $companyId)->update(['is_default' => false]);
        }

        $profile = filled($data['profile_id'] ?? null)
            ? PosProfile::query()->where('company_id', $companyId)->findOrFail($data['profile_id'])
            : new PosProfile(['company_id' => $companyId]);

        $receiptLogoPath = $profile->receipt_logo_path;
        if ($request->hasFile('receipt_logo')) {
            $newLogoPath = $request->file('receipt_logo')->store('pos/receipt-logos', 'public');
            if ($receiptLogoPath) {
                Storage::disk('public')->delete($receiptLogoPath);
            }
            $receiptLogoPath = $newLogoPath;
        }

        $profile->fill([
            'company_id' => $companyId,
            'branch_id' => $data['branch_id'] ?? $branchId,
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'cash_account_id' => $data['cash_account_id'] ?? null,
            'price_list_id' => $data['price_list_id'] ?? null,
            'loyalty_program_id' => $data['loyalty_program_id'] ?? null,
            'note_template_id' => $data['note_template_id'] ?? null,
            'default_printer_id' => $data['default_printer_id'] ?? null,
            'default_display_id' => $data['default_display_id'] ?? null,
            'code' => $data['code'] ?: ($profile->code ?: $this->backofficeService->nextCode('pos_profiles', 'POS', $companyId)),
            'name' => $data['name'],
            'active_payment_methods' => array_values($data['active_payment_methods'] ?? []),
            'cash_denomination_preset' => $data['cash_denomination_preset'] ?? [],
            'open_with_cash_control' => $request->boolean('open_with_cash_control', true),
            'auto_print_receipt' => $request->boolean('auto_print_receipt', true),
            'allow_draft_orders' => $request->boolean('allow_draft_orders', true),
            'stock_policy' => $data['stock_policy'] ?? 'block',
            'show_stock_quantity' => $request->boolean('show_stock_quantity', true),
            'show_product_images' => $request->boolean('show_product_images', true),
            'group_products_by_category' => $request->boolean('group_products_by_category', true),
            'share_open_orders' => $request->boolean('share_open_orders'),
            'quick_cash_payment' => $request->boolean('quick_cash_payment'),
            'cash_rounding_enabled' => $request->boolean('cash_rounding_enabled'),
            'cash_rounding_precision' => $data['cash_rounding_precision'] ?? 5,
            'max_cash_variance' => $data['max_cash_variance'] ?? null,
            'allow_tips' => $request->boolean('allow_tips'),
            'receipt_show_cashier' => $request->boolean('receipt_show_cashier', true),
            'receipt_show_address' => $request->boolean('receipt_show_address', true),
            'receipt_logo_path' => $receiptLogoPath,
            'receipt_header' => $data['receipt_header'] ?? null,
            'receipt_footer' => $data['receipt_footer'] ?? null,
            'is_default' => $request->boolean('is_default'),
            'is_active' => true,
            'notes' => $data['notes'] ?? null,
        ]);
        $profile->save();

        return back()->with('success', 'Configuration de la caisse enregistree avec succes.');
    }

    private function workspaceIds(CurrentWorkspace $workspace): array
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();

        abort_if(! $companyId || ! $branchId, 403);

        return [$companyId, $branchId];
    }

    private function normalizeIntegerArray(array $values): array
    {
        return collect($values)
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();
    }

    private function configurationLockResponse(int $companyId, int $branchId): ?RedirectResponse
    {
        $openSession = PosSession::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();

        if (! $openSession) {
            return null;
        }

        return back()->with('error', 'Une session POS est en cours sur '.$openSession->session_number.'. Ferme d abord la session avant de modifier ces parametres sensibles.');
    }
}
