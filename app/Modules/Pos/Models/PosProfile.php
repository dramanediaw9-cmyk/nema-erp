<?php

namespace App\Modules\Pos\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Company\Models\PriceList;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Treasury\Models\CashAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosProfile extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'warehouse_id',
        'cash_account_id',
        'price_list_id',
        'loyalty_program_id',
        'note_template_id',
        'default_printer_id',
        'default_display_id',
        'code',
        'name',
        'active_payment_methods',
        'cash_denomination_preset',
        'open_with_cash_control',
        'auto_print_receipt',
        'allow_draft_orders',
        'stock_policy',
        'show_stock_quantity',
        'show_product_images',
        'group_products_by_category',
        'share_open_orders',
        'quick_cash_payment',
        'cash_rounding_enabled',
        'cash_rounding_precision',
        'max_cash_variance',
        'allow_tips',
        'receipt_show_cashier',
        'receipt_show_address',
        'receipt_logo_path',
        'receipt_header',
        'receipt_footer',
        'is_default',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'active_payment_methods' => 'array',
            'cash_denomination_preset' => 'array',
            'open_with_cash_control' => 'boolean',
            'auto_print_receipt' => 'boolean',
            'allow_draft_orders' => 'boolean',
            'show_stock_quantity' => 'boolean',
            'show_product_images' => 'boolean',
            'group_products_by_category' => 'boolean',
            'share_open_orders' => 'boolean',
            'quick_cash_payment' => 'boolean',
            'cash_rounding_enabled' => 'boolean',
            'cash_rounding_precision' => 'decimal:2',
            'max_cash_variance' => 'decimal:2',
            'allow_tips' => 'boolean',
            'receipt_show_cashier' => 'boolean',
            'receipt_show_address' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function loyaltyProgram(): BelongsTo
    {
        return $this->belongsTo(PosLoyaltyProgram::class, 'loyalty_program_id');
    }

    public function noteTemplate(): BelongsTo
    {
        return $this->belongsTo(PosNoteTemplate::class, 'note_template_id');
    }

    public function defaultPrinter(): BelongsTo
    {
        return $this->belongsTo(PosPreparationPrinter::class, 'default_printer_id');
    }

    public function defaultDisplay(): BelongsTo
    {
        return $this->belongsTo(PosPreparationDisplay::class, 'default_display_id');
    }

    public function preparationTickets(): HasMany
    {
        return $this->hasMany(PosPreparationTicket::class, 'pos_profile_id');
    }
}
